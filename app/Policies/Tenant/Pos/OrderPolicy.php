<?php

namespace App\Policies\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Order;
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
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.create', $branch);
    }

    public function cancel(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'orders.cancel', $branch);
    }

    public function serve(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch);
    }

    public function close(User $user, Order $order, Branch $branch): bool
    {
        return $this->view($user, $order, $branch) && $this->access->canManage($user, 'payments.collect', $branch);
    }
}
