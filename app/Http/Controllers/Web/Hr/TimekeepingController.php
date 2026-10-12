<?php

namespace App\Http\Controllers\Web\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Hr\AdjustAttendanceRequest;
use App\Http\Requests\Web\Hr\IndexAttendanceRequest;
use App\Http\Requests\Web\Hr\IndexMyShiftsRequest;
use App\Http\Requests\Web\Hr\IndexShiftAssignmentRequest;
use App\Http\Requests\Web\Hr\StoreBatchShiftAssignmentRequest;
use App\Http\Requests\Web\Hr\StoreBatchAttendanceRequest;
use App\Http\Requests\Web\Hr\StoreManualAttendanceRequest;
use App\Http\Requests\Web\Hr\StoreShiftAssignmentRequest;
use App\Http\Requests\Web\Hr\StoreShiftDefinitionRequest;
use App\Http\Requests\Web\Hr\UpdateShiftAssignmentRequest;
use App\Http\Requests\Web\Hr\UpdateShiftDefinitionRequest;
use App\Http\Resources\Web\Hr\AttendanceRecordResource;
use App\Http\Resources\Web\Hr\ShiftAssignmentResource;
use App\Http\Resources\Web\Hr\ShiftDefinitionResource;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftDefinition;
use App\Services\Tenant\Hr\TimekeepingService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimekeepingController extends Controller
{
    public function __construct(private readonly TimekeepingService $timekeeping) {}

    public function shiftDefinitions(Request $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [ShiftDefinition::class, $branch]);

        return ApiResponse::resource(
            ShiftDefinitionResource::collection($this->timekeeping->paginateShiftDefinitions($branch, $this->perPage($request))),
            ApiMessages::listRetrieved('shift definitions'),
        );
    }

    public function storeShiftDefinition(StoreShiftDefinitionRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [ShiftDefinition::class, $branch]);

        return ApiResponse::resource(
            new ShiftDefinitionResource($this->timekeeping->createShiftDefinition($branch, $request->validated())),
            ApiMessages::created('shift definition'),
            201,
        );
    }

    public function updateShiftDefinition(UpdateShiftDefinitionRequest $request, ShiftDefinition $shiftDefinition): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('updateShiftDefinition', [$shiftDefinition, $branch]);

        return ApiResponse::resource(
            new ShiftDefinitionResource($this->timekeeping->updateShiftDefinition($branch, $shiftDefinition, $request->validated())),
            ApiMessages::updated('shift definition'),
        );
    }

    public function shiftAssignments(IndexShiftAssignmentRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [ShiftAssignment::class, $branch]);

        return ApiResponse::resource(
            ShiftAssignmentResource::collection($this->timekeeping->paginateShiftAssignments($branch, $request->validated(), $this->perPage($request))),
            ApiMessages::listRetrieved('shift assignments'),
        );
    }

    public function storeBatchShiftAssignments(StoreBatchShiftAssignmentRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [ShiftAssignment::class, $branch]);

        return ApiResponse::resource(
            ShiftAssignmentResource::collection($this->timekeeping->createBatchShiftAssignments($branch, $request->validated()['shift_groups'])),
            'Shift assignments saved successfully.',
            201,
        );
    }

    public function myShifts(IndexMyShiftsRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewOwnShifts', [ShiftAssignment::class, $branch]);
        $tenantUser = $this->timekeeping->currentTenantUser($branch, $request->user('tenant_api'));

        return ApiResponse::success('Your shifts loaded successfully.', [
            'shifts' => $this->timekeeping->myShifts($branch, $tenantUser, $request->validated()),
        ]);
    }

    public function storeShiftAssignment(StoreShiftAssignmentRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [ShiftAssignment::class, $branch]);

        return ApiResponse::resource(
            new ShiftAssignmentResource($this->timekeeping->createShiftAssignment($branch, $request->validated())),
            ApiMessages::created('shift assignment'),
            201,
        );
    }

    public function updateShiftAssignment(UpdateShiftAssignmentRequest $request, ShiftAssignment $shiftAssignment): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('updateShiftAssignment', [$shiftAssignment, $branch]);

        return ApiResponse::resource(
            new ShiftAssignmentResource($this->timekeeping->updateShiftAssignment($branch, $shiftAssignment, $request->validated())),
            ApiMessages::updated('shift assignment'),
        );
    }

    public function clockIn(Request $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $tenantUser = $this->timekeeping->currentTenantUser($branch, $request->user('tenant_api'));
        $record = $this->timekeeping->clockIn($branch, $tenantUser);

        return ApiResponse::resource(
            new AttendanceRecordResource($this->loadRecordRelations($record)),
            'Clock-in recorded using server time.',
            201,
        );
    }

    public function clockOut(Request $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $tenantUser = $this->timekeeping->currentTenantUser($branch, $request->user('tenant_api'));
        $record = $this->timekeeping->clockOut($branch, $tenantUser);

        return ApiResponse::resource(
            new AttendanceRecordResource($this->loadRecordRelations($record)),
            'Clock-out recorded using server time.',
        );
    }

    public function batchClockIn(StoreBatchAttendanceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [AttendanceRecord::class, $branch]);
        $actor = $this->timekeeping->recordingTenantUser($branch, $request->user('tenant_api'));

        return ApiResponse::resource(
            AttendanceRecordResource::collection($this->timekeeping->batchClockIn($branch, $actor, $request->validated('tenant_user_uuids'))),
            'Attendance recorded successfully.',
            201,
        );
    }

    public function batchClockOut(StoreBatchAttendanceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [AttendanceRecord::class, $branch]);
        $actor = $this->timekeeping->recordingTenantUser($branch, $request->user('tenant_api'));

        return ApiResponse::resource(
            AttendanceRecordResource::collection($this->timekeeping->batchClockOut($branch, $actor, $request->validated('tenant_user_uuids'))),
            'Attendance clock-out recorded successfully.',
        );
    }

    public function myAttendance(Request $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $tenantUser = $this->timekeeping->currentTenantUser($branch, $request->user('tenant_api'));
        $record = $this->timekeeping->currentAttendance($branch, $tenantUser);

        return ApiResponse::success('Current attendance retrieved successfully.', [
            'attendance' => $record ? new AttendanceRecordResource($record) : null,
        ]);
    }

    public function attendance(IndexAttendanceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [AttendanceRecord::class, $branch]);

        return ApiResponse::resource(
            AttendanceRecordResource::collection($this->timekeeping->paginateAttendance($branch, $request->validated(), $this->perPage($request))),
            ApiMessages::listRetrieved('attendance records'),
        );
    }

    public function storeManualAttendance(StoreManualAttendanceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('manage', [AttendanceRecord::class, $branch]);
        $actor = $this->timekeeping->currentTenantUser($branch, $request->user('tenant_api'));
        $record = $this->timekeeping->createManualAttendance($branch, $actor, $request->validated());

        return ApiResponse::resource(
            new AttendanceRecordResource($this->loadRecordRelations($record)),
            ApiMessages::created('attendance record'),
            201,
        );
    }

    public function adjustAttendance(AdjustAttendanceRequest $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('adjustAttendance', [$attendanceRecord, $branch]);
        $actor = $this->timekeeping->currentTenantUser($branch, $request->user('tenant_api'));
        $record = $this->timekeeping->adjustAttendance($branch, $actor, $attendanceRecord, $request->validated());

        return ApiResponse::resource(
            new AttendanceRecordResource($record),
            'Attendance record adjusted successfully.',
        );
    }

    private function loadRecordRelations(AttendanceRecord $record): AttendanceRecord
    {
        return $record->load([
            'tenantUser:id,uuid,display_name,employee_number',
            'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight',
            'adjustedBy:id,uuid,display_name',
        ]);
    }
}
