<?php

namespace App\Services\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BranchMenuItem;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\Counter;
use App\Models\Tenant\Customer;
use App\Models\Tenant\DiningTable;
use App\Models\Tenant\ModifierOption;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\SalesChannel;
use App\Models\Tenant\ServiceType;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Foundation\NumberSequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly NumberSequenceService $numbers) {}

    public function create(Branch $branch, TenantUser $actor, array $data): Order
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $actor, $data): Order {
            $businessDate = BusinessDate::query()->where('branch_id', $branch->id)->where('status', 'open')->lockForUpdate()->first();
            if (! $businessDate) {
                throw ValidationException::withMessages(['business_date' => ['Open the business day before creating an order.']]);
            }

            [$number, $allocation] = $this->numbers->reserve($branch, 'order', 'orders', 'ORD');
            $order = Order::query()->create([
                'branch_id' => $branch->id,
                'business_date_id' => $businessDate->id,
                'sales_channel_id' => $this->companyId(SalesChannel::class, $branch, $data['sales_channel_uuid']),
                'service_type_id' => $this->companyId(ServiceType::class, $branch, $data['service_type_uuid']),
                'counter_id' => $this->branchId(Counter::class, $branch, $data['counter_uuid'] ?? null),
                'dining_table_id' => $this->branchId(DiningTable::class, $branch, $data['dining_table_uuid'] ?? null),
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

    public function update(Order $order, Branch $branch, array $data): Order
    {
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $data): Order {
            $order = $this->editable($order, $branch);
            $values = collect($data)->except(['service_type_uuid', 'counter_uuid', 'dining_table_uuid', 'customer_uuid'])->all();
            if (array_key_exists('service_type_uuid', $data)) {
                $values['service_type_id'] = $this->companyId(ServiceType::class, $branch, $data['service_type_uuid']);
            }
            if (array_key_exists('counter_uuid', $data)) {
                $values['counter_id'] = $this->branchId(Counter::class, $branch, $data['counter_uuid']);
            }
            if (array_key_exists('dining_table_uuid', $data)) {
                $values['dining_table_id'] = $this->branchId(DiningTable::class, $branch, $data['dining_table_uuid']);
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
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $data): Order {
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
        return DB::connection('tenant')->transaction(function () use ($order, $item, $branch, $data): Order {
            $order = $this->editable($order, $branch);
            $item = OrderItem::query()->where('order_id', $order->id)->whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->status !== 'new') {
                throw ValidationException::withMessages(['item' => ['Only new items can be changed.']]);
            }
            $item->delete();
            $this->addItem($order, $branch, $data);
            $this->recalculate($order);

            return $this->details($order);
        });
    }

    public function transition(Order $order, Branch $branch, TenantUser $actor, string $status, ?string $reason): Order
    {
        return DB::connection('tenant')->transaction(function () use ($order, $branch, $actor, $status, $reason): Order {
            $order = Order::query()->where('branch_id', $branch->id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $allowed = ['cancelled' => ['draft', 'open'], 'served' => ['open', 'sent_to_kitchen', 'ready'], 'closed' => ['served']];
            if (! in_array($order->status, $allowed[$status], true)) {
                throw ValidationException::withMessages(['status' => ["An order in {$order->status} status cannot be changed to {$status}."]]);
            }
            if ($status === 'closed' && (float) $order->paid_amount < (float) $order->total_amount) {
                throw ValidationException::withMessages(['payment' => ['The order must be fully paid before it can be closed.']]);
            }
            $from = $order->status;
            $order->update(['status' => $status, 'closed_by_tenant_user_id' => $status === 'closed' ? $actor->id : null, 'closed_at' => $status === 'closed' ? now() : null]);
            $this->log($order, $actor, $from, $status, $reason);

            return $this->details($order);
        });
    }

    public function details(Order $order): Order
    {
        return $order->fresh(['salesChannel:id,uuid,name', 'serviceType:id,uuid,name', 'counter:id,uuid,name', 'diningTable:id,uuid,name', 'customer:id,uuid,name', 'items.modifiers']);
    }

    private function addItem(Order $order, Branch $branch, array $data): void
    {
        $menu = BranchMenuItem::query()->with(['menuItem.taxCode', 'menuItem.modifierGroups.modifierGroup.options'])->where('branch_id', $branch->id)->where('uuid', $data['branch_menu_item_uuid'])->where('is_available', true)->lockForUpdate()->first();
        if (! $menu || ! $menu->menuItem?->is_active) {
            throw ValidationException::withMessages(['items' => ['A selected menu item is unavailable in this branch.']]);
        }
        $options = collect($data['modifiers'] ?? []);
        $optionModels = ModifierOption::query()->whereIn('uuid', $options->pluck('modifier_option_uuid'))->where('is_active', true)->get()->keyBy('uuid');
        $allowedOptionIds = $menu->menuItem->modifierGroups->where('is_active', true)->flatMap(fn ($link) => $link->modifierGroup?->options?->pluck('id') ?? [])->all();
        if ($optionModels->count() !== $options->pluck('modifier_option_uuid')->unique()->count() || $optionModels->contains(fn ($option) => ! in_array($option->id, $allowedOptionIds, true))) {
            throw ValidationException::withMessages(['items' => ['One or more modifiers are not available for the selected menu item.']]);
        }
        $quantity = (float) $data['quantity'];
        $modifierTotal = $options->sum(fn ($row) => (float) $optionModels[$row['modifier_option_uuid']]->price_delta * (int) $row['quantity']);
        $base = ((float) $menu->price + $modifierTotal) * $quantity;
        $taxCode = $menu->menuItem->taxCode;
        $tax = $taxCode ? ($taxCode->is_inclusive ? $base - ($base / (1 + ((float) $taxCode->rate_percentage / 100))) : $base * ((float) $taxCode->rate_percentage / 100)) : 0;
        $lineTotal = $taxCode?->is_inclusive ? $base : $base + $tax;
        $item = $order->items()->create(['branch_menu_item_id' => $menu->id, 'item_name_snapshot' => $menu->menuItem->name, 'unit_price_snapshot' => $menu->price, 'preparation_minutes_snapshot' => $menu->preparation_minutes, 'quantity' => $data['quantity'], 'tax_amount' => round($tax, 2), 'line_total' => round($lineTotal, 2), 'status' => 'new', 'notes' => $data['notes'] ?? null]);
        foreach ($options as $row) {
            $option = $optionModels[$row['modifier_option_uuid']];
            $item->modifiers()->create(['modifier_option_id' => $option->id, 'modifier_name_snapshot' => $option->name, 'price_delta_snapshot' => $option->price_delta, 'quantity' => $row['quantity'], 'line_total' => round((float) $option->price_delta * (int) $row['quantity'] * $quantity, 2)]);
        }
    }

    private function recalculate(Order $order): void
    {
        $totals = $order->items()->whereNotIn('status', ['cancelled', 'voided'])->selectRaw('COALESCE(SUM(line_total - tax_amount), 0) subtotal, COALESCE(SUM(tax_amount), 0) tax')->first();
        $order->update(['subtotal_amount' => $totals->subtotal, 'tax_amount' => $totals->tax, 'total_amount' => round((float) $totals->subtotal + (float) $totals->tax - (float) $order->discount_amount + (float) $order->service_charge_amount + (float) $order->tip_amount, 2)]);
    }

    private function editable(Order $order, Branch $branch): Order
    {
        $order = Order::query()->where('branch_id', $branch->id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
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
        $id = $model::query()->where('company_id', $branch->company_id)->where('uuid', $uuid)->where('status', 'active')->value('id');
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
        $id = $model::query()->where('branch_id', $branch->id)->where('uuid', $uuid)->where('status', 'active')->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['reference' => ['A selected branch record is unavailable.']]);
        }

        return (int) $id;
    }

    private function log(Order $order, TenantUser $actor, ?string $from, string $to, ?string $reason = null): void
    {
        $order->statusLogs()->create(['changed_by_tenant_user_id' => $actor->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'changed_at' => now()]);
    }
}
