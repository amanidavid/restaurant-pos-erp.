<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array { return ['uuid' => $this->uuid, 'amount' => $this->amount, 'change_amount' => $this->change_amount, 'currency_code' => $this->currency_code, 'status' => $this->status, 'paid_at' => $this->paid_at, 'notes' => $this->notes, 'payment_method' => $this->whenLoaded('paymentMethod', fn () => ['uuid' => $this->paymentMethod?->uuid, 'name' => $this->paymentMethod?->name, 'type' => $this->paymentMethod?->method_type])]; }
}
