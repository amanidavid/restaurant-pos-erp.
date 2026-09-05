<?php

namespace App\Policies\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Order;
use App\Models\Tenant\Receipt;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class OrderPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'orders.view', $branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'orders.create', $branch);
    }

    public function view(User $user, Order $order, Branch $branch): bool
    {
        return $order->branch_id === $branch->id && $this->viewAny($user, $branch);
    }

    public function update(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.update', $branch);
    }

    public function cancel(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.cancel', $branch);
    }

    public function voidItem(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.void_item', $branch);
    }

    public function sendToKitchen(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.send_to_kitchen', $branch);
    }

    public function collectPayment(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'payments.create', $branch);
    }
    public function issueReceipt(User $user, Order $order, Branch $branch): bool { return $this->view($user, $order, $branch) && $this->access->canManage($user, 'receipts.issue', $branch); }
    public function viewReceipt(User $user, Receipt $receipt, Branch $branch): bool { return $receipt->order()->where('branch_id', $branch->id)->exists() && $this->access->canManage($user, 'receipts.view', $branch); }
    public function voidReceipt(User $user, Receipt $receipt, Branch $branch): bool { return $this->viewReceipt($user, $receipt, $branch) && $this->access->canManage($user, 'receipts.void', $branch); }

    public function serve(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.serve', $branch);
    }

    public function close(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.close', $branch);
    }
}
