<?php

namespace App\Policies\Tenant\Kitchen;

use App\Models\Tenant\Branch;
use App\Models\Tenant\KitchenTicket;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class KitchenTicketPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool { return $this->access->canManage($user, 'kitchen.view', $branch); }
    public function view(User $user, KitchenTicket $ticket, Branch $branch): bool { return $ticket->branch_id === $branch->id && $this->viewAny($user, $branch); }
    public function update(User $user, KitchenTicket $ticket, Branch $branch): bool { return $this->view($user, $ticket, $branch) && $this->access->canManage($user, 'kitchen.update', $branch); }
}
