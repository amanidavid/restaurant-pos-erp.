<?php

namespace App\Http\Resources\Web\Reservations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'reservation_number' => $this->reservation_number,
            'status' => $this->status,
            'customer' => [
                'uuid' => $this->customer?->uuid,
                'name' => $this->customer_name_snapshot,
                'phone' => $this->customer_phone_snapshot,
            ],
            'guest_count' => $this->guest_count,
            'reserved_from' => $this->reserved_from,
            'reserved_to' => $this->reserved_to,
            'notes' => $this->notes,
            'tables' => $this->whenLoaded('reservationTables', fn () => $this->reservationTables
                ->map(fn ($reservationTable) => [
                    'uuid' => $reservationTable->diningTable?->uuid,
                    'name' => $reservationTable->diningTable?->name,
                    'capacity' => $reservationTable->diningTable?->capacity,
                ])
                ->values()),
            'payment' => $this->whenLoaded('payments', function () {
                $payment = $this->payments->first();

                return $payment ? [
                    'uuid' => $payment->uuid,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at,
                    'refund' => $payment->relationLoaded('refunds') && $payment->refunds->isNotEmpty() ? [
                        'uuid' => $payment->refunds->first()->uuid,
                        'refund_number' => $payment->refunds->first()->refund_number,
                        'amount' => $payment->refunds->first()->amount,
                        'status' => $payment->refunds->first()->status,
                        'completed_at' => $payment->refunds->first()->completed_at,
                    ] : null,
                    'method' => $payment->paymentMethod ? [
                        'uuid' => $payment->paymentMethod->uuid,
                        'name' => $payment->paymentMethod->name,
                        'type' => $payment->paymentMethod->method_type,
                    ] : null,
                ] : null;
            }),
            'order' => $this->whenLoaded('orders', function () {
                $order = $this->orders->first();

                return $order ? [
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'paid_amount' => $order->paid_amount,
                ] : null;
            }),
        ];
    }
}
