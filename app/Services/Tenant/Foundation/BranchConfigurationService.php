<?php

namespace App\Services\Tenant\Foundation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BranchConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchConfigurationService
{
    public function current(Branch $branch): BranchConfiguration
    {
        return $branch->activeConfiguration()->first()
            ?? $branch->configurations()->orderByDesc('version')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): BranchConfiguration
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $data): BranchConfiguration {
            $current = $branch->configurations()
                ->where('is_active', true)
                ->lockForUpdate()
                ->orderByDesc('version')
                ->first();

            $effectiveFrom = Carbon::parse((string) $data['effective_from'])->toDateString();

            if ($current && $this->hasSameValues($current, $data, $effectiveFrom)) {
                return $current->refresh();
            }

            if ($current && $effectiveFrom <= $current->effective_from->toDateString()) {
                throw ValidationException::withMessages([
                    'effective_from' => ['Effective date must be after the current active configuration date.'],
                ]);
            }

            if ($current) {
                $current->forceFill([
                    'effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString(),
                    'is_active' => false,
                ])->save();
            }

            $nextVersion = ((int) $branch->configurations()->max('version')) + 1;

            return $branch->configurations()->create([
                'version' => $nextVersion,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'business_day_opens_at' => $data['business_day_opens_at'].':00',
                'business_day_closes_at' => $data['business_day_closes_at'].':00',
                'allow_negative_stock' => (bool) $data['allow_negative_stock'],
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasSameValues(BranchConfiguration $configuration, array $data, string $effectiveFrom): bool
    {
        return $configuration->effective_from->toDateString() === $effectiveFrom
            && substr((string) $configuration->business_day_opens_at, 0, 5) === $data['business_day_opens_at']
            && substr((string) $configuration->business_day_closes_at, 0, 5) === $data['business_day_closes_at']
            && (bool) $configuration->allow_negative_stock === (bool) $data['allow_negative_stock'];
    }
}
