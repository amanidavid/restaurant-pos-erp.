<?php

namespace App\Services\Tenant\Kitchen;

use App\Models\Tenant\Branch;
use App\Models\Tenant\KitchenTicket;
use App\Models\Tenant\KitchenTicketItem;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Models\Tenant\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenProductionService
{
    public function start(KitchenTicket $ticket, KitchenTicketItem $item, Branch $branch, TenantUser $actor): KitchenTicket
    {
        return $this->changeItemStatus($ticket, $item, $branch, $actor, 'queued', 'preparing');
    }

    public function ready(KitchenTicket $ticket, KitchenTicketItem $item, Branch $branch, TenantUser $actor): KitchenTicket
    {
        return $this->changeItemStatus($ticket, $item, $branch, $actor, 'preparing', 'ready');
    }

    private function changeItemStatus(KitchenTicket $ticket, KitchenTicketItem $item, Branch $branch, TenantUser $actor, string $from, string $to): KitchenTicket
    {
        return DB::connection('tenant')->transaction(function () use ($ticket, $item, $branch, $actor, $from, $to): KitchenTicket {
            $ticket = KitchenTicket::query()->where('branch_id', $branch->id)->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $item = KitchenTicketItem::query()->where('kitchen_ticket_id', $ticket->id)->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->status !== $from) {
                throw ValidationException::withMessages(['item' => ["Only {$from} kitchen items can be marked {$to}."]]);
            }

            $orderItem = OrderItem::query()->whereKey($item->order_item_id)->lockForUpdate()->firstOrFail();
            $item->update(array_filter(['status' => $to, 'started_at' => $to === 'preparing' ? now() : null, 'ready_at' => $to === 'ready' ? now() : null]));
            $orderItem->update(['status' => $to]);
            $this->syncTicketStatus($ticket);
            $order = Order::query()->whereKey($ticket->order_id)->lockForUpdate()->firstOrFail();
            $this->syncOrderStatus($order, $actor);
            $this->outbox($ticket, $branch, $actor, $to);
            $this->orderOutbox($order, $branch, $actor, $to);

            return $ticket->fresh(['kitchenSection:id,uuid,name', 'order:id,uuid,order_number,status', 'items.orderItem:id,uuid']);
        });
    }

    private function syncTicketStatus(KitchenTicket $ticket): void
    {
        $statuses = $ticket->items()->pluck('status');
        $status = $statuses->every(fn (string $value): bool => in_array($value, ['ready', 'cancelled'], true)) ? 'ready' : ($statuses->contains('preparing') ? 'preparing' : 'open');
        $ticket->update(['status' => $status]);
    }

    private function syncOrderStatus(Order $order, TenantUser $actor): void
    {
        $statuses = KitchenTicketItem::query()->whereHas('kitchenTicket', fn ($query) => $query->where('order_id', $order->id))->pluck('status');
        $active = $statuses->filter(fn (string $status): bool => $status !== 'cancelled');
        $next = $active->isNotEmpty() && $active->every(fn (string $status): bool => $status === 'ready') ? 'ready' : ($active->contains('ready') ? 'partially_ready' : 'sent_to_kitchen');
        if ($order->status !== $next) {
            $from = $order->status;
            $order->update(['status' => $next]);
            $order->statusLogs()->create(['changed_by_tenant_user_id' => $actor->id, 'from_status' => $from, 'to_status' => $next, 'changed_at' => now()]);
        }
    }

    private function outbox(KitchenTicket $ticket, Branch $branch, TenantUser $actor, string $action): void
    {
        RealtimeEventOutbox::query()->create([
            'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'created_by_tenant_user_id' => $actor->id,
            'kitchen_ticket_id' => $ticket->id, 'event_type' => $action === 'ready' ? 'kitchen_ticket.ready' : 'kitchen_ticket.updated',
            'channel_name' => 'tenant.branch.'.$branch->uuid.'.kitchen-section.'.$ticket->kitchenSection()->value('uuid'),
            'visibility_scope' => 'kitchen_section',
            'payload' => ['kitchen_ticket_uuid' => $ticket->uuid, 'order_uuid' => $ticket->order()->value('uuid'), 'status' => $ticket->status],
            'idempotency_key' => 'kitchen-ticket-'.$action.':'.$ticket->uuid.':'.now()->format('Uu'), 'priority' => 6, 'status' => 'pending', 'available_at' => now(),
        ]);
    }

    private function orderOutbox(Order $order, Branch $branch, TenantUser $actor, string $action): void
    {
        RealtimeEventOutbox::query()->create([
            'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'created_by_tenant_user_id' => $actor->id,
            'order_id' => $order->id, 'event_type' => 'order.updated', 'channel_name' => 'tenant.branch.'.$branch->uuid.'.pos',
            'visibility_scope' => 'branch',
            'payload' => ['order_uuid' => $order->uuid, 'status' => $order->status, 'kitchen_action' => $action],
            'idempotency_key' => 'order-kitchen-'.$action.':'.$order->uuid.':'.now()->format('Uu'), 'priority' => 6, 'status' => 'pending', 'available_at' => now(),
        ]);
    }
}
