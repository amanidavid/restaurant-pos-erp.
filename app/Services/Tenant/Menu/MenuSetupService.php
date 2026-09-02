<?php

namespace App\Services\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BranchMenuItem;
use App\Models\Tenant\Company;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\Recipe;
use App\Models\Tenant\TaxCode;
use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuSetupService
{
    public function createMenuItem(array $data): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($data): MenuItem {
            $item = MenuItem::query()->create([
                'company_id' => $this->companyId(),
                ...$this->menuItemPayload($data),
            ]);

            $this->syncBranches($item, $data['branches']);

            return $item->load(['menuCategory', 'taxCode', 'branchMenuItems.branch', 'branchMenuItems.kitchenSection']);
        });
    }

    public function updateMenuItem(MenuItem $item, array $data): MenuItem
    {
        return DB::connection('tenant')->transaction(function () use ($item, $data): MenuItem {
            $item->update($this->menuItemPayload($data));

            return $item->refresh()->load(['menuCategory', 'taxCode', 'branchMenuItems.branch', 'branchMenuItems.kitchenSection']);
        });
    }

    public function syncRecipe(MenuItem $item, array $data): Recipe
    {
        return DB::connection('tenant')->transaction(function () use ($item, $data): Recipe {
            $ingredientIds = collect($data['ingredients'])->pluck('ingredient_uuid')->unique();
            $ingredients = Ingredient::query()->whereIn('uuid', $ingredientIds)->get()->keyBy('uuid');

            if ($ingredients->count() !== $ingredientIds->count()) {
                throw ValidationException::withMessages([
                    'ingredients' => ['One or more ingredients could not be found.'],
                ]);
            }

            $recipe = Recipe::query()->updateOrCreate(
                ['menu_item_id' => $item->id],
                [
                    'company_id' => $item->company_id,
                    'name' => Str::title(Str::lower(Str::squish($data['name']))),
                    'yield_quantity' => $data['yield_quantity'],
                    'yield_unit_id' => $this->idByUuid(UnitOfMeasure::class, $data['yield_unit_uuid'] ?? null, 'yield_unit_uuid'),
                    'is_active' => true,
                ]
            );

            $recipe->ingredients()->delete();

            foreach ($data['ingredients'] as $row) {
                $recipe->ingredients()->create([
                    'ingredient_id' => $ingredients[$row['ingredient_uuid']]->id,
                    'unit_id' => $this->idByUuid(UnitOfMeasure::class, $row['unit_uuid'] ?? null, 'unit_uuid'),
                    'quantity' => $row['quantity'],
                    'waste_percentage' => $row['waste_percentage'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                    'sort_order' => $row['sort_order'] ?? 1,
                ]);
            }

            return $recipe->load(['yieldUnit', 'ingredients.ingredient', 'ingredients.unit']);
        });
    }

    private function syncBranches(MenuItem $item, array $rows): void
    {
        $branchIds = collect($rows)->pluck('branch_uuid')->unique();
        $branches = Branch::query()->whereIn('uuid', $branchIds)->get()->keyBy('uuid');

        if ($branches->count() !== $branchIds->count()) {
            throw ValidationException::withMessages([
                'branches' => ['One or more selected branches could not be found.'],
            ]);
        }

        $kitchenSections = KitchenSection::query()
            ->whereIn('uuid', collect($rows)->pluck('kitchen_section_uuid')->filter()->unique())
            ->get(['id', 'uuid', 'branch_id'])
            ->keyBy('uuid');

        foreach ($rows as $row) {
            $branch = $branches[$row['branch_uuid']];
            $kitchenSection = $this->nullableKitchenSection($kitchenSections, $row['kitchen_section_uuid'] ?? null);

            if ($kitchenSection && $kitchenSection->branch_id !== $branch->id) {
                throw ValidationException::withMessages([
                    'branches' => ['A kitchen section must belong to its selected branch.'],
                ]);
            }

            BranchMenuItem::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'menu_item_id' => $item->id],
                [
                    'kitchen_section_id' => $kitchenSection?->id,
                    'price' => $row['price'],
                    'cost_price' => $row['cost_price'] ?? 0,
                    'preparation_minutes' => $row['preparation_minutes'] ?? 0,
                    'is_available' => $row['is_available'],
                    'is_visible' => $row['is_visible'],
                    'sort_order' => $row['sort_order'] ?? 1,
                ]
            );
        }
    }

    private function menuItemPayload(array $data): array
    {
        $payload = collect($data)
            ->only(['sku', 'description', 'allows_open_price', 'is_combo', 'is_active'])
            ->all();

        if (array_key_exists('name', $data)) {
            $payload['name'] = Str::title(Str::lower(Str::squish($data['name'])));
        }

        if (array_key_exists('menu_category_uuid', $data)) {
            $payload['menu_category_id'] = $this->idByUuid(MenuCategory::class, $data['menu_category_uuid'], 'menu_category_uuid');
        }

        if (array_key_exists('tax_code_uuid', $data)) {
            $payload['tax_code_id'] = $this->idByUuid(TaxCode::class, $data['tax_code_uuid'], 'tax_code_uuid');
        }

        return $payload;
    }

    private function idByUuid(string $model, ?string $uuid, string $field): ?int
    {
        if (! $uuid) {
            return null;
        }

        $id = $model::query()->where('uuid', $uuid)->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                $field => ['The selected record could not be found.'],
            ]);
        }

        return (int) $id;
    }

    private function nullableKitchenSection(Collection $sections, ?string $uuid): ?KitchenSection
    {
        if (! $uuid) {
            return null;
        }

        if (! $sections->has($uuid)) {
            throw ValidationException::withMessages([
                'kitchen_section_uuid' => ['The selected kitchen section could not be found.'],
            ]);
        }

        return $sections[$uuid];
    }

    private function companyId(): int
    {
        $companyId = Company::query()->orderBy('id')->value('id');

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Company profile must be created before menu setup.'],
            ]);
        }

        return (int) $companyId;
    }
}
