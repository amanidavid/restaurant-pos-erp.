<?php

namespace App\Services\Tenant\Organisation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\Department;
use App\Models\Tenant\EmploymentType;
use App\Models\Tenant\JobPosition;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserJobHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffProfileService
{
    public function update(TenantUser $tenantUser, array $data): TenantUser
    {
        return DB::connection('tenant')
            ->transaction(function () use ($tenantUser, $data): TenantUser {
                $tenantUser = TenantUser::query()
                    ->whereKey($tenantUser->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $updates = $this->profileUpdates($tenantUser, $data);
                $this->assertHomeBranchAccess($tenantUser, $updates);
                $tenantUser->update($updates);

                return $this->loadProfile($tenantUser);
            });
    }

    public function addJobHistory(TenantUser $tenantUser, array $data): TenantUserJobHistory
    {
        return DB::connection('tenant')
            ->transaction(function () use ($tenantUser, $data): TenantUserJobHistory {
                $tenantUser = TenantUser::query()
                    ->whereKey($tenantUser->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $effectiveFrom = $data['effective_from'];
                $openHistory = TenantUserJobHistory::query()
                    ->where('tenant_user_id', $tenantUser->id)
                    ->whereNull('effective_to')
                    ->lockForUpdate()
                    ->first();
                if ($openHistory && $openHistory->effective_from >= $effectiveFrom) {
                    throw ValidationException::withMessages(['effective_from' => ['The effective date must be later than the current job history record.']]);
                }
                if ($openHistory) {
                    $openHistory->update(['effective_to' => Carbon::parse($effectiveFrom)
                        ->subDay()
                        ->toDateString()]);
                }
                $updates = $this->profileUpdates($tenantUser, $data, false);
                $this->assertHomeBranchAccess($tenantUser, $updates);
                $tenantUser->update($updates);

                return TenantUserJobHistory::query()
                    ->create([
                        'tenant_user_id' => $tenantUser->id,
                        'branch_id' => $updates['home_branch_id'] ?? $tenantUser->home_branch_id,
                        'department_id' => $updates['department_id'] ?? $tenantUser->department_id,
                        'job_position_id' => $updates['job_position_id'] ?? $tenantUser->job_position_id,
                        'employment_type_id' => $updates['employment_type_id'] ?? $tenantUser->employment_type_id,
                        'base_salary_amount' => $data['base_salary_amount'] ?? null,
                        'currency_code' => 'TZS',
                        'effective_from' => $effectiveFrom,
                        'reason' => $data['reason'] ?? null,
                    ]);
            });
    }

    public function profile(TenantUser $tenantUser): TenantUser
    {
        return $this->loadProfile($tenantUser);
    }

    private function loadProfile(TenantUser $tenantUser): TenantUser
    {
        return $tenantUser->fresh([
            'employmentType:id,uuid,name',
            'homeBranch:id,uuid,name',
            'department:id,uuid,name',
            'jobPosition:id,uuid,name',
        ]);
    }

    private function profileUpdates(TenantUser $tenantUser, array $data, bool $includeAll = true): array
    {
        $updates = [];
        $map = [
            'employment_type_uuid' => ['employment_type_id', EmploymentType::class],
            'home_branch_uuid' => ['home_branch_id', Branch::class],
            'department_uuid' => ['department_id', Department::class],
            'job_position_uuid' => ['job_position_id', JobPosition::class],
        ];
        foreach ($map as $field => [$column, $model]) {
            if (array_key_exists($field, $data)) {
                $updates[$column] = $this->idForUuid($model, $data[$field], $field);
            }
        }
        if ($includeAll) {
            $updates['company_id'] = Company::query()
                ->orderBy('id')
                ->value('id');
            foreach ([
                'employee_number',
                'date_of_birth',
                'hired_on',
                'confirmed_on',
                'ended_on',
                'employment_status',
                'gender',
                'national_id_number',
                'tax_number',
                'nssf_number',
                'nhif_number',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }
            if (array_key_exists('employee_number', $updates) && $updates['employee_number'] !== null) {
                $exists = TenantUser::query()
                    ->where('company_id', $updates['company_id'])
                    ->where('employee_number', $updates['employee_number'])
                    ->whereKeyNot($tenantUser->id)
                    ->exists();
                if ($exists) {
                    throw ValidationException::withMessages(['employee_number' => ['This employee number is already in use.']]);
                }
            }
        }

        return $updates;
    }

    private function idForUuid(string $model, ?string $uuid, string $field): ?int
    {
        if (! $uuid) {
            return null;
        }
        $id = $model::query()
            ->where('uuid', $uuid)
            ->value('id');
        if (! $id) {
            throw ValidationException::withMessages([$field => ['The selected record could not be found.']]);
        }

        return $id;
    }

    private function assertHomeBranchAccess(TenantUser $tenantUser, array $updates): void
    {
        if (! array_key_exists('home_branch_id', $updates) || $updates['home_branch_id'] === null) {
            return;
        }
        $hasAccess = $tenantUser->branchAssignments()
            ->where('branch_id', $updates['home_branch_id'])
            ->where('status', 'active')
            ->exists();
        if (! $hasAccess) {
            throw ValidationException::withMessages(['home_branch_uuid' => ['The staff member must have access to the selected home branch.']]);
        }
    }
}
