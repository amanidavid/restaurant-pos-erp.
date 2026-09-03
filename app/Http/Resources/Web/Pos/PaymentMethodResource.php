<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'method_type' => $this->method_type,
            'requires_provider' => (bool) $this->requires_provider,
            'allows_change' => (bool) $this->allows_change,
            'status' => $this->status,
        ];
    }
}
