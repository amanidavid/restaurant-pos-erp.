<?php

namespace App\Http\Controllers\Web\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Organisation\StoreDepartmentRequest;
use App\Http\Requests\Web\Organisation\StoreEmploymentTypeRequest;
use App\Http\Requests\Web\Organisation\StoreJobPositionRequest;
use App\Http\Requests\Web\Organisation\UpdateDepartmentRequest;
use App\Http\Requests\Web\Organisation\UpdateEmploymentTypeRequest;
use App\Http\Requests\Web\Organisation\UpdateJobPositionRequest;
use App\Http\Resources\Web\Organisation\DepartmentResource;
use App\Http\Resources\Web\Organisation\EmploymentTypeResource;
use App\Http\Resources\Web\Organisation\JobPositionResource;
use App\Models\Tenant\Company;
use App\Models\Tenant\Department;
use App\Models\Tenant\EmploymentType;
use App\Models\Tenant\JobPosition;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganisationController extends Controller
{
    public function departments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Department::class, $this->currentBranch()]);

        $departments = Department::query()->with(['branch:id,uuid', 'parent:id,uuid'])->orderBy('name')->simplePaginate($this->perPage($request));

        return ApiResponse::resource(DepartmentResource::collection($departments), ApiMessages::listRetrieved('departments'));
    }

    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('create', [Department::class, $this->currentBranch()]);

        $data = $request->validated();
        $department = Department::query()->create([
            'company_id' => $this->companyId(),
            'branch_id' => $this->branchId($data['branch_uuid'] ?? null),
            'parent_id' => $this->departmentId($data['parent_uuid'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return ApiResponse::resource(
            new DepartmentResource($department->load(['branch', 'parent'])),
            ApiMessages::created('department'),
            201
        );
    }

    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('update', [$department, $this->currentBranch()]);

        $data = $request->validated();
        $updates = [];

        if (array_key_exists('branch_uuid', $data)) {
            $updates['branch_id'] = $this->branchId($data['branch_uuid']);
        }

        if (array_key_exists('parent_uuid', $data)) {
            $updates['parent_id'] = $this->departmentId($data['parent_uuid']);

            if ($updates['parent_id'] === $department->id) {
                throw ValidationException::withMessages([
                    'parent_uuid' => ['A department cannot be its own parent.'],
                ]);
            }
        }

        foreach (['name', 'description', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        $department->update($updates);

        return ApiResponse::resource(
            new DepartmentResource($department->refresh()->load(['branch', 'parent'])),
            ApiMessages::updated('department')
        );
    }

    public function employmentTypes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [EmploymentType::class, $this->currentBranch()]);

        return ApiResponse::resource(EmploymentTypeResource::collection(EmploymentType::query()->orderBy('name')->simplePaginate($this->perPage($request))), ApiMessages::listRetrieved('employment types'));
    }

    public function storeEmploymentType(StoreEmploymentTypeRequest $request): JsonResponse
    {
        $this->authorize('create', [EmploymentType::class, $this->currentBranch()]);

        $data = $request->validated();
        $employmentType = EmploymentType::query()->create([
            'company_id' => $this->companyId(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_salary_based' => $data['is_salary_based'],
            'status' => $data['status'] ?? 'active',
        ]);

        return ApiResponse::resource(
            new EmploymentTypeResource($employmentType),
            ApiMessages::created('employment type'),
            201
        );
    }

    public function updateEmploymentType(UpdateEmploymentTypeRequest $request, EmploymentType $employmentType): JsonResponse
    {
        $this->authorize('update', [$employmentType, $this->currentBranch()]);

        $employmentType->update($request->validated());

        return ApiResponse::resource(
            new EmploymentTypeResource($employmentType->refresh()),
            ApiMessages::updated('employment type')
        );
    }

    public function jobPositions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [JobPosition::class, $this->currentBranch()]);

        return ApiResponse::resource(JobPositionResource::collection(JobPosition::query()->with('department:id,uuid')->orderBy('name')->simplePaginate($this->perPage($request))), ApiMessages::listRetrieved('job positions'));
    }

    public function storeJobPosition(StoreJobPositionRequest $request): JsonResponse
    {
        $this->authorize('create', [JobPosition::class, $this->currentBranch()]);

        $data = $request->validated();
        $jobPosition = JobPosition::query()->create([
            'company_id' => $this->companyId(),
            'department_id' => $this->departmentId($data['department_uuid'] ?? null),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'level_rank' => $data['level_rank'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return ApiResponse::resource(
            new JobPositionResource($jobPosition->load('department')),
            ApiMessages::created('job position'),
            201
        );
    }

    public function updateJobPosition(UpdateJobPositionRequest $request, JobPosition $jobPosition): JsonResponse
    {
        $this->authorize('update', [$jobPosition, $this->currentBranch()]);

        $data = $request->validated();

        if (array_key_exists('department_uuid', $data)) {
            $data['department_id'] = $this->departmentId($data['department_uuid']);
            unset($data['department_uuid']);
        }

        $jobPosition->update($data);

        return ApiResponse::resource(
            new JobPositionResource($jobPosition->refresh()->load('department')),
            ApiMessages::updated('job position')
        );
    }

    private function companyId(): int
    {
        return (int) Company::query()->orderBy('id')->firstOrFail(['id'])->id;
    }

    private function branchId(?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        $id = Branch::query()->where('uuid', $uuid)->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'branch_uuid' => ['The selected branch could not be found.'],
            ]);
        }

        return (int) $id;
    }

    private function departmentId(?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        $id = Department::query()->where('uuid', $uuid)->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'department_uuid' => ['The selected department could not be found.'],
            ]);
        }

        return (int) $id;
    }

}
