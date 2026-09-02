<?php

namespace App\Services\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\TaxCode;
use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Validation\ValidationException;

class CatalogMasterService
{
    public function unit(array $data, ?UnitOfMeasure $unit = null): UnitOfMeasure
    {
        $companyId = $this->companyId();

        if ($data['is_base_unit'] ?? false) {
            UnitOfMeasure::query()
                ->where('company_id', $companyId)
                ->where('unit_type', $data['unit_type'])
                ->where('is_base_unit', true)
                ->when($unit, fn ($query) => $query->whereKeyNot($unit->id))
                ->update(['is_base_unit' => false]);
        }

        if ($unit) {
            return tap($unit)->update($data)->refresh();
        }

        return UnitOfMeasure::query()->create(['company_id' => $companyId, ...$data]);
    }

    public function tax(array $data, ?TaxCode $tax = null): TaxCode
    {
        if ($tax) {
            return tap($tax)->update($data)->refresh();
        }

        return TaxCode::query()->create(['company_id' => $this->companyId(), ...$data]);
    }

    public function category(array $data, ?MenuCategory $category = null): MenuCategory
    {
        $parentId = $this->id(MenuCategory::class, $data['parent_uuid'] ?? null, 'parent_uuid');

        if ($category && $parentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_uuid' => ['A category cannot be its own parent.'],
            ]);
        }

        $values = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'parent_id' => $parentId,
        ];

        if ($category) {
            return tap($category)->update($values)->refresh();
        }

        return MenuCategory::query()->create(['company_id' => $this->companyId(), ...$values]);
    }

    public function ingredient(array $data, ?Ingredient $ingredient = null): Ingredient
    {
        $values = [
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'],
            'base_unit_id' => $this->id(UnitOfMeasure::class, $data['base_unit_uuid'], 'base_unit_uuid'),
            'tax_code_id' => $this->id(TaxCode::class, $data['tax_code_uuid'] ?? null, 'tax_code_uuid'),
        ];

        if ($ingredient) {
            return tap($ingredient)->update($values)->refresh();
        }

        return Ingredient::query()->create(['company_id' => $this->companyId(), ...$values]);
    }

    public function kitchenSection(array $data, ?KitchenSection $section = null): KitchenSection
    {
        $branchId = $this->id(Branch::class, $data['branch_uuid'] ?? null, 'branch_uuid');

        $values = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 1,
            'status' => $data['status'] ?? 'active',
        ];

        if (! $section) {
            $values['branch_id'] = $branchId;
        }

        if ($section && $branchId && $section->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'branch_uuid' => ['A kitchen section cannot be moved to another branch.'],
            ]);
        }

        if ($section) {
            return tap($section)->update($values)->refresh();
        }

        return KitchenSection::query()->create($values);
    }

    private function companyId(): int
    {
        $companyId = Company::query()->orderBy('id')->value('id');

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Company profile must be created before menu configuration.'],
            ]);
        }

        return (int) $companyId;
    }

    private function id(string $model, ?string $uuid, string $field): ?int
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
}
