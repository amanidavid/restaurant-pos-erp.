<?php

namespace App\Services\Tenant\Pos\Dining;

use App\Models\Tenant\Branch;
use App\Models\Tenant\DiningArea;
use App\Models\Tenant\DiningTable;
use App\Models\Tenant\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiningFloorPlanService
{
    private const ACTIVE_ORDER_STATUSES = ['draft', 'open', 'sent_to_kitchen', 'partially_ready', 'ready', 'served'];

    /**
     * @param array<int, array{name: string, description?: string|null, status?: string, tables?: array<int, array{name: string, capacity: int, shape?: string|null, status?: string}>}> $areas
     * @return Collection<int, DiningArea>
     */
    public function create(Branch $branch, array $areas): Collection
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $areas): Collection {
            Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            $this->assertUniqueNames($branch, $areas);

            try {
                $createdAreas = collect();

                foreach ($areas as $areaData) {
                    $area = DiningArea::query()->create([
                        'branch_id' => $branch->id,
                        'name' => trim($areaData['name']),
                        'description' => $areaData['description'] ?? null,
                        'status' => $areaData['status'] ?? 'active',
                    ]);

                    $tables = collect($areaData['tables'] ?? [])
                        ->map(fn (array $tableData): array => [
                            'uuid' => (string) Str::uuid7(),
                            'branch_id' => $branch->id,
                            'dining_area_id' => $area->id,
                            'name' => trim($tableData['name']),
                            'capacity' => $tableData['capacity'],
                            'shape' => $tableData['shape'] ?? null,
                            'status' => $tableData['status'] ?? 'active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])
                        ->all();

                    if ($tables !== []) {
                        DiningTable::query()->insert($tables);
                    }

                    $createdAreas->push($area);
                }
            } catch (QueryException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    throw ValidationException::withMessages([
                        'areas' => ['Area and table names must be unique within the branch.'],
                    ]);
                }

                throw $exception;
            }

            $createdAreas->each(function (DiningArea $area): void {
                $area->load([
                    'diningTables:id,uuid,dining_area_id,name,capacity,shape,status',
                ]);
                $area->loadCount('diningTables');
            });

            return $createdAreas;
        });
    }

    public function updateArea(DiningArea $area, Branch $branch, array $data): DiningArea
    {
        return DB::connection('tenant')->transaction(function () use ($area, $branch, $data): DiningArea {
            $area = DiningArea::query()
                ->where('branch_id', $branch->id)
                ->whereKey($area->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (($data['status'] ?? $area->status) !== 'active') {
                $hasActiveOrders = Order::query()
                    ->where('branch_id', $branch->id)
                    ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
                    ->whereHas('diningTable', fn ($query) => $query->where('dining_area_id', $area->id))
                    ->exists();

                if ($hasActiveOrders) {
                    throw ValidationException::withMessages([
                        'status' => ['A dining area with active orders cannot be deactivated or placed under maintenance.'],
                    ]);
                }
            }

            if (array_key_exists('name', $data)) {
                $this->assertAreaNameAvailable($branch, $data['name'], $area->id);
            }

            $area->update($data);

            return $area->refresh()
                ->load('diningTables:id,uuid,dining_area_id,name,capacity,shape,status')
                ->loadCount('diningTables');
        });
    }

    public function createTable(Branch $branch, array $data): DiningTable
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $data): DiningTable {
            $area = DiningArea::query()
                ->where('branch_id', $branch->id)
                ->where('uuid', $data['dining_area_uuid'])
                ->lockForUpdate()
                ->first();

            if (! $area) {
                throw ValidationException::withMessages([
                    'dining_area_uuid' => ['The selected dining area does not belong to the current branch.'],
                ]);
            }

            $this->assertTableNameAvailable($branch, $data['name'], 0);

            try {
                $table = DiningTable::query()->create([
                    'branch_id' => $branch->id,
                    'dining_area_id' => $area->id,
                    'name' => $data['name'],
                    'capacity' => $data['capacity'],
                    'shape' => $data['shape'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    throw ValidationException::withMessages([
                        'name' => ['A dining table with this name already exists for this branch.'],
                    ]);
                }

                throw $exception;
            }

            return $table->load('diningArea:id,uuid,name');
        });
    }

    public function showArea(DiningArea $area, Branch $branch): DiningArea
    {
        return DiningArea::query()
            ->where('branch_id', $branch->id)
            ->whereKey($area->id)
            ->with('diningTables:id,uuid,branch_id,dining_area_id,name,capacity,shape,status')
            ->withCount('diningTables')
            ->firstOrFail();
    }

    public function showTable(DiningTable $table, Branch $branch): DiningTable
    {
        return DiningTable::query()
            ->where('branch_id', $branch->id)
            ->whereKey($table->id)
            ->with('diningArea:id,uuid,name')
            ->firstOrFail();
    }

    public function updateTable(DiningTable $table, Branch $branch, array $data): DiningTable
    {
        return DB::connection('tenant')->transaction(function () use ($table, $branch, $data): DiningTable {
            $table = DiningTable::query()
                ->where('branch_id', $branch->id)
                ->whereKey($table->id)
                ->lockForUpdate()
                ->firstOrFail();

            $updates = collect($data)->except('dining_area_uuid')->all();
            $areaId = $table->dining_area_id;

            if (array_key_exists('dining_area_uuid', $data)) {
                $areaId = $data['dining_area_uuid'] === null
                    ? null
                    : DiningArea::query()
                        ->where('branch_id', $branch->id)
                        ->where('uuid', $data['dining_area_uuid'])
                        ->lockForUpdate()
                        ->value('id');

                if ($data['dining_area_uuid'] !== null && $areaId === null) {
                    throw ValidationException::withMessages([
                        'dining_area_uuid' => ['The selected dining area does not belong to the current branch.'],
                    ]);
                }

                $updates['dining_area_id'] = $areaId;
            }

            $isMoving = array_key_exists('dining_area_id', $updates) && $updates['dining_area_id'] !== $table->dining_area_id;
            $isBeingDeactivated = array_key_exists('status', $updates) && $updates['status'] !== 'active';

            if (($isMoving || $isBeingDeactivated) && $this->hasActiveOrders($table, $branch)) {
                throw ValidationException::withMessages([
                    $isMoving ? 'dining_area_uuid' : 'status' => ['A table with active orders cannot be moved, deactivated, or placed under maintenance.'],
                ]);
            }

            if (array_key_exists('name', $updates)) {
                $this->assertTableNameAvailable($branch, $updates['name'], $table->id);
            }

            $table->update($updates);

            return $table->refresh()->load('diningArea:id,uuid,name');
        });
    }

    /**
     * @param array<int, array{name: string, tables?: array<int, array{name: string}>}> $areas
     */
    private function assertUniqueNames(Branch $branch, array $areas): void
    {
        $areaNames = collect($areas)->pluck('name')->map(fn (string $name): string => trim($name));
        $tableNames = collect($areas)
            ->flatMap(fn (array $area): array => $area['tables'] ?? [])
            ->pluck('name')
            ->map(fn (string $name): string => trim($name));

        if ($areaNames->unique()->count() !== $areaNames->count()) {
            throw ValidationException::withMessages([
                'areas' => ['Each dining area name must appear only once in this request.'],
            ]);
        }

        if ($tableNames->unique()->count() !== $tableNames->count()) {
            throw ValidationException::withMessages([
                'areas' => ['Each dining table name must appear only once in this request.'],
            ]);
        }

        $existingArea = DiningArea::query()
            ->where('branch_id', $branch->id)
            ->whereIn('name', $areaNames->all())
            ->exists();
        $existingTable = DiningTable::query()
            ->where('branch_id', $branch->id)
            ->whereIn('name', $tableNames->all())
            ->exists();

        if ($existingArea || $existingTable) {
            throw ValidationException::withMessages([
                'areas' => ['A dining area or table with one of these names already exists for this branch.'],
            ]);
        }
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true);
    }

    private function assertAreaNameAvailable(Branch $branch, string $name, int $ignoreId): void
    {
        if (DiningArea::query()->where('branch_id', $branch->id)->where('name', $name)->where('id', '!=', $ignoreId)->exists()) {
            throw ValidationException::withMessages(['name' => ['A dining area with this name already exists for this branch.']]);
        }
    }

    private function assertTableNameAvailable(Branch $branch, string $name, int $ignoreId): void
    {
        if (DiningTable::query()->where('branch_id', $branch->id)->where('name', $name)->where('id', '!=', $ignoreId)->exists()) {
            throw ValidationException::withMessages(['name' => ['A dining table with this name already exists for this branch.']]);
        }
    }

    private function hasActiveOrders(DiningTable $table, Branch $branch): bool
    {
        return Order::query()
            ->where('branch_id', $branch->id)
            ->where('dining_table_id', $table->id)
            ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
            ->exists();
    }
}
