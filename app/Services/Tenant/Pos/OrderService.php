<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\AuditEvent;
use App\Models\Tenant\Branch;
use App\Models\Tenant\BranchMenuItem;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\Counter;
use App\Models\Tenant\Customer;
use App\Models\Tenant\DiningTable;
use App\Models\Tenant\KitchenTicket;
use App\Models\Tenant\KitchenTicketItem;
use App\Models\Tenant\ModifierOption;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentAllocation;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Models\Tenant\Reservation;
use App\Models\Tenant\SalesChannel;
use App\Models\Tenant\ServiceType;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Foundation\NumberSequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    public function create(Branch $branch, TenantUser $actor, array $data): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $data): Order {
                $businessDate = BusinessDate::query()
                    ->where('branch_id', $branch->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
                if (! $businessDate) {
                    throw ValidationException::withMessages(['business_date' => ['Open the business day before creating an order.']]);
                }
                [$number, $allocation] = $this->numbers->reserve($branch, 'order', 'orders', 'ORD');
                $order = Order::query()
                    ->create([
                        'branch_id' => $branch->id,
                        'business_date_id' => $businessDate->id,
                        'sales_channel_id' => $this->companyId(SalesChannel::class, $branch, $data['sales_channel_uuid'] ?? null),
                        'service_type_id' => $this->companyId(ServiceType::class, $branch, $data['service_type_uuid']),
                        'counter_id' => $this->branchId(Counter::class, $branch, $data['counter_uuid'] ?? null),
                        'dining_table_id' => $this->activeDiningTableId($branch, $data['dining_table_uuid'] ?? null),
                        'customer_id' => $this->companyId(Customer::class, $branch, $data['customer_uuid'] ?? null),
                        'opened_by_tenant_user_id' => $actor->id,
                        'order_number' => $number,
                        'status' => 'open',
                        'guest_count' => $data['guest_count'] ?? 1,
                        'opened_at' => now(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                foreach ($data['items'] as $item) {
                    $this->addItem($order, $branch, $item);
                }
                $this->recalculate($order);
                $this->log($order, $actor, null, 'open');
                $this->numbers->use($allocation, $order->id, $order->uuid);

                return $this->details($order);
            });
    }

    /**
     * Opens the POS order for a seated reservation. The reservation payment is
     * retained as its original financial record and is allocated as the order
     * total grows, never beyond the amount actually due.
     */
    public function createForReservation(Reservation $reservation, Branch $branch, TenantUser $actor, array $data): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($reservation, $branch, $actor, $data): Order {
                $reservation = Reservation::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($reservation->orders()
                    ->exists()) {
                    throw ValidationException::withMessages(['reservation' => ['This reservation already has an open or completed order.']]);
                }
                $businessDate = BusinessDate::query()
                    ->where('branch_id', $branch->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
                if (! $businessDate) {
                    throw ValidationException::withMessages(['business_date' => ['Open the business day before seating a reservation.']]);
                }
                $primaryTableId = $reservation->reservationTables()
                    ->orderBy('id')
                    ->value('dining_table_id');
                $primaryTable = DiningTable::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($primaryTableId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
                if (! $primaryTable) {
                    throw ValidationException::withMessages(['reservation' => ['The reservation no longer has an active dining table.']]);
                }
                [$number, $allocation] = $this->numbers->reserve($branch, 'order', 'orders', 'ORD');
                $order = Order::query()
                    ->create([
                        'branch_id' => $branch->id,
                        'business_date_id' => $businessDate->id,
                        'reservation_id' => $reservation->id,
                        'sales_channel_id' => $this->companyId(SalesChannel::class, $branch, $data['sales_channel_uuid'] ?? null),
                        'service_type_id' => $this->companyId(ServiceType::class, $branch, $data['service_type_uuid']),
                        'counter_id' => $this->branchId(Counter::class, $branch, $data['counter_uuid'] ?? null),
                        'dining_table_id' => $primaryTable->id,
                        'customer_id' => $reservation->customer_id,
                        'opened_by_tenant_user_id' => $actor->id,
                        'order_number' => $number,
                        'status' => 'open',
                        'guest_count' => $reservation->guest_count,
                        'opened_at' => now(),
                        'notes' => $data['notes'] ?? $reservation->notes,
                    ]);
                Payment::query()
                    ->where('reservation_id', $reservation->id)
                    ->where('status', 'completed')
                    ->whereNull('order_id')
                    ->lockForUpdate()
                    ->update(['order_id' => $order->id]);
                $this->recalculate($order);
                $this->log($order, $actor, null, 'open');
                $this->numbers->use($allocation, $order->id, $order->uuid);

                return $this->details($order);
            });
    }

    public function update(Order $order, Branch $branch, array $data): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $branch, $data): Order {
                $order = $this->editable($order, $branch);
                $values = collect($data)->except([
                    'service_type_uuid',
                    'counter_uuid',
                    'dining_table_uuid',
                    'customer_uuid',
                ])
                    ->all();
                if (array_key_exists('service_type_uuid', $data)) {
                    $values['service_type_id'] = $this->companyId(ServiceType::class, $branch, $data['service_type_uuid']);
                }
                if (array_key_exists('counter_uuid', $data)) {
                    $values['counter_id'] = $this->branchId(Counter::class, $branch, $data['counter_uuid']);
                }
                if (array_key_exists('dining_table_uuid', $data)) {
                    $values['dining_table_id'] = $this->activeDiningTableId($branch, $data['dining_table_uuid']);
                }
                if (array_key_exists('customer_uuid', $data)) {
                    $values['customer_id'] = $this->companyId(Customer::class, $branch, $data['customer_uuid']);
                }
                $order->update($values);

                return $this->details($order);
            });
    }

    public function add(Order $order, Branch $branch, array $data): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $branch, $data): Order {
                $order = $this->editable($order, $branch);
                foreach ($data['items'] as $item) {
                    $this->addItem($order, $branch, $item);
                }
                $this->recalculate($order);

                return $this->details($order);
            });
    }

    public function updateItem(Order $order, OrderItem $item, Branch $branch, array $data): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $item, $branch, $data): Order {
                $order = $this->editable($order, $branch);
                $item = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereKey($item->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($item->status !== 'new') {
                    throw ValidationException::withMessages(['item' => ['Only new items can be changed.']]);
                }
                $menu = BranchMenuItem::query()
                    ->with(['menuItem.taxCode', 'menuItem.modifierGroups.modifierGroup.options'])
                    ->where('branch_id', $branch->id)
                    ->whereKey($item->branch_menu_item_id)
                    ->lockForUpdate()
                    ->first();
                if (! $menu || ! $menu->menuItem) {
                    throw ValidationException::withMessages(['item' => ['The selected order item is no longer available.']]);
                }
                $quantity = (float) ($data['quantity'] ?? $item->quantity);
                if (array_key_exists('modifiers', $data)) {
                    $modifierRows = $data['modifiers'];
                    $optionModels = $this->modifierOptions($menu, $modifierRows);
                    $modifierTotal = $this->modifierTotal($modifierRows, $optionModels);
                    $item->modifiers()
                        ->delete();
                    $this->storeModifiers($item, $modifierRows, $optionModels, $quantity);
                } else {
                    $modifierTotal = (float) $item->modifiers()
                        ->get(['price_delta_snapshot', 'quantity'])
                        ->sum(fn ($modifier): float => (float) $modifier->price_delta_snapshot * (int) $modifier->quantity);
                }
                [$tax, $lineTotal] = $this->itemAmounts($item->unit_price_snapshot, $quantity, $modifierTotal, $menu);
                $item->update([
                    'quantity' => $quantity,
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $item->notes,
                    'tax_amount' => $tax,
                    'line_total' => $lineTotal,
                ]);
                $this->recalculate($order);

                return $this->details($order);
            });
    }

    public function voidItem(Order $order, OrderItem $item, Branch $branch, TenantUser $actor, string $reason): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $item, $branch, $actor, $reason): Order {
                $order = $this->editable($order, $branch);
                $item = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereKey($item->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($item->status !== 'new') {
                    throw ValidationException::withMessages(['item' => ['Only new items can be voided before kitchen dispatch.']]);
                }
                $item->update(['status' => 'voided']);
                $this->auditItemVoid($order, $item, $branch, $actor, $reason);
                $this->recalculate($order);

                return $this->details($order);
            });
    }

    public function serveItem(Order $order, OrderItem $item, Branch $branch, TenantUser $actor): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $item, $branch, $actor): Order {
                $order = Order::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $item = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereKey($item->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($item->status !== 'ready') {
                    throw ValidationException::withMessages(['item' => ['Only ready items can be marked as served.']]);
                }
                $item->update(['status' => 'served']);
                $this->markKitchenItemsServed(collect([$item->id]));
                $activeStatuses = $order->items()
                    ->whereNotIn('status', ['cancelled', 'voided'])
                    ->pluck('status');
                if ($activeStatuses->isNotEmpty() && $activeStatuses->every(fn (string $status): bool => $status === 'served')) {
                    $from = $order->status;
                    $order->update(['status' => 'served']);
                    $order->statusLogs()
                        ->create([
                            'changed_by_tenant_user_id' => $actor->id,
                            'from_status' => $from,
                            'to_status' => 'served',
                            'changed_at' => now(),
                        ]);
                }

                return $this->details($order);
            });
    }

    public function serveOrder(Order $order, Branch $branch, TenantUser $actor): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $branch, $actor): Order {
                $order = Order::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($order->status, ['ready', 'partially_ready'], true)) {
                    throw ValidationException::withMessages(['order' => ['Only an order with ready items can be served.']]);
                }
                $items = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereNotIn('status', ['cancelled', 'voided', 'served'])
                    ->lockForUpdate()
                    ->get();
                if ($items->isEmpty() || $items->contains(fn (OrderItem $item): bool => $item->status !== 'ready')) {
                    throw ValidationException::withMessages(['order' => ['All remaining active order items must be ready before serving the order.']]);
                }
                $itemIds = $items->pluck('id');
                $items->each(fn (OrderItem $item) => $item->update(['status' => 'served']));
                $this->markKitchenItemsServed($itemIds);
                $from = $order->status;
                $order->update(['status' => 'served']);
                $order->statusLogs()
                    ->create([
                        'changed_by_tenant_user_id' => $actor->id,
                        'from_status' => $from,
                        'to_status' => 'served',
                        'changed_at' => now(),
                    ]);
                RealtimeEventOutbox::query()
                    ->create([
                        'company_id' => $branch->company_id,
                        'branch_id' => $branch->id,
                        'created_by_tenant_user_id' => $actor->id,
                        'order_id' => $order->id,
                        'event_type' => 'order.updated',
                        'channel_name' => 'tenant.branch.'.$branch->uuid.'.pos',
                        'visibility_scope' => 'branch',
                        'payload' => [
                            'order_uuid' => $order->uuid,
                            'status' => 'served',
                        ],
                        'idempotency_key' => 'order-served:'.$order->uuid,
                        'priority' => 6,
                        'status' => 'pending',
                        'available_at' => now(),
                    ]);

                return $this->details($order);
            });
    }

    /**
     * Mark ready kitchen-ticket items as served and complete only their fully served tickets.
     *
     * @param  Collection<int, int>  $orderItemIds
     */
    private function markKitchenItemsServed(Collection $orderItemIds): void
    {
        $kitchenItems = KitchenTicketItem::query()
            ->whereIn('order_item_id', $orderItemIds)
            ->where('status', 'ready')
            ->lockForUpdate()
            ->get();
        if ($kitchenItems->isEmpty()) {
            return;
        }
        $ticketIds = $kitchenItems->pluck('kitchen_ticket_id')
            ->unique()
            ->values();
        $kitchenItems->each(fn (KitchenTicketItem $kitchenItem) => $kitchenItem->update(['status' => 'served']));
        KitchenTicket::query()
            ->whereIn('id', $ticketIds)
            ->lockForUpdate()
            ->get()
            ->each(function (KitchenTicket $ticket): void {
                $activeStatuses = $ticket->items()
                    ->where('status', '!=', 'cancelled')
                    ->pluck('status');
                if ($activeStatuses->isNotEmpty() && $activeStatuses->every(fn (string $status): bool => $status === 'served')) {
                    $ticket->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }
            });
    }

    public function transition(Order $order, Branch $branch, TenantUser $actor, string $status, ?string $reason): Order
    {
        return DB::connection('tenant')
            ->transaction(function () use ($order, $branch, $actor, $status, $reason): Order {
                $order = Order::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $allowed = [
                    'cancelled' => ['draft', 'open'],
                    'served' => ['open', 'sent_to_kitchen', 'ready'],
                    'closed' => ['served'],
                ];
                if (! in_array($order->status, $allowed[$status], true)) {
                    throw ValidationException::withMessages(['status' => ["An order in {$order->status} status cannot be changed to {$status}."]]);
                }
                if ($status === 'closed') {
                    $paidAmount = (float) PaymentAllocation::query()
                        ->where('order_id', $order->id)
                        ->whereHas('payment', fn ($query) => $query->where('status', 'completed'))
                        ->sum('amount');
                    $order->update(['paid_amount' => round($paidAmount, 2)]);
                    if (round($paidAmount, 2) !== round((float) $order->total_amount, 2)) {
                        throw ValidationException::withMessages(['payment' => ['The order must be fully paid before it can be closed.']]);
                    }
                }
                $from = $order->status;
                $order->update([
                    'status' => $status,
                    'closed_by_tenant_user_id' => $status === 'closed' ? $actor->id : null,
                    'closed_at' => $status === 'closed' ? now() : null,
                ]);
                $this->log($order, $actor, $from, $status, $reason);
                if ($status === 'closed') {
                    RealtimeEventOutbox::query()
                        ->create([
                            'company_id' => $branch->company_id,
                            'branch_id' => $branch->id,
                            'created_by_tenant_user_id' => $actor->id,
                            'order_id' => $order->id,
                            'event_type' => 'order.updated',
                            'channel_name' => 'tenant.branch.'.$branch->uuid.'.pos',
                            'visibility_scope' => 'branch',
                            'payload' => [
                                'order_uuid' => $order->uuid,
                                'status' => 'closed',
                            ],
                            'idempotency_key' => 'order-closed:'.$order->uuid,
                            'priority' => 6,
                            'status' => 'pending',
                            'available_at' => now(),
                        ]);
                }

                return $this->details($order);
            });
    }

    public function details(Order $order): Order
    {
        return $order->fresh([
            'salesChannel:id,uuid,name',
            'serviceType:id,uuid,name',
            'counter:id,uuid,name',
            'diningTable:id,uuid,name',
            'customer:id,uuid,name',
            'items.modifiers',
        ]);
    }

    private function addItem(Order $order, Branch $branch, array $data): void
    {
        $menu = BranchMenuItem::query()
            ->with(['menuItem.taxCode', 'menuItem.modifierGroups.modifierGroup.options'])
            ->where('branch_id', $branch->id)
            ->where('uuid', $data['branch_menu_item_uuid'])
            ->where('is_available', true)
            ->lockForUpdate()
            ->first();
        if (! $menu || ! $menu->menuItem?->is_active) {
            throw ValidationException::withMessages(['items' => ['A selected menu item is unavailable in this branch.']]);
        }
        $options = $data['modifiers'] ?? [];
        $optionModels = $this->modifierOptions($menu, $options);
        $quantity = (float) $data['quantity'];
        $modifierTotal = $this->modifierTotal($options, $optionModels);
        [$tax, $lineTotal] = $this->itemAmounts($menu->price, $quantity, $modifierTotal, $menu);
        $item = $order->items()
            ->create([
                'branch_menu_item_id' => $menu->id,
                'item_name_snapshot' => $menu->menuItem->name,
                'unit_price_snapshot' => $menu->price,
                'preparation_minutes_snapshot' => $menu->preparation_minutes,
                'quantity' => $data['quantity'],
                'tax_amount' => round($tax, 2),
                'line_total' => round($lineTotal, 2),
                'status' => 'new',
                'notes' => $data['notes'] ?? null,
            ]);
        $this->storeModifiers($item, $options, $optionModels, $quantity);
    }

    /**
     * @param  array<int, array{modifier_option_uuid: string, quantity: int}>  $rows
     * @return Collection<string, ModifierOption>
     */
    private function modifierOptions(BranchMenuItem $menu, array $rows): Collection
    {
        $optionUuids = collect($rows)->pluck('modifier_option_uuid')
            ->filter()
            ->unique()
            ->values();
        $optionModels = ModifierOption::query()
            ->whereIn('uuid', $optionUuids)
            ->where('is_active', true)
            ->lockForUpdate()
            ->get()
            ->keyBy('uuid');
        $allowedOptionIds = $menu->menuItem->modifierGroups->where('is_active', true)
            ->flatMap(fn ($link) => $link->modifierGroup?->options?->pluck('id') ?? [])
            ->all();
        if ($optionModels->count() !== $optionUuids->count() || $optionModels->contains(fn (ModifierOption $option): bool => ! in_array($option->id, $allowedOptionIds, true))) {
            throw ValidationException::withMessages(['modifiers' => ['One or more modifiers are not available for the selected menu item.']]);
        }

        return $optionModels;
    }

    /**
     * @param  array<int, array{modifier_option_uuid: string, quantity: int}>  $rows
     * @param  Collection<string, ModifierOption>  $optionModels
     */
    private function modifierTotal(array $rows, Collection $optionModels): float
    {
        return (float) collect($rows)->sum(fn (array $row): float => (float) $optionModels[$row['modifier_option_uuid']]->price_delta * (int) $row['quantity']);
    }

    /**
     * @param  array<int, array{modifier_option_uuid: string, quantity: int}>  $rows
     * @param  Collection<string, ModifierOption>  $optionModels
     */
    private function storeModifiers(OrderItem $item, array $rows, Collection $optionModels, float $itemQuantity): void
    {
        foreach ($rows as $row) {
            $option = $optionModels[$row['modifier_option_uuid']];
            $item->modifiers()
                ->create([
                    'modifier_option_id' => $option->id,
                    'modifier_name_snapshot' => $option->name,
                    'price_delta_snapshot' => $option->price_delta,
                    'quantity' => $row['quantity'],
                    'line_total' => round((float) $option->price_delta * (int) $row['quantity'] * $itemQuantity, 2),
                ]);
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function itemAmounts(string|float $unitPrice, float $quantity, float $modifierTotal, BranchMenuItem $menu): array
    {
        $base = ((float) $unitPrice + $modifierTotal) * $quantity;
        $taxCode = $menu->menuItem->taxCode;
        $tax = 0;

        if ($taxCode !== null) {
            $taxRate = (float) $taxCode->rate_percentage / 100;

            if ($taxCode->is_inclusive) {
                $tax = $base - $base / (1 + $taxRate);
            } else {
                $tax = $base * $taxRate;
            }
        }

        return [round($tax, 2), round($taxCode?->is_inclusive ? $base : $base + $tax, 2)];
    }

    private function recalculate(Order $order): void
    {
        $totals = $order->items()
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->selectRaw('COALESCE(SUM(line_total - tax_amount), 0) subtotal, COALESCE(SUM(tax_amount), 0) tax')
            ->first();
        $total = round((float) $totals->subtotal + (float) $totals->tax - (float) $order->discount_amount + (float) $order->service_charge_amount + (float) $order->tip_amount, 2);
        $order->update([
            'subtotal_amount' => $totals->subtotal,
            'tax_amount' => $totals->tax,
            'total_amount' => $total,
        ]);
        $this->allocateReservationPayments($order, $total);
    }

    private function allocateReservationPayments(Order $order, float $total): void
    {
        $allocated = (float) PaymentAllocation::query()
            ->where('order_id', $order->id)
            ->sum('amount');
        $remainingOrderBalance = max(0, round($total - $allocated, 2));
        if ($remainingOrderBalance > 0) {
            $payments = Payment::query()
                ->where('order_id', $order->id)
                ->whereNotNull('reservation_id')
                ->where('status', 'completed')
                ->lockForUpdate()
                ->get();
            foreach ($payments as $payment) {
                if ($remainingOrderBalance <= 0) {
                    break;
                }
                $paymentAllocated = (float) $payment->allocations()
                    ->sum('amount');
                $available = max(0, round((float) $payment->amount - $paymentAllocated, 2));
                $amount = min($available, $remainingOrderBalance);
                if ($amount <= 0) {
                    continue;
                }
                $allocation = $payment->allocations()
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->first();
                if ($allocation) {
                    $allocation->increment('amount', $amount);
                } else {
                    $payment->allocations()
                        ->create([
                            'order_id' => $order->id,
                            'amount' => $amount,
                        ]);
                }
                $remainingOrderBalance = round($remainingOrderBalance - $amount, 2);
            }
        }
        $order->update(['paid_amount' => PaymentAllocation::query()
            ->where('order_id', $order->id)
            ->whereHas('payment', fn ($query) => $query->where('status', 'completed'))
            ->sum('amount')]);
    }

    private function auditItemVoid(Order $order, OrderItem $item, Branch $branch, TenantUser $actor, string $reason): void
    {
        AuditEvent::query()
            ->create([
                'company_id' => $branch->company_id,
                'branch_id' => $branch->id,
                'tenant_user_id' => $actor->id,
                'order_id' => $order->id,
                'audit_area' => 'sales',
                'event_type' => 'order_item_voided',
                'severity' => 'warning',
                'reason' => $reason,
                'changed_fields' => ['status' => [
                    'from' => 'new',
                    'to' => 'voided',
                ]],
                'metadata' => [
                    'order_item_uuid' => $item->uuid,
                    'order_item_name' => $item->item_name_snapshot,
                    'order_item_quantity' => $item->quantity,
                ],
                'occurred_at' => now(),
            ]);
    }

    private function editable(Order $order, Branch $branch): Order
    {
        $order = Order::query()
            ->where('branch_id', $branch->id)
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
        if (! in_array($order->status, ['draft', 'open'], true)) {
            throw ValidationException::withMessages(['order' => ['Only draft or open orders can be changed.']]);
        }

        return $order;
    }

    private function companyId(string $model, Branch $branch, ?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }
        $id = $model::query()
            ->where('company_id', $branch->company_id)
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['reference' => ['A selected company record is unavailable.']]);
        }

        return (int) $id;
    }

    private function branchId(string $model, Branch $branch, ?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }
        $id = $model::query()
            ->where('branch_id', $branch->id)
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['reference' => ['A selected branch record is unavailable.']]);
        }

        return (int) $id;
    }

    private function activeDiningTableId(Branch $branch, ?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }
        $id = DiningTable::query()
            ->where('branch_id', $branch->id)
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('dining_area_id')
                    ->orWhereHas('diningArea', fn ($area) => $area->where('status', 'active'));
            })
            ->lockForUpdate()
            ->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['dining_table_uuid' => ['The selected dining table is inactive or its dining area is unavailable.']]);
        }

        return (int) $id;
    }

    private function log(Order $order, TenantUser $actor, ?string $from, string $to, ?string $reason = null): void
    {
        $order->statusLogs()
            ->create([
                'changed_by_tenant_user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'changed_at' => now(),
            ]);
    }
}
