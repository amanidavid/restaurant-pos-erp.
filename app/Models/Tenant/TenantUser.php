<?php

namespace App\Models\Tenant;

use App\Models\Landlord\User as LandlordUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class TenantUser extends TenantModel implements HasMedia
{
    use HasRoles;
    use InteractsWithMedia;

    protected $guard_name = 'tenant_api';

    public function landlordIdentity(): BelongsTo
    {
        return $this->belongsTo(LandlordUser::class, 'landlord_user_uuid', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    public function branchAssignments(): HasMany
    {
        return $this->hasMany(TenantUserBranch::class);
    }

    public function departmentAccess(): HasMany
    {
        return $this->hasMany(UserDepartmentAccess::class);
    }

    public function branchEmployments(): HasMany
    {
        return $this->hasMany(TenantUserBranchEmployment::class);
    }

    public function jobHistories(): HasMany
    {
        return $this->hasMany(TenantUserJobHistory::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function approvedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by_tenant_user_id');
    }

    public function employeeEarnings(): HasMany
    {
        return $this->hasMany(EmployeeEarning::class);
    }

    public function employeeDeductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function statutoryProfiles(): HasMany
    {
        return $this->hasMany(EmployeeStatutoryProfile::class);
    }

    public function employeeLoans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function payrollRunsProcessed(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'processed_by_tenant_user_id');
    }

    public function payrollRunEmployees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function approvalRequestsRequested(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'requested_by_tenant_user_id');
    }

    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'decided_by_tenant_user_id');
    }

    public function notificationMessagesCreated(): HasMany
    {
        return $this->hasMany(NotificationMessage::class, 'created_by_tenant_user_id');
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function openedBusinessDates(): HasMany
    {
        return $this->hasMany(BusinessDate::class, 'opened_by_id');
    }

    public function closedBusinessDates(): HasMany
    {
        return $this->hasMany(BusinessDate::class, 'closed_by_id');
    }

    public function openedShiftSessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class, 'opened_by_tenant_user_id');
    }

    public function closedShiftSessions(): HasMany
    {
        return $this->hasMany(ShiftSession::class, 'closed_by_tenant_user_id');
    }

    public function openedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'opened_by_tenant_user_id');
    }

    public function closedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'closed_by_tenant_user_id');
    }

    public function paymentAttemptsInitiated(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class, 'initiated_by_tenant_user_id');
    }

    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by_tenant_user_id');
    }

    public function refundsRequested(): HasMany
    {
        return $this->hasMany(Refund::class, 'requested_by_tenant_user_id');
    }

    public function refundsApproved(): HasMany
    {
        return $this->hasMany(Refund::class, 'approved_by_tenant_user_id');
    }

    public function reservationsCreated(): HasMany
    {
        return $this->hasMany(Reservation::class, 'created_by_tenant_user_id');
    }

    public function printJobsRequested(): HasMany
    {
        return $this->hasMany(PrintJob::class, 'requested_by_tenant_user_id');
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('profile_photo')
            ->useDisk('tenant_public_media')
            ->singleFile();
    }

    protected function casts(): array
    {
        return [
            'last_accessed_at' => 'immutable_datetime',
            'last_synchronized_at' => 'immutable_datetime',
            'date_of_birth' => 'immutable_date',
            'hired_on' => 'immutable_date',
            'confirmed_on' => 'immutable_date',
            'ended_on' => 'immutable_date',
        ];
    }
}
