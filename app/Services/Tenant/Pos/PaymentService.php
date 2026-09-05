<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\ShiftSession;
use App\Models\Tenant\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function collect(Order $order, Branch $branch, TenantUser $actor, array $data): Payment
    {
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $actor, $data): Payment {
            $order = Order::query()->where('branch_id', $branch->id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($order->status, ['draft', 'cancelled', 'closed'], true)) throw ValidationException::withMessages(['order' => ['This order cannot receive a payment.']]);
            $shift = ShiftSession::query()->where('branch_id', $branch->id)->where('status', 'open')->lockForUpdate()->first();
            if (! $shift) throw ValidationException::withMessages(['shift' => ['Open a shift before collecting payment.']]);
            $method = PaymentMethod::query()->where('company_id', $branch->company_id)->where('uuid', $data['payment_method_uuid'])->where('status', 'active')->lockForUpdate()->first();
            if (! $method || $method->requires_provider) throw ValidationException::withMessages(['payment_method' => ['Select an active manual payment method.']]);
            $paid = (float) $order->payments()->where('status', 'completed')->sum('amount') - (float) $order->payments()->where('status', 'completed')->sum('change_amount');
            $remaining = round((float) $order->total_amount - $paid, 2);
            if ($remaining <= 0) throw ValidationException::withMessages(['payment' => ['This order is already fully paid.']]);
            $received = (float) $data['received_amount'];
            if ($received > $remaining && ! ($method->method_type === 'cash' && $method->allows_change)) throw ValidationException::withMessages(['received_amount' => ['Only a cash method that allows change may exceed the remaining balance.']]);
            $allocated = min($received, $remaining);
            $payment = Payment::query()->create(['order_id' => $order->id, 'branch_id' => $branch->id, 'business_date_id' => $order->business_date_id, 'shift_session_id' => $shift->id, 'payment_method_id' => $method->id, 'received_by_tenant_user_id' => $actor->id, 'amount' => $received, 'change_amount' => round($received - $allocated, 2), 'currency_code' => Company::query()->whereKey($branch->company_id)->value('base_currency_code'), 'status' => 'completed', 'paid_at' => now(), 'notes' => $data['notes'] ?? null]);
            $payment->allocations()->create(['order_id' => $order->id, 'amount' => $allocated]);
            $order->update(['paid_amount' => round($paid + $allocated, 2)]);
            return $payment->fresh('paymentMethod:id,uuid,name,method_type');
        });
    }
}
