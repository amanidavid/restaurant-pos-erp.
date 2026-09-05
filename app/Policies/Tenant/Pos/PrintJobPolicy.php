<?php

namespace App\Policies\Tenant\Pos;

use App\Models\Tenant\Branch;
use App\Models\Tenant\PrintJob;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class PrintJobPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function view(User $user, PrintJob $job, Branch $branch): bool
    {
        return $job->branch_id === $branch->id
            && $this->access->canManage($user, 'print_jobs.view', $branch);
    }
}
