<?php

namespace App\Services\Tenant\Menu;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Company;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\TaxCode;
use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogMasterService
{
    public function unit(array $data, ?UnitOfMeasure $unit = null): UnitOfMeasure
    {
        try {
            return DB::connection('tenant')->transaction(function () use ($data, $unit): UnitOfMeasure {
                $companyId = $this->companyId();

                $this->assertUnitIsUnique($companyId, $data, $unit);

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
            });
        } catch (QueryException $exception) {
            if (! $this->isUnitUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages($this->unitUniqueViolationMessages($exception));
        }
    }

    public function tax(array $data, ?TaxCode $tax = null): TaxCode
    {
        $data['status'] ??= 'active';

        try {
            return DB::connection('tenant')->transaction(function () use ($data, $tax): TaxCode {
                $companyId = $this->companyId();

                $this->assertTaxIsConsistent($companyId, $data, $tax);

                if ($tax) {
                    return tap($tax)->update($data)->refresh();
                }

                return TaxCode::query()->create(['company_id' => $companyId, ...$data]);
            });
        } catch (QueryException $exception) {
            if (! $this->isTaxNameUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => ['This tax code already exists.'],
            ]);
        }
    }

    public function category(array $data, ?MenuCategory $category = null): MenuCategory
    {
        $parentId = $this->id(MenuCategory::class, $data['parent_uuid'] ?? null, 'parent_uuid');

        if ($category && $parentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_uuid' => ['A category cannot be its own parent.'],
            ]);
        }

        try {
            return DB::connection('tenant')->transaction(function () use ($data, $category, $parentId): MenuCategory {
                $companyId = $this->companyId();
                $this->lockCategoryScope($companyId, $parentId, $data['name']);
                $this->assertCategoryIsUnique($companyId, $parentId, $data['name'], $category);

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

                return MenuCategory::query()->create(['company_id' => $companyId, ...$values]);
            });
        } catch (QueryException $exception) {
            if (! $this->isCategoryUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => ['This menu category already exists.'],
            ]);
        }
    }

    public function ingredient(array $data, ?Ingredient $ingredient = null): Ingredient
    {
        try {
            return DB::connection('tenant')->transaction(function () use ($data, $ingredient): Ingredient {
                $companyId = $this->companyId();
                $values = [
                    'name' => $data['name'],
                    'sku' => $data['sku'] ?? null,
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'],
                    'base_unit_id' => $this->id(UnitOfMeasure::class, $data['base_unit_uuid'], 'base_unit_uuid'),
                    'tax_code_id' => $this->id(TaxCode::class, $data['tax_code_uuid'] ?? null, 'tax_code_uuid'),
                ];

                $this->assertIngredientIsUnique($companyId, $values, $ingredient);

                if ($ingredient) {
                    return tap($ingredient)->update($values)->refresh();
                }

                return Ingredient::query()->create(['company_id' => $companyId, ...$values]);
            });
        } catch (QueryException $exception) {
            if (! $this->isIngredientUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages($this->ingredientUniqueViolationMessages($exception));
        }
    }

    public function kitchenSection(array $data, ?KitchenSection $section = null): KitchenSection
    {
        $branchId = $this->id(Branch::class, $data['branch_uuid'] ?? null, 'branch_uuid');

        if ($section && $branchId && $section->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'branch_uuid' => ['A kitchen section cannot be moved to another branch.'],
            ]);
        }

        try {
            return DB::connection('tenant')->transaction(function () use ($data, $section, $branchId): KitchenSection {
                $scopeBranchId = $section?->branch_id ?? $branchId;
                $this->lockKitchenSectionScope($scopeBranchId, $data['name']);
                $this->assertKitchenSectionIsUnique($scopeBranchId, $data['name'], $section);

                $values = [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'sort_order' => $data['sort_order'] ?? 1,
                    'status' => $data['status'] ?? 'active',
                ];

                if ($section) {
                    return tap($section)->update($values)->refresh();
                }

                return KitchenSection::query()->create(['branch_id' => $branchId, ...$values]);
            });
        } catch (QueryException $exception) {
            if (! $this->isKitchenSectionUniqueViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => ['This kitchen section already exists.'],
            ]);
        }
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

    /** @param array<string, mixed> $data */
    private function assertUnitIsUnique(int $companyId, array $data, ?UnitOfMeasure $unit): void
    {
        $symbol = $data['symbol'] ?? null;
        $duplicates = UnitOfMeasure::query()
            ->where('company_id', $companyId)
            ->when($unit, fn ($query) => $query->whereKeyNot($unit->id))
            ->where(function ($query) use ($data, $symbol): void {
                $query->where('name', $data['name']);

                if ($symbol !== null) {
                    $query->orWhere('symbol', $symbol);
                }
            })
            ->get(['name', 'symbol']);

        if ($duplicates->isEmpty()) {
            return;
        }

        $errors = [];

        if ($duplicates->contains('name', $data['name'])) {
            $errors['name'] = ['This unit of measure already exists.'];
        }

        if ($symbol !== null && $duplicates->contains('symbol', $symbol)) {
            $errors['symbol'] = ['This unit symbol already exists.'];
        }

        throw ValidationException::withMessages($errors);
    }

    private function isUnitUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'units_of_measure_');
    }

    /** @return array<string, array<int, string>> */
    private function unitUniqueViolationMessages(QueryException $exception): array
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'units_of_measure_company_name_unique')) {
            return ['name' => ['This unit of measure already exists.']];
        }

        if (str_contains($message, 'units_of_measure_company_symbol_unique')) {
            return ['symbol' => ['This unit symbol already exists.']];
        }

        return ['is_base_unit' => ['Only one base unit is allowed for each unit type.']];
    }

    /** @param array<string, mixed> $data */
    private function assertTaxIsConsistent(int $companyId, array $data, ?TaxCode $tax): void
    {
        $duplicateName = TaxCode::query()
            ->where('company_id', $companyId)
            ->where('name', $data['name'])
            ->when($tax, fn ($query) => $query->whereKeyNot($tax->id))
            ->exists();

        if ($duplicateName) {
            throw ValidationException::withMessages([
                'name' => ['This tax code already exists.'],
            ]);
        }

        if ($data['status'] !== 'active') {
            return;
        }

        $this->lockTaxRule($companyId, $data);

        $hasOverlappingRule = TaxCode::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('rate_percentage', $data['rate_percentage'])
            ->where('is_inclusive', $data['is_inclusive'])
            ->when($tax, fn ($query) => $query->whereKeyNot($tax->id))
            ->where(function ($query) use ($data): void {
                if ($data['effective_to'] ?? null) {
                    $query->where(function ($query) use ($data): void {
                        $query->whereNull('effective_from')
                            ->orWhere('effective_from', '<=', $data['effective_to']);
                    });
                }

                if ($data['effective_from'] ?? null) {
                    $query->where(function ($query) use ($data): void {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $data['effective_from']);
                    });
                }
            })
            ->exists();

        if ($hasOverlappingRule) {
            throw ValidationException::withMessages([
                'rate_percentage' => ['This tax rule already exists for the selected period.'],
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function lockTaxRule(int $companyId, array $data): void
    {
        $rate = number_format((float) $data['rate_percentage'], 4, '.', '');
        $key = "tax-code:{$companyId}:{$rate}:".((int) $data['is_inclusive']);

        DB::connection('tenant')->select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
    }

    private function isTaxNameUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'tax_codes_company_name_unique');
    }

    private function lockCategoryScope(int $companyId, ?int $parentId, string $name): void
    {
        $scope = $parentId ?? 'root';
        $key = "menu-category:{$companyId}:{$scope}:".mb_strtolower($name);

        DB::connection('tenant')->select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
    }

    private function assertCategoryIsUnique(int $companyId, ?int $parentId, string $name, ?MenuCategory $category): void
    {
        $exists = MenuCategory::query()
            ->where('company_id', $companyId)
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'), fn ($query) => $query->where('parent_id', $parentId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($category, fn ($query) => $query->whereKeyNot($category->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['This menu category already exists.'],
            ]);
        }
    }

    private function isCategoryUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'menu_categories_company_parent_name_unique');
    }

    /** @param array<string, mixed> $values */
    private function assertIngredientIsUnique(int $companyId, array $values, ?Ingredient $ingredient): void
    {
        $sku = $values['sku'] ?? null;
        $duplicates = Ingredient::query()
            ->where('company_id', $companyId)
            ->when($ingredient, fn ($query) => $query->whereKeyNot($ingredient->id))
            ->where(function ($query) use ($values, $sku): void {
                $query->where('name', $values['name']);

                if ($sku !== null) {
                    $query->orWhere('sku', $sku);
                }
            })
            ->get(['name', 'sku']);

        if ($duplicates->isEmpty()) {
            return;
        }

        $errors = [];

        if ($duplicates->contains('name', $values['name'])) {
            $errors['name'] = ['This ingredient already exists.'];
        }

        if ($sku !== null && $duplicates->contains('sku', $sku)) {
            $errors['sku'] = ['This ingredient SKU already exists.'];
        }

        throw ValidationException::withMessages($errors);
    }

    private function isIngredientUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && (str_contains($exception->getMessage(), 'ingredients_company_name_unique')
                || str_contains($exception->getMessage(), 'ingredients_company_sku_unique'));
    }

    /** @return array<string, array<int, string>> */
    private function ingredientUniqueViolationMessages(QueryException $exception): array
    {
        if (str_contains($exception->getMessage(), 'ingredients_company_name_unique')) {
            return ['name' => ['This ingredient already exists.']];
        }

        return ['sku' => ['This ingredient SKU already exists.']];
    }

    private function lockKitchenSectionScope(?int $branchId, string $name): void
    {
        $scope = $branchId ?? 'global';
        $key = "kitchen-section:{$scope}:".mb_strtolower($name);

        DB::connection('tenant')->select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
    }

    private function assertKitchenSectionIsUnique(?int $branchId, string $name, ?KitchenSection $section): void
    {
        $exists = KitchenSection::query()
            ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($section, fn ($query) => $query->whereKeyNot($section->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['This kitchen section already exists.'],
            ]);
        }
    }

    private function isKitchenSectionUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'kitchen_sections_branch_name_unique');
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
