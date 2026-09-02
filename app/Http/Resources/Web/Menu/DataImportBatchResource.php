<?php

namespace App\Http\Resources\Web\Menu;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'import_area' => $this->import_area,
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'successful_rows' => $this->successful_rows,
            'failed_rows' => $this->failed_rows,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'summary' => $this->summary,
        ];
    }
}
