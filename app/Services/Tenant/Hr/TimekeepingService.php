<?php

namespace App\Services\Tenant\Hr;

use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Branch;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftDefinition;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TimekeepingService
{
    public function paginateShiftDefinitions(Branch $branch, int $perPage): Paginator
    {
        return ShiftDefinition::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->simplePaginate($perPage);
    }

    public function createShiftDefinition(Branch $branch, array $data): ShiftDefinition
    {
        try {
            return ShiftDefinition::query()
                ->create([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'name' => Str::squish($data['name']),
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'grace_minutes' => $data['grace_minutes'] ?? 0,
                    'crosses_midnight' => $data['crosses_midnight'] ?? false,
                    'status' => $data['status'] ?? 'active',
                ]);
        } catch (QueryException $exception) {
            $this->throwDuplicateShiftName($exception);
            throw $exception;
        }
    }

    public function updateShiftDefinition(Branch $branch, ShiftDefinition $shiftDefinition, array $data): ShiftDefinition
    {
        $shiftDefinition = $this->shiftDefinition($branch, $shiftDefinition->uuid);
        if (array_key_exists('name', $data)) {
            $data['name'] = Str::squish($data['name']);
        }
        try {
            $shiftDefinition->update($data);
        } catch (QueryException $exception) {
            $this->throwDuplicateShiftName($exception);
            throw $exception;
        }

        return $shiftDefinition->refresh();
    }

    public function paginateShiftAssignments(Branch $branch, array $filters, int $perPage): Paginator
    {
        return ShiftAssignment::query()
            ->where('branch_id', $branch->id)
            ->with(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'branch:id,timezone'])
            ->when($filters['tenant_user_uuid'] ?? null, function (Builder $query, string $uuid): void {
                $query->whereHas('tenantUser', fn (Builder $userQuery) => $userQuery->where('uuid', $uuid));
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = Str::lower(trim($search));
                $query->whereHas('tenantUser', function (Builder $userQuery) use ($search): void {
                    $userQuery->whereRaw('LOWER(display_name) LIKE ?', [$search.'%'])
                        ->orWhere('employee_number', 'LIKE', $search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom) use ($filters): void {
                $query->where('valid_from', '<=', $filters['date_to'] ?? '9999-12-31')
                    ->where(fn (Builder $dateQuery) => $dateQuery->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $dateFrom));
            })
            ->when(! ($filters['date_from'] ?? null) && ($filters['date_to'] ?? null), function (Builder $query) use ($filters): void {
                $query->where('valid_from', '<=', $filters['date_to']);
            })
            ->orderBy('valid_from')
            ->orderBy('id')
            ->simplePaginate($perPage);
    }

    public function createShiftAssignment(Branch $branch, array $data): ShiftAssignment
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $data): ShiftAssignment {
                $tenantUser = $this->tenantUserAssignedToBranch($branch, $data['tenant_user_uuid']);
                $shiftDefinition = $this->shiftDefinition($branch, $data['shift_definition_uuid']);
                $this->assertNoOverlappingAssignment($branch, $tenantUser->id, $data['valid_from'], $data['valid_to'] ?? null);

                return ShiftAssignment::query()
                    ->create([
                        'tenant_user_id' => $tenantUser->id,
                        'shift_definition_id' => $shiftDefinition->id,
                        'branch_id' => $branch->id,
                        'valid_from' => $data['valid_from'],
                        'valid_to' => $data['valid_to'] ?? null,
                        'status' => $data['status'] ?? 'active',
                    ])
                    ->load(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'branch:id,timezone']);
            });
    }

    /** @param array<int, array<string, mixed>> $groups */
    public function createBatchShiftAssignments(Branch $branch, array $groups): Collection
    {
        $rows = collect();

        foreach ($groups as $group) {
            foreach ($group['staff'] as $staff) {
                foreach ($staff['date_ranges'] as $range) {
                    $rows->push([
                        'tenant_user_uuid' => $staff['tenant_user_uuid'],
                        'shift_definition_uuid' => $group['shift_definition_uuid'],
                        'valid_from' => $range['valid_from'],
                        'valid_to' => $range['valid_to'] ?? null,
                    ]);
                }
            }
        }

        return DB::connection('tenant')
            ->transaction(function () use ($branch, $rows) {
                $users = TenantUser::query()
                    ->whereIn('uuid', $rows->pluck('tenant_user_uuid')
                        ->unique())
                    ->where('status', 'active')
                    ->whereHas('branchAssignments', fn (Builder $query) => $query->where('branch_id', $branch->id)
                        ->where('status', 'active'))
                    ->get()
                    ->keyBy('uuid');
                $shifts = ShiftDefinition::query()
                    ->where('company_id', $branch->company_id)
                    ->where('branch_id', $branch->id)
                    ->whereIn('uuid', $rows->pluck('shift_definition_uuid')
                        ->unique())
                    ->get()
                    ->keyBy('uuid');
                foreach ($rows as $index => $row) {
                    if (! $users->has($row['tenant_user_uuid'])) {
                        throw ValidationException::withMessages(["shift_groups.{$index}" => ['Each staff member must be active in the selected branch.']]);
                    }
                    if (! $shifts->has($row['shift_definition_uuid'])) {
                        throw ValidationException::withMessages(["shift_groups.{$index}" => ['Each shift must belong to the selected branch.']]);
                    }
                }
                $created = collect();
                foreach ($rows as $row) {
                    $created->push($this->createShiftAssignment($branch, $row));
                }

                return $created;
            });
    }

    /** @return array<int, array<string, mixed>> */
    public function myShifts(Branch $branch, TenantUser $tenantUser, array $range): array
    {
        $assignments = ShiftAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('tenant_user_id', $tenantUser->id)
            ->where('status', 'active')
            ->where('valid_from', '<=', $range['to'])
            ->where(fn (Builder $query) => $query->whereNull('valid_to')
                ->orWhere('valid_to', '>=', $range['from']))
            ->with('shiftDefinition:id,uuid,name,starts_at,ends_at,grace_minutes,crosses_midnight')
            ->orderBy('valid_from')
            ->get();
        $days = [];
        $rangeStart = CarbonImmutable::parse($range['from']);
        $rangeEnd = CarbonImmutable::parse($range['to']);

        for ($date = $rangeStart; $date->lessThanOrEqualTo($rangeEnd); $date = $date->addDay()) {
            $assignment = $assignments->first(function (ShiftAssignment $item) use ($date): bool {
                $hasStarted = $item->valid_from->lessThanOrEqualTo($date);
                $hasNotEnded = $item->valid_to === null || $item->valid_to->greaterThanOrEqualTo($date);

                return $hasStarted && $hasNotEnded;
            });
            if ($assignment) {
                $days[] = [
                    'date' => $date->toDateString(),
                    'shift' => [
                        'uuid' => $assignment->shiftDefinition->uuid,
                        'name' => $assignment->shiftDefinition->name,
                        'starts_at' => $assignment->shiftDefinition->starts_at,
                        'ends_at' => $assignment->shiftDefinition->ends_at,
                        'grace_minutes' => $assignment->shiftDefinition->grace_minutes,
                        'crosses_midnight' => $assignment->shiftDefinition->crosses_midnight,
                    ],
                ];
            }
        }

        return $days;
    }

    public function updateShiftAssignment(Branch $branch, ShiftAssignment $assignment, array $data): ShiftAssignment
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $assignment, $data): ShiftAssignment {
                $assignment = $this->assignment($branch, $assignment->uuid, true);
                $shiftDefinitionId = $assignment->shift_definition_id;
                if (array_key_exists('shift_definition_uuid', $data)) {
                    $shiftDefinitionId = $this->shiftDefinition($branch, $data['shift_definition_uuid'])->id;
                }
                $validFrom = $data['valid_from'] ?? $assignment->valid_from->toDateString();
                $validTo = array_key_exists('valid_to', $data) ? $data['valid_to'] : $assignment->valid_to?->toDateString();
                if ($validTo !== null && $validTo < $validFrom) {
                    throw ValidationException::withMessages(['valid_to' => ['The end date must be on or after the start date.']]);
                }
                if (($data['status'] ?? $assignment->status) === 'active') {
                    $this->assertNoOverlappingAssignment($branch, $assignment->tenant_user_id, $validFrom, $validTo, $assignment->id);
                }
                $assignment->update([
                    'shift_definition_id' => $shiftDefinitionId,
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'status' => $data['status'] ?? $assignment->status,
                ]);

                return $assignment->refresh()
                    ->load(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'branch:id,timezone']);
            });
    }

    public function clockIn(Branch $branch, TenantUser $tenantUser): AttendanceRecord
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $tenantUser): AttendanceRecord {
                $now = CarbonImmutable::now($branch->timezone);
                $attendanceDate = $now->toDateString();
                $assignment = $this->activeAssignment($branch, $tenantUser->id, $attendanceDate);
                if (! $assignment) {
                    throw ValidationException::withMessages(['shift_assignment' => ['No active shift assignment applies to this branch today.']]);
                }
                if (AttendanceRecord::query()
                    ->where('tenant_user_id', $tenantUser->id)
                    ->where('attendance_date', $attendanceDate)
                    ->lockForUpdate()
                    ->exists()) {
                    throw ValidationException::withMessages(['attendance' => ['You have already clocked in for today.']]);
                }
                $schedule = $this->schedule($assignment->shiftDefinition, $attendanceDate, $branch->timezone);
                try {
                    return AttendanceRecord::query()
                        ->create([
                            'tenant_user_id' => $tenantUser->id,
                            'branch_id' => $branch->id,
                            'shift_definition_id' => $assignment->shift_definition_id,
                            'attendance_date' => $attendanceDate,
                            'clock_in_at' => $now,
                            'late_minutes' => $this->lateMinutes($schedule, $now),
                            'status' => 'present',
                            'source' => 'system',
                        ]);
                } catch (QueryException $exception) {
                    if ($exception->getCode() === '23505') {
                        throw ValidationException::withMessages(['attendance' => ['You have already clocked in for today.']]);
                    }
                    throw $exception;
                }
            });
    }

    public function clockOut(Branch $branch, TenantUser $tenantUser): AttendanceRecord
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $tenantUser): AttendanceRecord {
                $now = CarbonImmutable::now($branch->timezone);
                $record = AttendanceRecord::query()
                    ->where('tenant_user_id', $tenantUser->id)
                    ->where('branch_id', $branch->id)
                    ->whereNull('clock_out_at')
                    ->orderByDesc('attendance_date')
                    ->lockForUpdate()
                    ->first();
                if (! $record) {
                    throw ValidationException::withMessages(['attendance' => ['There is no open clock-in for this branch.']]);
                }
                if ($now->lessThan($record->clock_in_at)) {
                    throw ValidationException::withMessages(['attendance' => ['The server time is earlier than the recorded clock-in time.']]);
                }
                $schedule = $record->shiftDefinition ? $this->schedule($record->shiftDefinition, $record->attendance_date->toDateString(), $branch->timezone) : null;
                $workedMinutes = $this->wholeMinutes($record->clock_in_at, $now);
                $record->update([
                    'clock_out_at' => $now,
                    'worked_minutes' => $workedMinutes,
                    'overtime_minutes' => $schedule ? max(0, $workedMinutes - $schedule['planned_minutes']) : 0,
                ]);

                return $record->refresh();
            });
    }

    /** @param array<int, string> $tenantUserUuids */
    public function batchClockIn(Branch $branch, ?TenantUser $actor, array $tenantUserUuids): Collection
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $tenantUserUuids) {
                $now = CarbonImmutable::now($branch->timezone);
                $date = $now->toDateString();
                $users = TenantUser::query()
                    ->whereIn('uuid', $tenantUserUuids)
                    ->where('status', 'active')
                    ->whereHas('branchAssignments', fn (Builder $query) => $query->where('branch_id', $branch->id)
                        ->where('status', 'active'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('uuid');
                $this->assertAllRequestedUsersFound($tenantUserUuids, $users);
                $userIds = $users->pluck('id')
                    ->all();
                $duplicates = AttendanceRecord::query()
                    ->whereIn('tenant_user_id', $userIds)
                    ->where('attendance_date', $date)
                    ->lockForUpdate()
                    ->pluck('tenant_user_id');
                if ($duplicates->isNotEmpty()) {
                    throw ValidationException::withMessages(['tenant_user_uuids' => ['One or more selected staff members already have attendance recorded for today.']]);
                }
                $assignments = ShiftAssignment::query()
                    ->where('branch_id', $branch->id)
                    ->whereIn('tenant_user_id', $userIds)
                    ->where('status', 'active')
                    ->where('valid_from', '<=', $date)
                    ->where(fn (Builder $query) => $query->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $date))
                    ->with('shiftDefinition:id,starts_at,ends_at,grace_minutes,crosses_midnight,status')
                    ->get()
                    ->keyBy('tenant_user_id');

                return $users->map(function (TenantUser $user) use ($assignments, $branch, $actor, $now, $date): AttendanceRecord {
                    $assignment = $assignments->get($user->id);
                    $shift = $assignment?->shiftDefinition?->status === 'active' ? $assignment->shiftDefinition : null;
                    $schedule = $shift ? $this->schedule($shift, $date, $branch->timezone) : null;

                    return AttendanceRecord::query()
                        ->create([
                            'tenant_user_id' => $user->id,
                            'branch_id' => $branch->id,
                            'shift_definition_id' => $shift?->id,
                            'attendance_date' => $date,
                            'clock_in_at' => $now,
                            'late_minutes' => $schedule ? $this->lateMinutes($schedule, $now) : 0,
                            'status' => 'present',
                            'source' => 'manual',
                            'recorded_by_tenant_user_id' => $actor?->id,
                            'recorded_at' => $now,
                        ]);
                })
                    ->values()
                    ->load(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'recordedBy:id,uuid,display_name']);
            });
    }

    /** @param array<int, string> $tenantUserUuids */
    public function batchClockOut(Branch $branch, ?TenantUser $actor, array $tenantUserUuids): Collection
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $tenantUserUuids) {
                $now = CarbonImmutable::now($branch->timezone);
                $users = TenantUser::query()
                    ->whereIn('uuid', $tenantUserUuids)
                    ->where('status', 'active')
                    ->whereHas('branchAssignments', fn (Builder $query) => $query->where('branch_id', $branch->id)
                        ->where('status', 'active'))
                    ->get()
                    ->keyBy('uuid');
                $this->assertAllRequestedUsersFound($tenantUserUuids, $users);
                $records = AttendanceRecord::query()
                    ->where('branch_id', $branch->id)
                    ->whereIn('tenant_user_id', $users->pluck('id'))
                    ->whereNull('clock_out_at')
                    ->with('shiftDefinition:id,uuid,name,starts_at,ends_at,grace_minutes,crosses_midnight,status')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('tenant_user_id');
                if ($records->count() !== $users->count()) {
                    throw ValidationException::withMessages(['tenant_user_uuids' => ['Every selected staff member must have an open attendance record before clock-out.']]);
                }

                return $records->map(function (AttendanceRecord $record) use ($branch, $actor, $now): AttendanceRecord {
                    $workedMinutes = $this->wholeMinutes($record->clock_in_at, $now);
                    $schedule = $record->shiftDefinition ? $this->schedule($record->shiftDefinition, $record->attendance_date->toDateString(), $branch->timezone) : null;
                    $record->update([
                        'clock_out_at' => $now,
                        'worked_minutes' => $workedMinutes,
                        'overtime_minutes' => $schedule ? max(0, $workedMinutes - $schedule['planned_minutes']) : 0,
                        'recorded_by_tenant_user_id' => $actor?->id,
                        'recorded_at' => $now,
                    ]);

                    return $record->refresh();
                })
                    ->values()
                    ->load(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'recordedBy:id,uuid,display_name']);
            });
    }

    public function paginateAttendance(Branch $branch, array $filters, int $perPage): Paginator
    {
        return AttendanceRecord::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('attendance_date', [$filters['date_from'], $filters['date_to']])
            ->with(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'adjustedBy:id,uuid,display_name'])
            ->when($filters['tenant_user_uuid'] ?? null, function (Builder $query, string $uuid): void {
                $query->whereHas('tenantUser', fn (Builder $userQuery) => $userQuery->where('uuid', $uuid));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->simplePaginate($perPage);
    }

    public function createManualAttendance(Branch $branch, TenantUser $actor, array $data): AttendanceRecord
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $data): AttendanceRecord {
                $tenantUser = $this->tenantUserAssignedToBranch($branch, $data['tenant_user_uuid']);
                $shiftDefinition = isset($data['shift_definition_uuid']) ? $this->shiftDefinition($branch, $data['shift_definition_uuid']) : null;
                if (AttendanceRecord::query()
                    ->where('tenant_user_id', $tenantUser->id)
                    ->where('attendance_date', $data['attendance_date'])
                    ->lockForUpdate()
                    ->exists()) {
                    throw ValidationException::withMessages(['attendance_date' => ['An attendance record already exists for this staff member on this date. Use adjustment instead.']]);
                }
                $values = $this->attendanceValues($branch, $data, $shiftDefinition);

                return AttendanceRecord::query()
                    ->create(array_merge($values, [
                        'tenant_user_id' => $tenantUser->id,
                        'branch_id' => $branch->id,
                        'shift_definition_id' => $shiftDefinition?->id,
                        'attendance_date' => $data['attendance_date'],
                        'source' => 'manual',
                        'notes' => $data['reason'],
                        'adjusted_by_tenant_user_id' => $actor->id,
                        'adjusted_at' => CarbonImmutable::now($branch->timezone),
                        'adjustment_reason' => $data['reason'],
                    ]));
            });
    }

    public function adjustAttendance(Branch $branch, TenantUser $actor, AttendanceRecord $record, array $data): AttendanceRecord
    {
        return DB::connection('tenant')
            ->transaction(function () use ($branch, $actor, $record, $data): AttendanceRecord {
                $record = AttendanceRecord::query()
                    ->where('branch_id', $branch->id)
                    ->whereKey($record->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $values = $this->attendanceValues($branch, array_merge([
                    'attendance_date' => $record->attendance_date->toDateString(),
                    'clock_in_at' => $record->clock_in_at,
                    'clock_out_at' => $record->clock_out_at,
                    'status' => $record->status,
                ], $data), $record->shiftDefinition);
                DB::connection('tenant')
                    ->table('attendance_adjustments')
                    ->insert([
                        'uuid' => (string) Str::uuid(),
                        'attendance_record_id' => $record->id,
                        'adjusted_by_tenant_user_id' => $actor->id,
                        'reason' => $data['reason'],
                        'previous_values' => json_encode($this->attendanceAuditValues($record), JSON_THROW_ON_ERROR),
                        'new_values' => json_encode($values, JSON_THROW_ON_ERROR),
                        'created_at' => CarbonImmutable::now($branch->timezone),
                    ]);
                $record->update(array_merge($values, [
                    'source' => 'manual',
                    'adjusted_by_tenant_user_id' => $actor->id,
                    'adjusted_at' => CarbonImmutable::now($branch->timezone),
                    'adjustment_reason' => $data['reason'],
                ]));

                return $record->refresh()
                    ->load(['tenantUser:id,uuid,display_name,employee_number', 'shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight', 'adjustedBy:id,uuid,display_name']);
            });
    }

    public function currentAttendance(Branch $branch, TenantUser $tenantUser): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('branch_id', $branch->id)
            ->where('tenant_user_id', $tenantUser->id)
            ->where('attendance_date', CarbonImmutable::now($branch->timezone)
                ->toDateString())
            ->with('shiftDefinition:id,uuid,name,starts_at,ends_at,crosses_midnight')
            ->first();
    }

    public function currentTenantUser(Branch $branch, User $actor): TenantUser
    {
        return $this->activeTenantUserForActor($branch, $actor) ?? throw (new ModelNotFoundException)->setModel(TenantUser::class);
    }

    public function recordingTenantUser(Branch $branch, User $actor): ?TenantUser
    {
        return $this->activeTenantUserForActor($branch, $actor);
    }

    private function activeTenantUserForActor(Branch $branch, User $actor): ?TenantUser
    {
        return $actor->tenantUser()
            ->where('status', 'active')
            ->whereHas('branchAssignments', fn (Builder $query) => $query->where('branch_id', $branch->id)
                ->where('status', 'active'))
            ->first();
    }

    private function shiftDefinition(Branch $branch, string $uuid): ShiftDefinition
    {
        return ShiftDefinition::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function assignment(Branch $branch, string $uuid, bool $lock = false): ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('uuid', $uuid)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->firstOrFail();
    }

    private function tenantUserAssignedToBranch(Branch $branch, string $uuid): TenantUser
    {
        return TenantUser::query()
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->whereHas('branchAssignments', function (Builder $query) use ($branch): void {
                $query->where('branch_id', $branch->id)
                    ->where('status', 'active');
            })
            ->firstOrFail();
    }

    private function assertAllRequestedUsersFound(array $requestedUuids, Collection $users): void
    {
        if ($users->count() !== count($requestedUuids)) {
            throw ValidationException::withMessages(['tenant_user_uuids' => ['Each selected staff member must be active in the selected branch.']]);
        }
    }

    private function activeAssignment(Branch $branch, int $tenantUserId, string $date): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('tenant_user_id', $tenantUserId)
            ->where('status', 'active')
            ->where('valid_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('valid_to')
                ->orWhere('valid_to', '>=', $date))
            ->whereHas('shiftDefinition', fn (Builder $query) => $query->where('status', 'active'))
            ->with('shiftDefinition:id,uuid,name,starts_at,ends_at,grace_minutes,crosses_midnight,status')
            ->orderByDesc('valid_from')
            ->first();
    }

    private function assertNoOverlappingAssignment(Branch $branch, int $tenantUserId, string $validFrom, ?string $validTo, ?int $exceptId = null): void
    {
        $query = ShiftAssignment::query()
            ->where('branch_id', $branch->id)
            ->where('tenant_user_id', $tenantUserId)
            ->where('status', 'active')
            ->where('valid_from', '<=', $validTo ?? '9999-12-31')
            ->where(fn (Builder $assignmentQuery) => $assignmentQuery->whereNull('valid_to')
                ->orWhere('valid_to', '>=', $validFrom))
            ->lockForUpdate();
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['valid_from' => ['This staff member already has an active shift assignment covering this date range.']]);
        }
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, grace_ends_at: CarbonImmutable, planned_minutes: int}
     */
    private function schedule(ShiftDefinition $shift, string $date, string $timezone): array
    {
        $startsAt = CarbonImmutable::parse($date.' '.$shift->starts_at, $timezone);
        $endsAt = CarbonImmutable::parse($date.' '.$shift->ends_at, $timezone);
        if ($shift->crosses_midnight || $endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt = $endsAt->addDay();
        }

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'grace_ends_at' => $startsAt->addMinutes($shift->grace_minutes),
            'planned_minutes' => $this->wholeMinutes($startsAt, $endsAt),
        ];
    }

    private function attendanceValues(Branch $branch, array $data, ?ShiftDefinition $shift): array
    {
        $clockIn = isset($data['clock_in_at']) ? CarbonImmutable::parse($data['clock_in_at'], $branch->timezone) : null;
        $clockOut = isset($data['clock_out_at']) ? CarbonImmutable::parse($data['clock_out_at'], $branch->timezone) : null;
        $workedMinutes = $clockIn && $clockOut ? $this->wholeMinutes($clockIn, $clockOut) : 0;
        $schedule = $shift ? $this->schedule($shift, $data['attendance_date'] ?? $clockIn?->toDateString() ?? now($branch->timezone)->toDateString(), $branch->timezone) : null;

        return [
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $clockIn && $schedule ? $this->lateMinutes($schedule, $clockIn) : 0,
            'overtime_minutes' => $schedule ? max(0, $workedMinutes - $schedule['planned_minutes']) : 0,
            'status' => $data['status'],
        ];
    }

    /**
     * Attendance duration columns are integers. Do not pass Carbon's fractional
     * minute result into PostgreSQL integer columns.
     */
    private function wholeMinutes(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        return (int) floor($from->diffInSeconds($to) / 60);
    }

    /** @param array{grace_ends_at: CarbonImmutable} $schedule */
    private function lateMinutes(array $schedule, CarbonImmutable $clockIn): int
    {
        return $this->wholeMinutes($schedule['grace_ends_at'], $clockIn);
    }

    private function throwDuplicateShiftName(QueryException $exception): void
    {
        if ($exception->getCode() === '23505') {
            throw ValidationException::withMessages(['name' => ['A shift definition with this name already exists for the current branch.']]);
        }
    }

    private function attendanceAuditValues(AttendanceRecord $record): array
    {
        return [
            'clock_in_at' => $record->clock_in_at?->toIso8601String(),
            'clock_out_at' => $record->clock_out_at?->toIso8601String(),
            'worked_minutes' => $record->worked_minutes,
            'late_minutes' => $record->late_minutes,
            'overtime_minutes' => $record->overtime_minutes,
            'status' => $record->status,
        ];
    }
}
