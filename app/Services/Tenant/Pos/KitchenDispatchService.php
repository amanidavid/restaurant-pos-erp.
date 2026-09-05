<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\KitchenTicket;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Foundation\NumberSequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenDispatchService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly OrderService $orders,
    ) {}

    public function dispatch(Order $order, Branch $branch, TenantUser $actor): Order
    {
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $actor): Order {
            $order = Order::query()
                ->where('branch_id', $branch->id)
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'sent_to_kitchen') {
                return $this->orders->details($order);
            }

            if (! in_array($order->status, ['draft', 'open'], true)) {
                throw ValidationException::withMessages(['order' => ["An order in {$order->status} status cannot be sent to the kitchen."]]);
            }

            $items = OrderItem::query()
                ->with('branchMenuItem.kitchenSection:id,uuid,name')
                ->where('order_id', $order->id)
                ->where('status', 'new')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => ['Add at least one new order item before sending to the kitchen.']]);
            }

            $unroutedItems = $items->filter(fn (OrderItem $item): bool => ! $item->branchMenuItem?->kitchen_section_id);
            if ($unroutedItems->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => ['Every order item must have a kitchen section before dispatch.']]);
            }

            $fromStatus = $order->status;
            $items->groupBy(fn (OrderItem $item): int => $item->branchMenuItem->kitchen_section_id)
                ->sortKeys()
                ->each(function (Collection $sectionItems) use ($branch, $order, $actor): void {
                    $this->createTicket($sectionItems, $branch, $order, $actor);
                });

            $items->each(fn (OrderItem $item) => $item->update(['status' => 'sent']));
            $order->update(['status' => 'sent_to_kitchen']);
            $order->statusLogs()->create([
                'changed_by_tenant_user_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => 'sent_to_kitchen',
                'changed_at' => now(),
            ]);
            $this->recordOrderOutboxEvent($order, $branch, $actor);

            return $this->orders->details($order);
        });
    }

    /** @param Collection<int, OrderItem> $items */
    private function createTicket(Collection $items, Branch $branch, Order $order, TenantUser $actor): void
    {
        /** @var OrderItem $firstItem */
        $firstItem = $items->firstOrFail();
        $section = $firstItem->branchMenuItem->kitchenSection;
        [$ticketNumber, $allocation] = $this->numbers->reserve($branch, 'kitchen_ticket', 'kitchen_tickets', 'KIT');
        $ticket = KitchenTicket::query()->create([
            'branch_id' => $branch->id,
            'kitchen_section_id' => $section->id,
            'order_id' => $order->id,
            'ticket_number' => $ticketNumber,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        foreach ($items as $item) {
            $ticket->items()->create([
                'order_item_id' => $item->id,
                'item_name_snapshot' => $item->item_name_snapshot,
                'quantity' => $item->quantity,
                'status' => 'queued',
            ]);
        }

        $this->numbers->use($allocation, $ticket->id, $ticket->uuid);
        $this->recordTicketOutboxEvent($ticket, $branch, $order, $actor, $section->uuid, $items);
    }

    /** @param Collection<int, OrderItem> $items */
    private function recordTicketOutboxEvent(KitchenTicket $ticket, Branch $branch, Order $order, TenantUser $actor, string $sectionUuid, Collection $items): void
    {
        RealtimeEventOutbox::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'created_by_tenant_user_id' => $actor->id,
            'kitchen_ticket_id' => $ticket->id,
            'event_type' => 'kitchen_ticket.created',
            'channel_name' => 'tenant.branch.'.$branch->uuid.'.kitchen-section.'.$sectionUuid,
            'visibility_scope' => 'kitchen_section',
            'payload' => [
                'branch_uuid' => $branch->uuid,
                'kitchen_section_uuid' => $sectionUuid,
                'kitchen_ticket_uuid' => $ticket->uuid,
                'ticket_number' => $ticket->ticket_number,
                'order_uuid' => $order->uuid,
                'items' => $items->map(fn (OrderItem $item): array => [
                    'order_item_uuid' => $item->uuid,
                    'name' => $item->item_name_snapshot,
                    'quantity' => $item->quantity,
                ])->values()->all(),
            ],
            'idempotency_key' => 'kitchen-ticket-created:'.$ticket->uuid,
            'priority' => 7,
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }

    private function recordOrderOutboxEvent(Order $order, Branch $branch, TenantUser $actor): void
    {
        RealtimeEventOutbox::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'created_by_tenant_user_id' => $actor->id,
            'order_id' => $order->id,
            'event_type' => 'order.sent_to_kitchen',
            'channel_name' => 'tenant.branch.'.$branch->uuid.'.pos',
            'visibility_scope' => 'branch',
            'payload' => [
                'branch_uuid' => $branch->uuid,
                'order_uuid' => $order->uuid,
                'status' => $order->status,
            ],
            'idempotency_key' => 'order-sent-to-kitchen:'.$order->uuid,
            'priority' => 6,
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }
}
