<?php

namespace App\Policies\Tenant\Finance;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class CashControlPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function viewAny(User $user, Branch $branch): bool { return $this->access->canManage($user, 'cash_reconciliations.view', $branch); }
    public function create(User $user, Branch $branch): bool { return $this->access->canManage($user, 'cash_reconciliations.open', $branch); }
    public function update(User $user, BusinessDayCashReconciliation $reconciliation, Branch $branch): bool { return $reconciliation->branch_id === $branch->id && $this->access->canManage($user, 'cash_reconciliations.count', $branch); }
    public function close(User $user, BusinessDayCashReconciliation $reconciliation, Branch $branch): bool { return $reconciliation->branch_id === $branch->id && $this->access->canManage($user, 'cash_reconciliations.close', $branch); }
    public function moveCash(User $user, Branch $branch): bool { return $this->access->canManage($user, 'cash_movements.create', $branch); }
}
