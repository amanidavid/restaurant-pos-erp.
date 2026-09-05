<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'job_type' => $this->job_type,
            'status' => $this->status,
            'attempt_count' => $this->attempt_count,
            'requested_at' => $this->requested_at,
            'printed_at' => $this->printed_at,
            'failed_reason' => $this->failed_reason,
            'receipt' => $this->whenLoaded('receipt', fn () => [
                'uuid' => $this->receipt?->uuid,
                'receipt_number' => $this->receipt?->receipt_number,
            ]),
            'print_device' => $this->whenLoaded('printDevice', fn () => [
                'uuid' => $this->printDevice?->uuid,
                'name' => $this->printDevice?->name,
            ]),
            'print_template' => $this->whenLoaded('printTemplate', fn () => [
                'uuid' => $this->printTemplate?->uuid,
                'name' => $this->printTemplate?->name,
            ]),
        ];
    }
}
