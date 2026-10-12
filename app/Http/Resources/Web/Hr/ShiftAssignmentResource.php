<?php

namespace App\Http\Resources\Web\Hr;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduleState = $this->scheduleState();

        return [
            'uuid' => $this->uuid,
            'staff' => $this->whenLoaded('tenantUser', fn (): array => [
                'uuid' => $this->tenantUser->uuid,
                'display_name' => $this->tenantUser->display_name,
                'employee_number' => $this->tenantUser->employee_number,
            ]),
            'shift_definition' => $this->whenLoaded('shiftDefinition', fn (): array => [
                'uuid' => $this->shiftDefinition->uuid,
                'name' => $this->shiftDefinition->name,
                'starts_at' => $this->shiftDefinition->starts_at,
                'ends_at' => $this->shiftDefinition->ends_at,
                'crosses_midnight' => $this->shiftDefinition->crosses_midnight,
            ]),
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            // `enabled` is a manager-controlled setting; `schedule_state` is
            // derived from the selected branch's current date.
            'enabled' => $this->status === 'active',
            'schedule_state' => $scheduleState,
        ];
    }

    private function scheduleState(): string
    {
        if ($this->status !== 'active') {
            return 'disabled';
        }

        $timezone = $this->relationLoaded('branch') && $this->branch
            ? $this->branch->timezone
            : config('app.timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $validFrom = $this->valid_from->toDateString();
        $validTo = $this->valid_to?->toDateString();

        if ($validFrom > $today) {
            return 'upcoming';
        }

        if ($validTo !== null && $validTo < $today) {
            return 'ended';
        }

        return 'current';
    }
}
