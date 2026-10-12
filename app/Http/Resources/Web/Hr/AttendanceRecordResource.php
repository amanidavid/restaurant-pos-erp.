<?php

namespace App\Http\Resources\Web\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'attendance_date' => $this->attendance_date?->toDateString(),
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
            'clock_in_at' => $this->clock_in_at?->toIso8601String(),
            'clock_out_at' => $this->clock_out_at?->toIso8601String(),
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'status' => $this->status,
            'source' => $this->source,
            'recorded_by' => $this->when($this->relationLoaded('recordedBy') && $this->recordedBy, fn (): array => [
                'uuid' => $this->recordedBy->uuid,
                'display_name' => $this->recordedBy->display_name,
            ]),
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'adjustment' => $this->when($this->adjusted_at !== null, fn (): array => [
                'reason' => $this->adjustment_reason,
                'adjusted_at' => $this->adjusted_at?->toIso8601String(),
                'adjusted_by' => $this->relationLoaded('adjustedBy') && $this->adjustedBy ? [
                    'uuid' => $this->adjustedBy->uuid,
                    'display_name' => $this->adjustedBy->display_name,
                ] : null,
            ]),
        ];
    }
}
