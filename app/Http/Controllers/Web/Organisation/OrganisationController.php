<?php

namespace App\Http\Controllers\Web\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Organisation\IndexDepartmentRequest;
use App\Http\Requests\Web\Organisation\StoreDepartmentRequest;
use App\Http\Requests\Web\Organisation\StoreEmploymentTypeRequest;
use App\Http\Requests\Web\Organisation\StoreJobPositionRequest;
use App\Http\Requests\Web\Organisation\UpdateDepartmentRequest;
use App\Http\Requests\Web\Organisation\UpdateEmploymentTypeRequest;
use App\Http\Requests\Web\Organisation\UpdateJobPositionRequest;
use App\Http\Resources\Web\Organisation\DepartmentResource;
use App\Http\Resources\Web\Organisation\EmploymentTypeResource;
use App\Http\Resources\Web\Organisation\JobPositionResource;
use App\Models\Tenant\Department;
use App\Models\Tenant\EmploymentType;
use App\Models\Tenant\JobPosition;
use App\Services\Tenant\Organisation\DepartmentService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function departments(IndexDepartmentRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [Department::class, $branch]);

        $departments = $this->departments->paginate($branch, $request->validated(), $this->perPage($request));

        return ApiResponse::resource(DepartmentResource::collection($departments), ApiMessages::listRetrieved('departments'));
    }

    public function departmentTree(IndexDepartmentRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [Department::class, $branch]);

        return ApiResponse::success(
            message: 'Department hierarchy retrieved successfully.',
            data: $this->departments->tree($branch, $request->validated('status') ?? 'active'),
        );
    }

    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('create', [Department::class, $branch]);
        $department = $this->departments->create($branch, $request->validated());

        return ApiResponse::resource(
            new DepartmentResource($department->load(['branch', 'parent'])),
            ApiMessages::created('department'),
            201
        );
    }

    public function showDepartment(Department $department): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('view', [$department, $branch]);

        return ApiResponse::resource(
            new DepartmentResource($this->departments->find($branch, $department)),
            ApiMessages::detailsRetrieved('department'),
        );
    }

    public function updateDepartment(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('update', [$department, $branch]);
        $department = $this->departments->update($branch, $department, $request->validated());

        return ApiResponse::resource(
            new DepartmentResource($department),
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
        return $this->currentBranch()->company_id;
    }

    private function departmentId(?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        $id = Department::query()
            ->where('company_id', $this->companyId())
            ->where('branch_id', $this->currentBranchId())
            ->where('uuid', $uuid)
            ->value('id');

        if (! $id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'department_uuid' => ['The selected department could not be found.'],
            ]);
        }

        return (int) $id;
    }

}
