<?php

namespace App\Services\Tenant\Finance;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Foundation\NumberSequenceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashReconciliationService
{
    private const MOVEMENT_TOTAL_SQL = <<<'SQL'
        COALESCE(SUM(CASE
            WHEN movement_reason IN ('cash_sale', 'cash_in') THEN amount
            WHEN movement_reason IN ('cash_out', 'safe_drop', 'cash_refund') THEN -amount
            ELSE 0
        END), 0) AS movement_total
        SQL;

    public function __construct(private readonly NumberSequenceService $numbers) {}

    /** Load response details in batches and calculate previews without changing saved closing totals. */
    public function prepareForResponse(Collection $reconciliations): void
    {
        if ($reconciliations->isEmpty()) {
            return;
        }
        $reconciliations->load(['cashAccount:id,uuid,name,currency_code', 'countLines']);
        $movementTotals = CashMovement::query()
            ->whereIn('cash_account_id', $reconciliations->pluck('cash_account_id')
                ->unique())
            ->whereIn('business_date_id', $reconciliations->pluck('business_date_id')
                ->unique())
            ->select(['cash_account_id', 'business_date_id'])
            ->selectRaw(self::MOVEMENT_TOTAL_SQL)
            ->groupBy('cash_account_id', 'business_date_id')
            ->get()
            ->keyBy(fn ($total) => $total->business_date_id.':'.$total->cash_account_id);
        foreach ($reconciliations as $reconciliation) {
            if ($reconciliation->status === 'closed') {
                continue;
            }
            $key = $reconciliation->business_date_id.':'.$reconciliation->cash_account_id;
            $movementTotal = $movementTotals->get($key)?->movement_total ?? '0.00';
            $expectedCash = bcadd((string) $reconciliation->opening_float_amount, (string) $movementTotal, 2);
            $countedCash = null;
            if ($reconciliation->countLines->isNotEmpty()) {
                $countedCash = $reconciliation->countLines->reduce(fn (string $total, $line): string => bcadd($total, $line->line_amount, 2), '0.00');
            }
            $reconciliation->forceFill([
                'expected_cash_amount' => $expectedCash,
                'counted_cash_amount' => $countedCash,
                'variance_amount' => $countedCash === null ? null : bcsub($countedCash, $expectedCash, 2),
            ]);
        }
    }

