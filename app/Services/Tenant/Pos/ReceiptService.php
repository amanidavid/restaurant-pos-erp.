<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\AuditEvent;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Order;
use App\Models\Tenant\PaymentAllocation;
use App\Models\Tenant\Receipt;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Foundation\NumberSequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    public function issue(Order $order, Branch $branch, TenantUser $actor): Receipt
    {
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $actor): Receipt {
            $order = Order::query()
                ->where('branch_id', $branch->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $paidAmount = (float) PaymentAllocation::query()
                ->where('order_id', $order->id)
                ->whereHas('payment', fn ($query) => $query->where('status', 'completed'))
                ->sum('amount');

            if (round($paidAmount, 2) !== round((float) $order->total_amount, 2)) {
                throw ValidationException::withMessages([
                    'payment' => ['The order must be fully paid before issuing a receipt.'],
                ]);
            }

            $order->update(['paid_amount' => number_format($paidAmount, 2, '.', '')]);

            $existing = Receipt::query()
                ->where('order_id', $order->id)
                ->where('status', 'issued')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing->load('order:id,uuid,order_number');
            }

            [$number, $allocation] = $this->numbers->reserve($branch, 'receipt', 'receipts', 'REC');

            $receipt = Receipt::query()->create([
                'order_id' => $order->id,
                'number_sequence_id' => $allocation->number_sequence_id,
                'issued_by_tenant_user_id' => $actor->id,
                'receipt_number' => $number,
                'status' => 'issued',
                'total_amount' => $order->total_amount,
                'issued_at' => now(),
            ]);

            $this->numbers->use($allocation, $receipt->id, $receipt->uuid);

            PaymentAllocation::query()
                ->where('order_id', $order->id)
                ->whereNull('receipt_id')
                ->whereHas('payment', fn ($query) => $query->where('status', 'completed'))
                ->update(['receipt_id' => $receipt->id]);

            return $receipt->load('order:id,uuid,order_number');
        });
    }

    public function void(Receipt $receipt, Branch $branch, TenantUser $actor, string $reason): Receipt
    {
        return DB::connection('tenant')->transaction(function () use ($receipt, $branch, $actor, $reason): Receipt {
            $receipt = Receipt::query()
                ->whereKey($receipt->id)
                ->whereHas('order', fn ($query) => $query->where('branch_id', $branch->id))
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->status !== 'issued') {
                throw ValidationException::withMessages([
                    'receipt' => ['Only an issued receipt can be voided.'],
                ]);
            }

            $receipt->update(['status' => 'voided']);

            AuditEvent::query()->create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'tenant_user_id' => $actor->id,
                'order_id' => $receipt->order_id,
                'audit_area' => 'sales',
                'event_type' => 'receipt_voided',
                'severity' => 'warning',
                'reason' => $reason,
                'metadata' => [
                    'receipt_uuid' => $receipt->uuid,
                    'receipt_number' => $receipt->receipt_number,
                ],
                'occurred_at' => now(),
            ]);

            return $receipt->load('order:id,uuid,order_number');
        });
    }
}
