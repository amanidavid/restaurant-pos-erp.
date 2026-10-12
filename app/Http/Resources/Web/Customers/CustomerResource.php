<?php

namespace App\Http\Resources\Web\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_number' => $this->tax_number,
            'status' => $this->status,
            'customer_group' => $this->whenLoaded('customerGroup', fn () => $this->customerGroup ? [
                'uuid' => $this->customerGroup->uuid,
                'name' => $this->customerGroup->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
