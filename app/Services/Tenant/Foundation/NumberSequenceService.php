<?php

namespace App\Services\Tenant\Foundation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\NumberSequence;
use App\Models\Tenant\NumberSequenceAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class NumberSequenceService
{
    /** @return array{0: string, 1: NumberSequenceAllocation} */
    public function reserve(Branch $branch, string $key, string $consumerTable, string $defaultPrefix): array
    {
        $today = Carbon::today();
        $definition = NumberSequence::query()
            ->where('branch_id', $branch->id)
            ->where('sequence_key', $key)
            ->where('is_active', true)
            ->whereNull('period_starts_on')
            ->lockForUpdate()
            ->first();
        if (! $definition) {
            throw ValidationException::withMessages(['number_sequence' => ["The {$key} number sequence is not configured for this branch."]]);
        }
        $periodStart = $definition->period_type === 'daily' ? $today : $today->copy()
            ->startOfYear();
        $periodEnd = $definition->period_type === 'daily' ? $today : $today->copy()
            ->endOfYear();
        $sequence = NumberSequence::query()
            ->firstOrCreate([
                'branch_id' => $branch->id,
                'sequence_key' => $key,
                'period_starts_on' => $periodStart,
            ], [
                'company_id' => $definition->company_id,
                'display_name' => $definition->display_name,
                'prefix' => $definition->prefix,
                'suffix' => $definition->suffix,
                'period_type' => $definition->period_type,
                'period_ends_on' => $periodEnd,
                'next_number' => 1,
                'padding_length' => $definition->padding_length,
                'reset_per_branch' => true,
                'is_active' => true,
            ]);
        $sequence = NumberSequence::query()
            ->lockForUpdate()
            ->findOrFail($sequence->id);
        $number = $sequence->next_number;
        $paddedNumber = str_pad((string) $number, $sequence->padding_length, '0', STR_PAD_LEFT);
        $formatted = sprintf('%s-%s-%s', $sequence->prefix ?: $defaultPrefix, $today->format('Y'), $paddedNumber);
        $allocation = NumberSequenceAllocation::query()
            ->create([
                'number_sequence_id' => $sequence->id,
                'branch_id' => $branch->id,
                'sequence_key' => $key,
                'allocated_number' => $number,
                'formatted_number' => $formatted,
                'consumer_table' => $consumerTable,
                'status' => 'reserved',
                'reserved_at' => now(),
            ]);
        $sequence->increment('next_number');

        return [$formatted, $allocation];
    }

    public function use(NumberSequenceAllocation $allocation, int $consumerId, string $consumerUuid): void
    {
        $allocation->update([
            'consumer_id' => $consumerId,
            'consumer_uuid' => $consumerUuid,
            'status' => 'used',
            'used_at' => now(),
        ]);
    }
}
