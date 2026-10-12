<?php

namespace App\Services\Tenant\Reservations;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\Customer;
use App\Models\Tenant\DiningTable;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Reservation;
use App\Models\Tenant\ReservationTable;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Models\Tenant\Refund;
use App\Models\Tenant\ShiftSession;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Foundation\NumberSequenceService;
use App\Services\Tenant\Pos\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly OrderService $orders,
    ) {}

    public function create(Branch $branch, TenantUser $actor, array $data): Reservation
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $actor, $data): Reservation {
            $reservedFrom = Carbon::parse($data['reserved_from']);
            $reservedTo = isset($data['reserved_to'])
                ? Carbon::parse($data['reserved_to'])
                : $reservedFrom->copy()->addHours(2);
            $tables = $this->lockedTables($branch, $data['dining_table_uuids']);

            if ($tables->sum('capacity') < $data['guest_count']) {
                throw ValidationException::withMessages([
                    'guest_count' => ['Selected table capacity is insufficient for the guest count.'],
                ]);
            }

            $hasConflict = ReservationTable::query()
                ->whereIn('dining_table_id', $tables->pluck('id'))
                ->whereHas('reservation', function ($query) use ($reservedFrom, $reservedTo): void {
                    $query->whereIn('status', ['pending', 'confirmed', 'seated'])
                        ->where('reserved_from', '<', $reservedTo)
                        ->where('reserved_to', '>', $reservedFrom);
                })
                ->exists();
            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'dining_table_uuids' => ['One or more selected tables are already reserved for this time.'],
                ]);
            }

            $customer = $this->customer($branch, $data['customer_uuid'] ?? null);
            $paymentMethod = $this->manualPaymentMethod($branch, $data['payment_method_uuid']);
            $shift = ShiftSession::query()
                ->where('branch_id', $branch->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();
            if (! $shift) {
                throw ValidationException::withMessages([
                    'shift' => ['Open a shift before recording a reservation payment.'],
                ]);
            }

            [$number, $sequenceAllocation] = $this->numbers->reserve($branch, 'reservation', 'reservations', 'RSV');
            $reservation = Reservation::query()->create([
                'branch_id' => $branch->id,
                'customer_id' => $customer?->id,
                'created_by_tenant_user_id' => $actor->id,
                'reservation_number' => $number,
                'customer_name_snapshot' => $data['customer_name'] ?? $customer?->name ?? 'Guest',
                'customer_phone_snapshot' => $data['customer_phone'] ?? $customer?->phone,
                'guest_count' => $data['guest_count'],
                'reserved_from' => $reservedFrom,
                'reserved_to' => $reservedTo,
                'status' => 'confirmed',
                'notes' => $data['notes'] ?? null,
            ]);

            $reservation->reservationTables()->createMany(
                $tables->map(fn (DiningTable $table): array => ['dining_table_id' => $table->id])->all()
            );
            $this->numbers->use($sequenceAllocation, $reservation->id, $reservation->uuid);

            Payment::query()->create([
                'reservation_id' => $reservation->id,
                'branch_id' => $branch->id,
                'shift_session_id' => $shift->id,
                'payment_method_id' => $paymentMethod->id,
                'received_by_tenant_user_id' => $actor->id,
                'amount' => $data['received_amount'],
                'change_amount' => 0,
                'currency_code' => Company::query()->whereKey($branch->company_id)->value('base_currency_code'),
                'status' => 'completed',
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->recordOutbox($reservation, $branch, $actor, 'created');

            return $this->details($reservation);
        });
    }

    public function seat(Reservation $reservation, Branch $branch, TenantUser $actor, array $data): Reservation
    {
        return DB::connection('tenant')->transaction(function () use ($reservation, $branch, $actor, $data): Reservation {
            $reservation = Reservation::query()
                ->where('branch_id', $branch->id)
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => ["A reservation in {$reservation->status} status cannot be seated."],
                ]);
            }

            $order = $this->orders->createForReservation($reservation, $branch, $actor, $data);
            $reservation->update(['status' => 'seated']);
            $this->recordOutbox($reservation, $branch, $actor, 'seated', $order->uuid);

            return $this->details($reservation);
        });
    }

    public function transition(Reservation $reservation, Branch $branch, TenantUser $actor, string $status, ?string $reason): Reservation
    {
        return DB::connection('tenant')->transaction(function () use ($reservation, $branch, $actor, $status, $reason): Reservation {
            $reservation = Reservation::query()
                ->where('branch_id', $branch->id)
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowed = [
                'confirmed' => ['pending'],
                'completed' => ['seated'],
                'cancelled' => ['pending', 'confirmed'],
                'no_show' => ['confirmed'],
            ];
            if (! in_array($reservation->status, $allowed[$status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["A reservation in {$reservation->status} status cannot be marked {$status}."],
                ]);
            }

            if ($status === 'cancelled') {
                $this->refundReservationPayments($reservation, $branch, $actor, $reason);
            }

            $reservation->update(['status' => $status]);
            $this->recordOutbox($reservation, $branch, $actor, $status);

            return $this->details($reservation);
        });
    }

    private function lockedTables(Branch $branch, array $tableUuids)
    {
        $uniqueUuids = collect($tableUuids)->unique()->values();
        $tables = DiningTable::query()
            ->where('branch_id', $branch->id)
            ->whereIn('uuid', $uniqueUuids)
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($tables->count() !== $uniqueUuids->count()) {
            throw ValidationException::withMessages([
                'dining_table_uuids' => ['Select active dining tables from the current branch.'],
            ]);
        }

        return $tables;
    }

    private function customer(Branch $branch, ?string $customerUuid): ?Customer
    {
        if (! $customerUuid) {
            return null;
        }

        $customer = Customer::query()
            ->where('company_id', $branch->company_id)
            ->where('uuid', $customerUuid)
            ->where('status', 'active')
            ->first();
        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_uuid' => ['Select an active customer from this company.'],
            ]);
        }

        return $customer;
    }

    private function manualPaymentMethod(Branch $branch, string $paymentMethodUuid): PaymentMethod
    {
        $method = PaymentMethod::query()
            ->where('company_id', $branch->company_id)
            ->where('uuid', $paymentMethodUuid)
            ->where('status', 'active')
            ->where('requires_provider', false)
            ->lockForUpdate()
            ->first();
        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method_uuid' => ['Select an active manual payment method.'],
            ]);
        }

        return $method;
    }

    private function refundReservationPayments(Reservation $reservation, Branch $branch, TenantUser $actor, ?string $reason): void
    {
        $payments = Payment::query()
            ->where('reservation_id', $reservation->id)
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $alreadyRefunded = (float) Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', 'completed')
                ->sum('amount');
            $amount = round((float) $payment->amount - $alreadyRefunded, 2);
            if ($amount <= 0) {
                continue;
            }

            [$number, $sequenceAllocation] = $this->numbers->reserve($branch, 'refund', 'refunds', 'REF');
            $refund = Refund::query()->create([
                'payment_id' => $payment->id,
                'branch_id' => $branch->id,
                'requested_by_tenant_user_id' => $actor->id,
                'approved_by_tenant_user_id' => $actor->id,
                'refund_number' => $number,
                'amount' => $amount,
                'status' => 'completed',
                'reason' => $reason ?? 'Reservation cancelled.',
                'requested_at' => now(),
                'approved_at' => now(),
                'completed_at' => now(),
            ]);
            $this->numbers->use($sequenceAllocation, $refund->id, $refund->uuid);
            $payment->update(['status' => $amount === (float) $payment->amount ? 'refunded' : 'partially_refunded']);
        }
    }

    private function recordOutbox(Reservation $reservation, Branch $branch, TenantUser $actor, string $action, ?string $orderUuid = null): void
    {
        RealtimeEventOutbox::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'created_by_tenant_user_id' => $actor->id,
            'reservation_id' => $reservation->id,
            'event_type' => 'reservation.'.$action,
            'channel_name' => 'tenant.branch.'.$branch->uuid.'.reservations',
            'visibility_scope' => 'branch',
            'payload' => [
                'branch_uuid' => $branch->uuid,
                'reservation_uuid' => $reservation->uuid,
                'reservation_number' => $reservation->reservation_number,
                'status' => $reservation->status,
                'order_uuid' => $orderUuid,
                'customer_name' => $reservation->customer_name_snapshot,
                'reserved_from' => $reservation->reserved_from?->toIso8601String(),
                'reserved_to' => $reservation->reserved_to?->toIso8601String(),
            ],
            'idempotency_key' => 'reservation-'.$action.':'.$reservation->uuid,
            'priority' => 6,
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }

    private function details(Reservation $reservation): Reservation
    {
        return $reservation->fresh([
            'customer:id,uuid,name,phone',
            'reservationTables.diningTable:id,uuid,name,capacity',
            'payments.paymentMethod:id,uuid,name,method_type',
            'payments.refunds:id,uuid,payment_id,refund_number,amount,status,completed_at',
            'orders:id,uuid,reservation_id,order_number,status,total_amount,paid_amount',
        ]);
    }
}
