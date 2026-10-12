<?php

namespace App\Policies\Tenant\Hr;

use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Branch;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftDefinition;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\TenantUserAccessService;

class TimekeepingPolicy
{
    public function __construct(private readonly TenantUserAccessService $access) {}

    public function manage(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'attendance.manage', $branch);
    }

    public function viewOwnShifts(User $user, Branch $branch): bool
    {
        return $this->access->canManage($user, 'shifts.view_own', $branch);
    }

    public function updateShiftDefinition(User $user, ShiftDefinition $definition, Branch $branch): bool
    {
        return $definition->company_id === $branch->company_id
            && $definition->branch_id === $branch->id
            && $this->manage($user, $branch);
    }

    public function updateShiftAssignment(User $user, ShiftAssignment $assignment, Branch $branch): bool
    {
        return $assignment->branch_id === $branch->id && $this->manage($user, $branch);
    }

    public function adjustAttendance(User $user, AttendanceRecord $record, Branch $branch): bool
    {
        return $record->branch_id === $branch->id && $this->manage($user, $branch);
    }
}
