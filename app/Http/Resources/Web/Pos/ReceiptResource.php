<?php
namespace App\Http\Resources\Web\Pos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ReceiptResource extends JsonResource { public function toArray(Request $request): array { return ['uuid' => $this->uuid, 'receipt_number' => $this->receipt_number, 'status' => $this->status, 'total_amount' => $this->total_amount, 'issued_at' => $this->issued_at, 'order' => $this->whenLoaded('order', fn () => ['uuid' => $this->order?->uuid, 'order_number' => $this->order?->order_number])]; } }
