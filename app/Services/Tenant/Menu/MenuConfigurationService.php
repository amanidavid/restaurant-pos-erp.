<?php

namespace App\Services\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BranchMenuItem;
use App\Models\Tenant\Company;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\MenuItemModifierGroup;
use App\Models\Tenant\ModifierGroup;
use App\Models\Tenant\RealtimeEventOutbox;
use App\Models\Tenant\TaxCode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuConfigurationService
{
    public function saveModifierGroup(array $data, ?ModifierGroup $group = null): ModifierGroup
    {
        return DB::connection('tenant')->transaction(function () use ($data, $group): ModifierGroup {
            if ($group) {
                $payload = $this->modifierGroupPayload($data, false);
                $this->validateSelectionRange($data, $group);
                $group->update($payload);
            } else {
                $payload = $this->modifierGroupPayload($data);
                $this->validateSelectionRange($payload);
                $group = ModifierGroup::query()->create($payload + [
                    'company_id' => $this->companyId(),
                ]);
            }

            if (array_key_exists('options', $data)) {
                $this->replaceModifierOptions($group, collect($data['options']));
            }

            return $group->fresh('options.taxCode');
        });
    }

    public function syncItemModifiers(MenuItem $item, array $groups): void
    {
        DB::connection('tenant')->transaction(function () use ($item, $groups): void {
            $ids = $this->uuidIdMap(ModifierGroup::class, collect($groups)->pluck('modifier_group_uuid'));

            if ($ids->count() !== count($groups)) {
                throw ValidationException::withMessages([
                    'modifier_groups' => ['One or more modifier groups could not be found.'],
                ]);
            }

            $item->modifierGroups()->delete();

            foreach ($groups as $row) {
                MenuItemModifierGroup::query()->create([
                    'menu_item_id' => $item->id,
                    'modifier_group_id' => $ids[$row['modifier_group_uuid']],
                    'sort_order' => $row['sort_order'] ?? 1,
                    'is_active' => $row['is_active'],
                ]);
            }
        });
    }

    public function syncBranchMenu(Branch $branch, array $items): EloquentCollection
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $items): EloquentCollection {
            $menuItemIds = $this->uuidIdMap(MenuItem::class, collect($items)->pluck('menu_item_uuid'));
            $kitchenSectionIds = $this->uuidIdMap(
                KitchenSection::class,
                collect($items)->pluck('kitchen_section_uuid')->filter()
            );

            if ($menuItemIds->count() !== count($items)) {
                throw ValidationException::withMessages([
                    'items' => ['One or more menu items could not be found.'],
                ]);
            }

            $this->ensureKitchenSectionsBelongToBranch($branch, $items, $kitchenSectionIds);

            foreach ($items as $row) {
                BranchMenuItem::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'menu_item_id' => $menuItemIds[$row['menu_item_uuid']],
                    ],
                    [
                        'kitchen_section_id' => $this->nullableMapValue(
                            $kitchenSectionIds,
                            $row['kitchen_section_uuid'] ?? null,
                            'kitchen_section_uuid'
                        ),
                        'price' => $row['price'],
                        'cost_price' => $row['cost_price'] ?? 0,
                        'preparation_minutes' => $row['preparation_minutes'] ?? 0,
                        'is_available' => $row['is_available'],
                        'is_visible' => $row['is_visible'],
                        'sort_order' => $row['sort_order'] ?? 1,
                    ]
                );
            }

            return $this->branchMenuItems($branch, collect($items)->pluck('menu_item_uuid'));
        });
    }

    public function updateAvailability(Branch $branch, array $items, ?string $reason = null): EloquentCollection
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $items, $reason): EloquentCollection {
            $rows = BranchMenuItem::query()
                ->where('branch_id', $branch->id)
                ->whereIn('uuid', collect($items)->pluck('branch_menu_item_uuid'))
                ->lockForUpdate()
                ->get()
                ->keyBy('uuid');

            if ($rows->count() !== count($items)) {
                throw ValidationException::withMessages([
                    'items' => ['One or more branch menu items are not available in this branch.'],
                ]);
            }

            foreach ($items as $row) {
                $branchMenuItem = $rows[$row['branch_menu_item_uuid']];
                $wasAvailable = $branchMenuItem->is_available;
                $isAvailable = (bool) $row['is_available'];

                $branchMenuItem->update([
                    'is_available' => $row['is_available'],
                ]);

                if ($wasAvailable !== $isAvailable) {
                    $this->recordAvailabilityOutboxEvent($branch, $branchMenuItem, $isAvailable, $reason);
                }
            }

            return $this->branchMenuItems($branch, collect($items)->pluck('branch_menu_item_uuid'), 'uuid');
        });
    }

    private function modifierGroupPayload(array $data, bool $isCreate = true): array
    {
        $payload = collect($data)
            ->only(['name', 'description', 'min_select', 'max_select', 'is_required', 'status'])
            ->all();

        if (array_key_exists('name', $payload)) {
            $payload['name'] = Str::title(Str::lower(Str::squish($payload['name'])));
        }

        if ($isCreate && ! array_key_exists('status', $payload)) {
            $payload['status'] = 'active';
        }

        return $payload;
    }

    private function validateSelectionRange(array $data, ?ModifierGroup $group = null): void
    {
        $minSelect = $data['min_select'] ?? $group?->min_select;
        $maxSelect = $data['max_select'] ?? $group?->max_select;

        if ($maxSelect < $minSelect) {
            throw ValidationException::withMessages([
                'max_select' => ['Maximum selection must be greater than or equal to minimum selection.'],
            ]);
        }
    }

    private function replaceModifierOptions(ModifierGroup $group, Collection $options): void
    {
        if ($options->where('is_default', true)->where('is_active', true)->count() > 1) {
            throw ValidationException::withMessages([
                'options' => ['Only one active option can be the default for one modifier group.'],
            ]);
        }

        $taxCodeIds = $this->uuidIdMap(TaxCode::class, $options->pluck('tax_code_uuid')->filter());

        $group->options()->delete();

        foreach ($options as $option) {
            $group->options()->create([
                'tax_code_id' => $this->nullableMapValue($taxCodeIds, $option['tax_code_uuid'] ?? null, 'tax_code_uuid'),
                'name' => Str::title(Str::lower(Str::squish($option['name']))),
                'price_delta' => $option['price_delta'],
                'is_default' => $option['is_default'] ?? false,
                'is_active' => $option['is_active'],
                'sort_order' => $option['sort_order'] ?? 1,
            ]);
        }
    }

    private function ensureKitchenSectionsBelongToBranch(Branch $branch, array $items, Collection $kitchenSectionIds): void
    {
        $selectedIds = collect($items)
            ->pluck('kitchen_section_uuid')
            ->filter()
            ->map(fn (string $uuid): int => $kitchenSectionIds[$uuid] ?? 0)
            ->filter()
            ->values();

        if ($selectedIds->isEmpty()) {
            return;
        }

        $validCount = KitchenSection::query()
            ->where('branch_id', $branch->id)
            ->whereIn('id', $selectedIds)
            ->count();

        if ($validCount !== $selectedIds->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => ['One or more kitchen sections do not belong to this branch.'],
            ]);
        }
    }

    private function branchMenuItems(Branch $branch, Collection $values, string $column = 'menu_uuid'): EloquentCollection
    {
        return BranchMenuItem::query()
            ->with([
                'menuItem:id,uuid,name,is_combo',
                'kitchenSection:id,uuid,name',
            ])
            ->where('branch_id', $branch->id)
            ->when(
                $column === 'uuid',
                fn ($query) => $query->whereIn('uuid', $values),
                fn ($query) => $query->whereHas('menuItem', fn ($menu) => $menu->whereIn('uuid', $values))
            )
            ->orderBy('sort_order')
            ->get();
    }

    private function recordAvailabilityOutboxEvent(
        Branch $branch,
        BranchMenuItem $branchMenuItem,
        bool $isAvailable,
        ?string $reason
    ): void {
        RealtimeEventOutbox::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'branch_menu_item_id' => $branchMenuItem->id,
            'event_type' => 'menu_item.availability_changed',
            'channel_name' => 'tenant.branch.'.$branch->uuid.'.menu',
            'visibility_scope' => 'branch',
            'payload' => [
                'branch_uuid' => $branch->uuid,
                'branch_menu_item_uuid' => $branchMenuItem->uuid,
                'is_available' => $isAvailable,
                'reason' => $reason,
            ],
            'idempotency_key' => 'menu-availability:'.$branchMenuItem->uuid.':'.Str::uuid7(),
            'priority' => 4,
            'status' => 'pending',
            'available_at' => now(),
        ]);
    }

    private function uuidIdMap(string $model, Collection $uuids): Collection
    {
        $uniqueUuids = $uuids->filter()->unique()->values();

        if ($uniqueUuids->isEmpty()) {
            return collect();
        }

        return $model::query()
            ->whereIn('uuid', $uniqueUuids)
            ->pluck('id', 'uuid');
    }

    private function nullableMapValue(Collection $map, ?string $uuid, string $field): ?int
    {
        if (! $uuid) {
            return null;
        }

        if (! $map->has($uuid)) {
            throw ValidationException::withMessages([
                $field => ['The selected record could not be found.'],
            ]);
        }

        return $map[$uuid];
    }

    private function companyId(): int
    {
        $companyId = Company::query()->orderBy('id')->value('id');

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Company profile must be created before menu configuration.'],
            ]);
        }

        return $companyId;
    }
}