    /** @return Collection<int, BusinessDayCashReconciliation> */
    public function openForBusinessDay(Branch $branch, BusinessDate $businessDay, TenantUser $actor): Collection
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $businessDay, $actor): Collection {
                $lockedBusinessDay = $this->lockActiveBusinessDay($branch, $businessDay->id);

                return $this->reconcilableAccounts($branch)
                    ->map(fn (CashAccount $account): BusinessDayCashReconciliation => $this->findOrCreateReconciliation($branch, $lockedBusinessDay, $account, $actor));
            });
    }

    /** @param array{business_day_uuid: string, movement_reason: string, amount: string, notes: string} $data */
    public function recordMovement(Branch $branch, CashAccount $cashAccount, TenantUser $actor, array $data): CashMovement
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $cashAccount, $actor, $data): CashMovement {
                $account = $this->lockActiveCashAccount($branch, $cashAccount->id);
                $businessDay = $this->lockBusinessDayByUuid($branch, $data['business_day_uuid']);
                $this->ensureDrawerIsOpen($businessDay, $account);

                return CashMovement::query()
                    ->create([
                        'cash_account_id' => $account->id,
                        'branch_id' => $branch->id,
                        'business_date_id' => $businessDay->id,
                        'performed_by_tenant_user_id' => $actor->id,
                        'movement_reason' => $data['movement_reason'],
                        'amount' => $data['amount'],
                        'moved_at' => now(),
                        'notes' => $data['notes'],
                    ]);
            });
    }

    /** @param array{lines: array<int, array{denomination_value: string, quantity: int}>, notes?: string} $data */
    public function replaceCount(BusinessDayCashReconciliation $reconciliation, Branch $branch, array $data): BusinessDayCashReconciliation
    {
        return DB::connection('tenant')
            ->transaction(function () use ($reconciliation, $branch, $data): BusinessDayCashReconciliation {
                $lockedReconciliation = $this->lockReconciliation($reconciliation, $branch);
                $this->ensureCountCanBeUpdated($lockedReconciliation);
                $lockedReconciliation->update([
                    'status' => 'closing',
                    'notes' => $data['notes'] ?? $lockedReconciliation->notes,
                ]);
                $this->replaceCountLines($lockedReconciliation, $data['lines']);

                return $lockedReconciliation->refresh();
            });
    }

    /** @param array{notes?: string} $data */
    public function close(BusinessDayCashReconciliation $reconciliation, Branch $branch, TenantUser $actor, array $data): BusinessDayCashReconciliation
    {
        return DB::connection('tenant')
            ->transaction(function () use ($reconciliation, $branch, $actor, $data): BusinessDayCashReconciliation {
                $lockedReconciliation = $this->lockReconciliation($reconciliation, $branch);
                $this->ensureReadyToClose($lockedReconciliation);
                $expectedCash = $this->expectedCash($lockedReconciliation);
                $countedCash = $this->countedCash($lockedReconciliation);
                $lockedReconciliation->update([
                    'status' => 'closed',
                    'expected_cash_amount' => $expectedCash,
                    'counted_cash_amount' => $countedCash,
                    'variance_amount' => bcsub($countedCash, $expectedCash, 2),
                    'closed_by_tenant_user_id' => $actor->id,
                    'closed_at' => now(),
                    'notes' => $data['notes'] ?? $lockedReconciliation->notes,
                ]);

                return $lockedReconciliation->refresh();
            });
    }

    private function lockActiveBusinessDay(Branch $branch, int $businessDayId): BusinessDate
    {
        $businessDay = BusinessDate::query()
            ->whereKey($businessDayId)
            ->where('branch_id', $branch->id)
            ->lockForUpdate()
            ->firstOrFail();
        if (! in_array($businessDay->status, ['open', 'closing'], true)) {
            throw ValidationException::withMessages(['business_day' => ['Cash reconciliation can only be opened for an active business day.']]);
        }

        return $businessDay;
    }

    private function lockBusinessDayByUuid(Branch $branch, string $businessDayUuid): BusinessDate
    {
        return BusinessDate::query()
            ->where('uuid', $businessDayUuid)
            ->where('branch_id', $branch->id)
            ->whereIn('status', ['open', 'closing'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return Collection<int, CashAccount> */
    private function reconcilableAccounts(Branch $branch): Collection
    {
        return CashAccount::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->where('requires_reconciliation', true)
            ->lockForUpdate()
            ->get();
    }

    private function findOrCreateReconciliation(Branch $branch, BusinessDate $businessDay, CashAccount $account, TenantUser $actor): BusinessDayCashReconciliation
    {
        $existing = BusinessDayCashReconciliation::query()
            ->where('business_date_id', $businessDay->id)
            ->where('cash_account_id', $account->id)
            ->first();
        if ($existing) {
            return $existing;
        }
        [$number, $allocation] = $this->numbers->reserve($branch, 'cash_reconciliation', 'business_day_cash_reconciliations', 'CASH');
        $openingFloat = $account->is_default ? $businessDay->opening_float_amount : '0.00';
        $reconciliation = BusinessDayCashReconciliation::query()
            ->create([
                'business_date_id' => $businessDay->id,
                'cash_account_id' => $account->id,
                'branch_id' => $branch->id,
                'opened_by_tenant_user_id' => $actor->id,
                'reconciliation_number' => $number,
                'opening_float_amount' => $openingFloat,
                'expected_cash_amount' => $openingFloat,
                'status' => 'open',
                'opened_at' => now(),
            ]);
        $this->numbers->use($allocation, $reconciliation->id, $reconciliation->uuid);

        return $reconciliation;
    }

    private function lockActiveCashAccount(Branch $branch, int $cashAccountId): CashAccount
    {
        return CashAccount::query()
            ->whereKey($cashAccountId)
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureDrawerIsOpen(BusinessDate $businessDay, CashAccount $account): void
    {
        $isOpen = BusinessDayCashReconciliation::query()
            ->where('business_date_id', $businessDay->id)
            ->where('cash_account_id', $account->id)
            ->where('status', 'open')
            ->lockForUpdate()
            ->exists();
        if (! $isOpen) {
            throw ValidationException::withMessages(['cash_account' => ['This drawer is not open for movements on the selected business day.']]);
        }
    }

    private function ensureCountCanBeUpdated(BusinessDayCashReconciliation $reconciliation): void
    {
        if (! in_array($reconciliation->status, ['open', 'closing'], true)) {
            throw ValidationException::withMessages(['reconciliation' => ['Only an open drawer can be counted.']]);
        }
    }

    private function ensureReadyToClose(BusinessDayCashReconciliation $reconciliation): void
    {
        if ($reconciliation->status !== 'closing') {
            throw ValidationException::withMessages(['reconciliation' => ['Save the physical cash count before closing the reconciliation.']]);
        }
    }

    /** @param array<int, array{denomination_value: string, quantity: int}> $lines */
    private function replaceCountLines(BusinessDayCashReconciliation $reconciliation, array $lines): void
    {
        $query = DB::connection('tenant')
            ->table('cash_count_lines');
        $query->where('business_day_cash_reconciliation_id', $reconciliation->id)
            ->delete();
        foreach ($lines as $line) {
            $query->insert([
                'uuid' => (string) Str::uuid7(),
                'business_day_cash_reconciliation_id' => $reconciliation->id,
                'denomination_value' => $line['denomination_value'],
                'quantity' => $line['quantity'],
                'line_amount' => bcmul((string) $line['denomination_value'], (string) $line['quantity'], 2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function countedCash(BusinessDayCashReconciliation $reconciliation): string
    {
        return (string) DB::connection('tenant')
            ->table('cash_count_lines')
            ->where('business_day_cash_reconciliation_id', $reconciliation->id)
            ->sum('line_amount');
    }

    private function expectedCash(BusinessDayCashReconciliation $reconciliation): string
    {
        $movementTotal = CashMovement::query()
            ->where('cash_account_id', $reconciliation->cash_account_id)
            ->where('business_date_id', $reconciliation->business_date_id)
            ->selectRaw(self::MOVEMENT_TOTAL_SQL)
            ->value('movement_total');

        return bcadd((string) $reconciliation->opening_float_amount, (string) $movementTotal, 2);
    }

    private function lockReconciliation(BusinessDayCashReconciliation $reconciliation, Branch $branch): BusinessDayCashReconciliation
    {
        return BusinessDayCashReconciliation::query()
            ->whereKey($reconciliation->id)
            ->where('branch_id', $branch->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
