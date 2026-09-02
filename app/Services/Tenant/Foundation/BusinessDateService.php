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
        return DB::connection('tenant')->transaction(function () use ($branch, $actor, $data): BusinessDate {
            $branch = Branch::query()->lockForUpdate()->findOrFail($branch->id);

            if ($branch->status !== 'active') {
                throw ValidationException::withMessages([
                    'branch' => ['Only an active branch can open a business day.'],
                ]);
            }

            if ($this->current($branch)) {
                throw ValidationException::withMessages([
                    'business_date' => ['Close the current business day before opening another one.'],
                ]);
            }

            $now = CarbonImmutable::now($branch->timezone);
            $businessDate = CarbonImmutable::parse($data['business_date'] ?? $now->toDateString(), $branch->timezone);
            $configuration = $branch->activeConfiguration()->firstOrFail();
            [$plannedOpenAt, $plannedCloseAt] = $this->plannedHours($businessDate, $configuration->business_day_opens_at, $configuration->business_day_closes_at, $branch->timezone);
            [$businessDayNumber, $allocation] = $this->allocateNumber($branch, $businessDate, $now);
            $openingFloat = $data['opening_float_amount'] ?? '0.00';

            $businessDay = BusinessDate::query()->create([
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
            ])->save();

            return $businessDay->load('branch');
        });
    }

    /** @param array<string, mixed> $data */
    public function close(Branch $branch, BusinessDate $businessDay, TenantUser $actor, array $data): BusinessDate
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $businessDay, $actor, $data): BusinessDate {
            $businessDay = BusinessDate::query()
                ->whereKey($businessDay->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($businessDay->status, ['open', 'closing'], true)) {
                throw ValidationException::withMessages([
                    'business_day' => ['This business day is already closed.'],
                ]);
            }

            $this->ensureCanClose($businessDay);

            $countedCash = $data['counted_cash_amount'];
            $expectedCash = $this->expectedCashAmount($businessDay);
            $variance = DB::connection('tenant')->selectOne(
                'select cast(? as numeric) - cast(? as numeric) as variance',
                [$countedCash, $expectedCash],
            )->variance;
            $now = CarbonImmutable::now($branch->timezone);

            $businessDay->forceFill([
                'status' => 'closed',
                'closed_by_id' => $actor->id,
                'closed_at' => $now,
                'expected_cash_amount' => $expectedCash,
                'counted_cash_amount' => $countedCash,
                'closing_variance_amount' => $variance,
                'closing_notes' => $data['closing_notes'] ?? null,
            ])->save();

            return $businessDay->refresh()->load('branch');
        });
    }

    private function ensureCanClose(BusinessDate $businessDay): void
    {
        $hasOpenOrders = DB::connection('tenant')->table('orders')
            ->where('business_date_id', $businessDay->id)
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->exists();
        $hasOpenShifts = DB::connection('tenant')->table('shift_sessions')
            ->where('business_date_id', $businessDay->id)
            ->whereIn('status', ['open', 'closing'])
            ->exists();
        $hasOpenReconciliations = DB::connection('tenant')->table('business_day_cash_reconciliations')
            ->where('business_date_id', $businessDay->id)
            ->whereIn('status', ['open', 'closing'])
            ->exists();

        if ($hasOpenOrders || $hasOpenShifts || $hasOpenReconciliations) {
            throw ValidationException::withMessages([
                'business_day' => ['Close open orders, shifts, and cash reconciliations before closing this business day.'],
            ]);
        }
    }

    private function expectedCashAmount(BusinessDate $businessDay): string
    {
        // Cash-control APIs will maintain reconciliation projections. Until then,
        // the opening float is the only cash amount established by this module.
        return (string) $businessDay->opening_float_amount;
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
            $definition = NumberSequence::query()->create([
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
            $sequence = NumberSequence::query()->create([
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

        $sequence = NumberSequence::query()->lockForUpdate()->findOrFail($sequence->id);
        $number = $sequence->next_number;
        $formatted = sprintf('%s-%s-%s', $sequence->prefix ?: 'BD', $businessDate->format('Ymd'), str_pad((string) $number, $sequence->padding_length, '0', STR_PAD_LEFT));

        $allocation = NumberSequenceAllocation::query()->create([
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
