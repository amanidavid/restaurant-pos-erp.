<?php

namespace App\Services\Tenant\Organisation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Department;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    /**
     * @param  array{status?: string, search?: string}  $filters
     */
    public function paginate(Branch $branch, array $filters, int $perPage): Paginator
    {
        return $this->branchQuery($branch)
            ->with('parent:id,uuid,name')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name_normalized', 'like', $this->normalizeName($search).'%'))
            ->orderBy('name_normalized')
            ->simplePaginate($perPage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(Branch $branch, string $status = 'active'): array
    {
        $departments = $this->branchQuery($branch)
            ->where('status', $status)
            ->orderBy('name_normalized')
            ->get(['id', 'uuid', 'parent_id', 'name', 'status']);

        $childrenByParentId = [];

        foreach ($departments as $department) {
            $childrenByParentId[$department->parent_id ?? 0][] = $department;
        }

        $uuidById = $departments->pluck('uuid', 'id')->all();

        return $this->treeNodes($childrenByParentId, $uuidById, 0, []);
    }

    /**
     * @param  array{name: string, description?: string|null, parent_uuid?: string|null, status?: string}  $data
     */
    public function create(Branch $branch, array $data): Department
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $data): Department {
            $parentId = $this->parentId($branch, $data['parent_uuid'] ?? null);

            try {
                $department = Department::query()->create([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'parent_id' => $parentId,
                    'name' => $data['name'],
                    'name_normalized' => $this->normalizeName($data['name']),
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);
            } catch (QueryException $exception) {
                $this->throwDuplicateName($exception);

                throw $exception;
            }

            return $department->load('parent:id,uuid,name');
        });
    }

    public function find(Branch $branch, Department $department): Department
    {
        return $this->branchQuery($branch)
            ->with('parent:id,uuid,name')
            ->whereKey($department->id)
            ->firstOrFail();
    }

    /**
     * @param  array{name?: string, description?: string|null, parent_uuid?: string|null, status?: string}  $data
     */
    public function update(Branch $branch, Department $department, array $data): Department
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $department, $data): Department {
            $department = $this->branchQuery($branch)->whereKey($department->id)->lockForUpdate()->firstOrFail();
            $updates = [];

            if (array_key_exists('parent_uuid', $data)) {
                $updates['parent_id'] = $this->parentId($branch, $data['parent_uuid'], $department->id);
            }

            if (array_key_exists('name', $data)) {
                $updates['name'] = $data['name'];
                $updates['name_normalized'] = $this->normalizeName($data['name']);
            }

            foreach (['description', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            try {
                $department->update($updates);
            } catch (QueryException $exception) {
                $this->throwDuplicateName($exception);

                throw $exception;
            }

            return $department->refresh()->load('parent:id,uuid,name');
        });
    }

    private function branchQuery(Branch $branch): Builder
    {
        return Department::query()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id);
    }

    private function parentId(Branch $branch, ?string $parentUuid, ?int $departmentId = null): ?int
    {
        if ($parentUuid === null) {
            return null;
        }

        $parent = $this->branchQuery($branch)
            ->where('uuid', $parentUuid)
            ->lockForUpdate()
            ->first(['id', 'parent_id', 'status']);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_uuid' => ['The selected parent department must belong to the current branch.'],
            ]);
        }

        if ($parent->status !== 'active') {
            throw ValidationException::withMessages([
                'parent_uuid' => ['The selected parent department is inactive.'],
            ]);
        }

        if ($departmentId === null) {
            return $parent->id;
        }

        $ancestorId = $parent->id;
        $visited = [];

        while ($ancestorId !== null) {
            if ($ancestorId === $departmentId) {
                throw ValidationException::withMessages([
                    'parent_uuid' => ['A department cannot be its own parent or child.'],
                ]);
            }

            if (isset($visited[$ancestorId])) {
                throw ValidationException::withMessages([
                    'parent_uuid' => ['The selected department hierarchy is invalid.'],
                ]);
            }

            $visited[$ancestorId] = true;
            $ancestorId = $this->branchQuery($branch)->whereKey($ancestorId)->value('parent_id');
        }

        return $parent->id;
    }

    /**
     * @param  array<int, array<int, Department>>  $childrenByParentId
     * @param  array<int, string>  $uuidById
     * @param  array<int, bool>  $ancestorIds
     * @return array<int, array<string, mixed>>
     */
    private function treeNodes(array $childrenByParentId, array $uuidById, int $parentId, array $ancestorIds): array
    {
        return array_map(function (Department $department) use ($childrenByParentId, $uuidById, $ancestorIds): array {
            if (isset($ancestorIds[$department->id])) {
                return [];
            }

            $nextAncestorIds = $ancestorIds;
            $nextAncestorIds[$department->id] = true;

            return [
                'uuid' => $department->uuid,
                'name' => $department->name,
                'parent_uuid' => $department->parent_id === null ? null : $uuidById[$department->parent_id] ?? null,
                'status' => $department->status,
                'children' => array_values(array_filter($this->treeNodes($childrenByParentId, $uuidById, $department->id, $nextAncestorIds))),
            ];
        }, $childrenByParentId[$parentId] ?? []);
    }

    private function normalizeName(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    private function throwDuplicateName(QueryException $exception): void
    {
        if ($exception->getCode() === '23505') {
            throw ValidationException::withMessages([
                'name' => ['A department with this name already exists under the selected parent.'],
            ]);
        }
    }
}
