<?php

namespace App\Policies\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Receipt;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class ReceiptPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function view(User $user, Receipt $receipt, Branch $branch): bool
    {
        return $receipt->order()->where('branch_id', $branch->id)->exists()
            && $this->access->canManage($user, 'receipts.view', $branch);
    }

    public function void(User $user, Receipt $receipt, Branch $branch): bool
    {
        return $this->view($user, $receipt, $branch)
            && $this->access->canManage($user, 'receipts.void', $branch);
    }

    public function print(User $user, Receipt $receipt, Branch $branch): bool
    {
        return $this->view($user, $receipt, $branch)
            && $this->access->canManage($user, 'receipts.print', $branch);
    }
}
