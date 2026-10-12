<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * @param array{
     *     payment_method_uuid: string,
     *     received_amount: string|int|float,
     *     cash_account_uuid?: string|null,
     *     notes?: string|null
     * } $data
     */
    public function collect(Order $order, Branch $branch, TenantUser $actor, array $data): Payment
    {
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $actor, $data): Payment {
            $order = Order::query()
                ->where('branch_id', $branch->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($order->status, ['draft', 'cancelled', 'closed'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['This order cannot receive a payment.'],
                ]);
            }

            $businessDay = BusinessDate::query()
                ->whereKey($order->business_date_id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if (! $businessDay || $businessDay->status !== 'open') {
                throw ValidationException::withMessages([
                    'business_day' => ['Payments can only be collected while this order\'s business day is open.'],
                ]);
            }

            $paymentMethod = $this->lockPaymentMethod($branch, $data['payment_method_uuid']);
            $cashAccount = $this->resolveCashAccount(
                $branch,
                $businessDay,
                $paymentMethod,
                $data['cash_account_uuid'] ?? null,
            );

            $paidAmount = $this->completedPaymentAmount($order);
            $remainingAmount = round((float) $order->total_amount - $paidAmount, 2);
            $receivedAmount = (float) $data['received_amount'];

            $this->ensurePaymentAmountIsAllowed($paymentMethod, $receivedAmount, $remainingAmount);

            $allocatedAmount = min($receivedAmount, $remainingAmount);
            $currencyCode = Company::query()
                ->whereKey($branch->company_id)
                ->value('base_currency_code');

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'branch_id' => $branch->id,
                'business_date_id' => $businessDay->id,
                'payment_method_id' => $paymentMethod->id,
                'received_by_tenant_user_id' => $actor->id,
                'amount' => $receivedAmount,
                'change_amount' => round($receivedAmount - $allocatedAmount, 2),
                'currency_code' => $currencyCode,
                'status' => 'completed',
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $payment->allocations()->create([
                'order_id' => $order->id,
                'amount' => $allocatedAmount,
            ]);

            if ($cashAccount !== null) {
                $this->recordCashSale($payment, $cashAccount, $actor, $allocatedAmount);
            }

            $order->update([
                'paid_amount' => round($paidAmount + $allocatedAmount, 2),
            ]);

            return $payment->fresh('paymentMethod:id,uuid,name,method_type');
        });
    }

    private function lockPaymentMethod(Branch $branch, string $paymentMethodUuid): PaymentMethod
    {
        $paymentMethod = PaymentMethod::query()
            ->where('company_id', $branch->company_id)
            ->where('uuid', $paymentMethodUuid)
            ->lockForUpdate()
            ->first();

        if ($paymentMethod === null) {
            throw ValidationException::withMessages([
                'payment_method_uuid' => ['The selected payment method is unavailable for this company.'],
            ]);
        }

        if ($paymentMethod->status !== 'active') {
            throw ValidationException::withMessages([
                'payment_method_uuid' => ['The selected payment method is inactive.'],
            ]);
        }

        if ($paymentMethod->requires_provider) {
            throw ValidationException::withMessages([
                'payment_method_uuid' => ["{$paymentMethod->name} requires a configured provider and cannot be collected manually."],
            ]);
        }

        return $paymentMethod;
    }

    private function resolveCashAccount(
        Branch $branch,
        BusinessDate $businessDay,
        PaymentMethod $paymentMethod,
        ?string $cashAccountUuid,
    ): ?CashAccount {
        if ($paymentMethod->method_type !== 'cash') {
            return null;
        }

        if ($cashAccountUuid === null) {
            throw ValidationException::withMessages([
                'cash_account_uuid' => ['Select the cash account receiving this payment.'],
            ]);
        }

        $cashAccount = CashAccount::query()
            ->where('uuid', $cashAccountUuid)
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($cashAccount === null) {
            throw ValidationException::withMessages([
                'cash_account_uuid' => ['The selected cash account is unavailable for this branch.'],
            ]);
        }

        if ($cashAccount->requires_reconciliation) {
            $hasOpenReconciliation = BusinessDayCashReconciliation::query()
                ->where('business_date_id', $businessDay->id)
                ->where('cash_account_id', $cashAccount->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if (! $hasOpenReconciliation) {
                throw ValidationException::withMessages([
                    'cash_account_uuid' => ['Open the cash reconciliation for this account before collecting a cash payment.'],
                ]);
            }
        }

        return $cashAccount;
    }

    private function completedPaymentAmount(Order $order): float
    {
        $paidAmount = $order->payments()
            ->where('status', 'completed')
            ->selectRaw('COALESCE(SUM(amount), 0) - COALESCE(SUM(change_amount), 0) AS paid_amount')
            ->value('paid_amount');

        return (float) $paidAmount;
    }

    private function ensurePaymentAmountIsAllowed(
        PaymentMethod $paymentMethod,
        float $receivedAmount,
        float $remainingAmount,
    ): void {
        if ($remainingAmount <= 0) {
            throw ValidationException::withMessages([
                'payment' => ['This order is already fully paid.'],
            ]);
        }

        $allowsChange = $paymentMethod->method_type === 'cash' && $paymentMethod->allows_change;

        if ($receivedAmount > $remainingAmount && ! $allowsChange) {
            throw ValidationException::withMessages([
                'received_amount' => ['Only a cash method that allows change may exceed the remaining balance.'],
            ]);
        }
    }

    private function recordCashSale(
        Payment $payment,
        CashAccount $cashAccount,
        TenantUser $actor,
        float $allocatedAmount,
    ): void {
        CashMovement::query()->create([
            'cash_account_id' => $cashAccount->id,
            'branch_id' => $payment->branch_id,
            'business_date_id' => $payment->business_date_id,
            'payment_id' => $payment->id,
            'performed_by_tenant_user_id' => $actor->id,
            'movement_reason' => 'cash_sale',
            'amount' => $allocatedAmount,
            'moved_at' => now(),
            'notes' => $payment->notes,
        ]);
    }
}
