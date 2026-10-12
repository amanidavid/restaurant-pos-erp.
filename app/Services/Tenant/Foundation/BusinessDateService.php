<?php

namespace App\Services\Tenant\Foundation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\NumberSequence;
use App\Models\Tenant\NumberSequenceAllocation;
use App\Models\Tenant\TenantUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessDateService
{
    public function current(Branch $branch): ?BusinessDate
    {
        return $branch->businessDates()
            ->whereIn('status', ['open', 'closing'])
            ->orderByDesc('opened_at')
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function open(Branch $branch, TenantUser $actor, array $data): BusinessDate
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $data): BusinessDate {
                $branch = Branch::query()
                    ->lockForUpdate()
                    ->findOrFail($branch->id);
                if ($branch->status !== 'active') {
                    throw ValidationException::withMessages(['branch' => ['Only an active branch can open a business day.']]);
                }
                if ($this->current($branch)) {
                    throw ValidationException::withMessages(['business_date' => ['Close the current business day before opening another one.']]);
                }
                $now = CarbonImmutable::now($branch->timezone);
                $businessDate = CarbonImmutable::parse($data['business_date'] ?? $now->toDateString(), $branch->timezone);
                $configuration = $branch->activeConfiguration()
                    ->firstOrFail();
                [$plannedOpenAt, $plannedCloseAt] = $this->plannedHours(
                    $businessDate,
                    $configuration->business_day_opens_at,
                    $configuration->business_day_closes_at,
                    $branch->timezone,
                );
                [$businessDayNumber, $allocation] = $this->allocateNumber($branch, $businessDate, $now);
                $openingFloat = $data['opening_float_amount'] ?? '0.00';
                $businessDay = BusinessDate::query()
                    ->create([
                        'branch_id' => $branch->id,
                        'business_date' => $businessDate->toDateString(),
                        'business_day_number' => $businessDayNumber,
                        'status' => 'open',
                        'opened_by_id' => $actor->id,
                        'planned_open_at' => $plannedOpenAt,
                        'planned_close_at' => $plannedCloseAt,
                        'opened_at' => $now,
                        'opening_float_amount' => $openingFloat,
                        'expected_cash_amount' => $openingFloat,
                        'opening_notes' => $data['opening_notes'] ?? null,
                    ]);
                $allocation->forceFill([
                    'consumer_id' => $businessDay->id,
                    'consumer_uuid' => $businessDay->uuid,
                    'status' => 'used',
                    'used_at' => $now,
                ])
                    ->save();

                return $businessDay->load('branch');
            });
    }

    /** @param array<string, mixed> $data */
    public function close(Branch $branch, BusinessDate $businessDay, TenantUser $actor, array $data): BusinessDate
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $businessDay, $actor, $data): BusinessDate {
                $businessDay = BusinessDate::query()
                    ->whereKey($businessDay->id)
                    ->where('branch_id', $branch->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($businessDay->status, ['open', 'closing'], true)) {
                    throw ValidationException::withMessages(['business_day' => ['This business day is already closed.']]);
                }
                $this->ensureCanClose($businessDay);
                $cashTotals = $this->closedCashTotals($businessDay);
                $now = CarbonImmutable::now($branch->timezone);
                $businessDay->forceFill([
                    'status' => 'closed',
                    'closed_by_id' => $actor->id,
                    'closed_at' => $now,
                    'expected_cash_amount' => $cashTotals->expected_cash_amount,
                    'counted_cash_amount' => $cashTotals->counted_cash_amount,
                    'closing_variance_amount' => $cashTotals->variance_amount,
                    'closing_notes' => $data['closing_notes'] ?? null,
                ])
                    ->save();

                return $businessDay->refresh()
                    ->load('branch');
            });
    }

    private function ensureCanClose(BusinessDate $businessDay): void
    {
        $openOrderCount = DB::connection('tenant')
            ->table('orders')
            ->where('business_date_id', $businessDay->id)
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->count();
        $pendingReconciliationCount = DB::connection('tenant')
            ->table('business_day_cash_reconciliations')
            ->where('business_date_id', $businessDay->id)
            ->whereIn('status', ['open', 'closing'])
            ->count();
        $missingAccounts = DB::connection('tenant')
            ->table('cash_accounts as accounts')
            ->where('accounts.branch_id', $businessDay->branch_id)
            ->where('accounts.status', 'active')
            ->where('accounts.requires_reconciliation', true)
            ->whereNotExists(function ($query) use ($businessDay): void {
                $query->selectRaw('1')
                    ->from('business_day_cash_reconciliations as reconciliations')
                    ->whereColumn('reconciliations.cash_account_id', 'accounts.id')
                    ->where('reconciliations.business_date_id', $businessDay->id);
            });
        $missingAccountCount = (clone $missingAccounts)->count();
        $messages = [];
        if ($openOrderCount > 0) {
            $messages[] = "Close the remaining orders before closing this business day ({$openOrderCount} remaining).";
        }
        if ($pendingReconciliationCount > 0) {
            $messages[] = "Complete and close the pending cash account reconciliations ({$pendingReconciliationCount} remaining).";
        }
        if ($missingAccountCount > 0) {
            $accountNames = (clone $missingAccounts)->orderBy('accounts.id')
                ->limit(15)
                ->pluck('accounts.name')
                ->implode(', ');
            $remainingCount = $missingAccountCount - 15;
            $additionalAccounts = $remainingCount > 0 ? " and {$remainingCount} more accounts" : '';
            $messages[] = "Open, count, and close reconciliations for these required cash accounts: {$accountNames}{$additionalAccounts}.";
        }
        if ($messages !== []) {
            throw ValidationException::withMessages(['business_day' => $messages]);
        }
    }

    /** @return object{expected_cash_amount: string, counted_cash_amount: string, variance_amount: string} */
    private function closedCashTotals(BusinessDate $businessDay): object
    {
        return DB::connection('tenant')
            ->table('business_day_cash_reconciliations')
            ->where('business_date_id', $businessDay->id)
            ->where('status', 'closed')
            ->selectRaw('COALESCE(SUM(expected_cash_amount), 0) AS expected_cash_amount')
            ->selectRaw('COALESCE(SUM(counted_cash_amount), 0) AS counted_cash_amount')
            ->selectRaw('COALESCE(SUM(variance_amount), 0) AS variance_amount')
            ->firstOrFail();
    }

    /** @return array{0: string, 1: NumberSequenceAllocation} */
    private function allocateNumber(Branch $branch, CarbonImmutable $businessDate, CarbonImmutable $now): array
    {
        $definition = NumberSequence::query()
            ->where('branch_id', $branch->id)
            ->where('sequence_key', 'business_day')
            ->whereNull('period_starts_on')
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();
        if (! $definition) {
            $definition = NumberSequence::query()
                ->create([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'sequence_key' => 'business_day',
                    'display_name' => "{$branch->name} Business Days",
                    'prefix' => 'BD',
                    'period_type' => 'daily',
                    'next_number' => 1,
                    'padding_length' => 2,
                    'reset_per_branch' => true,
                    'is_active' => true,
                ]);
        }
        $sequence = NumberSequence::query()
            ->where('branch_id', $branch->id)
            ->where('sequence_key', 'business_day')
            ->whereDate('period_starts_on', $businessDate->toDateString())
            ->lockForUpdate()
            ->first();
        if (! $sequence) {
            $sequence = NumberSequence::query()
                ->create([
                    'company_id' => $definition->company_id,
                    'branch_id' => $branch->id,
                    'sequence_key' => $definition->sequence_key,
                    'display_name' => $definition->display_name,
                    'prefix' => $definition->prefix,
                    'suffix' => $definition->suffix,
                    'period_type' => 'daily',
                    'period_starts_on' => $businessDate->toDateString(),
                    'period_ends_on' => $businessDate->toDateString(),
                    'next_number' => 1,
                    'padding_length' => $definition->padding_length,
                    'reset_per_branch' => true,
                    'is_active' => true,
                ]);
        }
        $sequence = NumberSequence::query()
            ->lockForUpdate()
            ->findOrFail($sequence->id);
        $number = $sequence->next_number;
        $paddedNumber = str_pad((string) $number, $sequence->padding_length, '0', STR_PAD_LEFT);
        $formatted = sprintf('%s-%s-%s', $sequence->prefix ?: 'BD', $businessDate->format('Ymd'), $paddedNumber);
        $allocation = NumberSequenceAllocation::query()
            ->create([
                'number_sequence_id' => $sequence->id,
                'branch_id' => $branch->id,
                'sequence_key' => 'business_day',
                'allocated_number' => $number,
                'formatted_number' => $formatted,
                'consumer_table' => 'business_dates',
                'status' => 'reserved',
                'reserved_at' => $now,
            ]);
        $sequence->increment('next_number');

        return [$formatted, $allocation];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function plannedHours(CarbonImmutable $businessDate, string $opensAt, string $closesAt, string $timezone): array
    {
        $plannedOpenAt = CarbonImmutable::parse($businessDate->toDateString().' '.$opensAt, $timezone);
        $plannedCloseAt = CarbonImmutable::parse($businessDate->toDateString().' '.$closesAt, $timezone);
        if ($plannedCloseAt->lessThanOrEqualTo($plannedOpenAt)) {
            $plannedCloseAt = $plannedCloseAt->addDay();
        }

        return [$plannedOpenAt, $plannedCloseAt];
    }
}
