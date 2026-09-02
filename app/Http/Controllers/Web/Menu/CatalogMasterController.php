<?php

namespace App\Http\Controllers\Web\Menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\Menu\IngredientResource;
use App\Http\Resources\Web\Menu\KitchenSectionResource;
use App\Http\Resources\Web\Menu\MenuCategoryResource;
use App\Http\Resources\Web\Menu\TaxCodeResource;
use App\Http\Resources\Web\Menu\UnitOfMeasureResource;
use App\Models\Tenant\Ingredient;
use App\Models\Tenant\KitchenSection;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\TaxCode;
use App\Models\Tenant\UnitOfMeasure;
use App\Services\Tenant\Menu\CatalogMasterService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogMasterController extends Controller
{
    public function __construct(private readonly CatalogMasterService $service) {}

    public function units(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [UnitOfMeasure::class, $this->currentBranch()]);

        $units = UnitOfMeasure::query()
            ->select(['id', 'uuid', 'name', 'symbol', 'unit_type', 'base_factor', 'is_base_unit', 'status'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($query) => $this->searchByName($query, $request))
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(UnitOfMeasureResource::collection($units), ApiMessages::listRetrieved('units of measure'));
    }

    public function taxes(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [TaxCode::class, $this->currentBranch()]);

        $taxes = TaxCode::query()
            ->select(['id', 'uuid', 'name', 'rate_percentage', 'is_inclusive', 'status', 'effective_from', 'effective_to'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($query) => $this->searchByName($query, $request))
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(TaxCodeResource::collection($taxes), ApiMessages::listRetrieved('tax codes'));
    }

    public function categories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [MenuCategory::class, $this->currentBranch()]);

        $categories = MenuCategory::query()
            ->select(['id', 'uuid', 'parent_id', 'name', 'description', 'sort_order', 'status'])
            ->with('parent:id,uuid,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('parent_uuid'), fn ($query) => $query->whereHas(
                'parent',
                fn ($parent) => $parent->where('uuid', $request->input('parent_uuid'))
            ))
            ->when($request->filled('search'), fn ($query) => $this->searchByName($query, $request))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(MenuCategoryResource::collection($categories), ApiMessages::listRetrieved('menu categories'));
    }

    public function ingredients(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [Ingredient::class, $this->currentBranch()]);

        $ingredients = Ingredient::query()
            ->select(['id', 'uuid', 'tax_code_id', 'base_unit_id', 'sku', 'name', 'description', 'is_active'])
            ->with(['baseUnit:id,uuid,name,symbol', 'taxCode:id,uuid,name'])
            ->when($request->filled('is_active'), fn ($query) => $query->where(
                'is_active',
                filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            ))
            ->when($request->filled('base_unit_uuid'), fn ($query) => $query->whereHas(
                'baseUnit',
                fn ($unit) => $unit->where('uuid', $request->input('base_unit_uuid'))
            ))
            ->when($request->filled('search'), fn ($query) => $this->searchByName($query, $request))
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(IngredientResource::collection($ingredients), ApiMessages::listRetrieved('ingredients'));
    }

    public function kitchenSections(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [KitchenSection::class, $this->currentBranch()]);

        $sections = KitchenSection::query()
            ->select(['id', 'uuid', 'branch_id', 'name', 'description', 'sort_order', 'status'])
            ->with('branch:id,uuid,name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('branch_uuid'), fn ($query) => $query->whereHas(
                'branch',
                fn ($branch) => $branch->where('uuid', $request->input('branch_uuid'))
            ))
            ->when($request->filled('search'), fn ($query) => $this->searchByName($query, $request))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(KitchenSectionResource::collection($sections), ApiMessages::listRetrieved('kitchen sections'));
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $this->authorize('create', [UnitOfMeasure::class, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->unit($data),
            $this->unitRules(),
            UnitOfMeasureResource::class,
            'unit of measure',
            201
        );
    }

    public function updateUnit(Request $request, UnitOfMeasure $unit): JsonResponse
    {
        $this->authorize('update', [$unit, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->unit(array_merge(
                $unit->only(['name', 'symbol', 'unit_type', 'base_factor', 'is_base_unit', 'status']),
                $data
            ), $unit),
            $this->unitRules(false),
            UnitOfMeasureResource::class,
            'unit of measure'
        );
    }

    public function storeTax(Request $request): JsonResponse
    {
        $this->authorize('create', [TaxCode::class, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->tax($data),
            $this->taxRules(),
            TaxCodeResource::class,
            'tax code',
            201
        );
    }

    public function updateTax(Request $request, TaxCode $taxCode): JsonResponse
    {
        $this->authorize('update', [$taxCode, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->tax(array_merge(
                $taxCode->only(['name', 'rate_percentage', 'is_inclusive', 'status', 'effective_from', 'effective_to']),
                $data
            ), $taxCode),
            $this->taxRules(false),
            TaxCodeResource::class,
            'tax code'
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('create', [MenuCategory::class, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->category($data),
            $this->categoryRules(),
            MenuCategoryResource::class,
            'menu category',
            201
        );
    }

    public function updateCategory(Request $request, MenuCategory $menuCategory): JsonResponse
    {
        $this->authorize('update', [$menuCategory, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->category(array_merge(
                $menuCategory->only(['name', 'description', 'sort_order', 'status']),
                ['parent_uuid' => $menuCategory->parent?->uuid],
                $data
            ), $menuCategory),
            $this->categoryRules(false),
            MenuCategoryResource::class,
            'menu category'
        );
    }

    public function storeIngredient(Request $request): JsonResponse
    {
        $this->authorize('create', [Ingredient::class, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->ingredient($data),
            $this->ingredientRules(),
            IngredientResource::class,
            'ingredient',
            201
        );
    }

    public function updateIngredient(Request $request, Ingredient $ingredient): JsonResponse
    {
        $this->authorize('update', [$ingredient, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->ingredient(array_merge(
                $ingredient->only(['name', 'sku', 'description', 'is_active']),
                [
                    'base_unit_uuid' => $ingredient->baseUnit?->uuid,
                    'tax_code_uuid' => $ingredient->taxCode?->uuid,
                ],
                $data
            ), $ingredient),
            $this->ingredientRules(false),
            IngredientResource::class,
            'ingredient'
        );
    }

    public function storeKitchenSection(Request $request): JsonResponse
    {
        $this->authorize('create', [KitchenSection::class, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->kitchenSection($data),
            $this->kitchenRules(),
            KitchenSectionResource::class,
            'kitchen section',
            201
        );
    }

    public function updateKitchenSection(Request $request, KitchenSection $kitchenSection): JsonResponse
    {
        $this->authorize('update', [$kitchenSection, $this->currentBranch()]);

        return $this->save(
            $request,
            fn (array $data) => $this->service->kitchenSection(array_merge(
                $kitchenSection->only(['name', 'description', 'sort_order', 'status']),
                ['branch_uuid' => $kitchenSection->branch?->uuid],
                $data
            ), $kitchenSection),
            $this->kitchenRules(false),
            KitchenSectionResource::class,
            'kitchen section'
        );
    }

    private function save(
        Request $request,
        callable $callback,
        array $rules,
        string $resourceClass,
        string $label,
        int $status = 200
    ): JsonResponse {
        $data = $request->validate($rules);

        if (isset($data['name'])) {
            $data['name'] = Str::title(Str::lower(Str::squish($data['name'])));
        }

        $model = $callback($data);

        return ApiResponse::resource(
            new $resourceClass($model),
            $status === 201 ? ApiMessages::created($label) : ApiMessages::updated($label),
            $status
        );
    }

    private function unitRules(bool $create = true): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:80'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'unit_type' => [$create ? 'required' : 'sometimes', 'in:count,weight,volume,length,time,other'],
            'base_factor' => [$create ? 'required' : 'sometimes', 'decimal:0,6', 'gt:0'],
            'is_base_unit' => [$create ? 'required' : 'sometimes', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    private function taxRules(bool $create = true): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:120'],
            'rate_percentage' => [$create ? 'required' : 'sometimes', 'decimal:0,4', 'min:0'],
            'is_inclusive' => [$create ? 'required' : 'sometimes', 'boolean'],
            'status' => ['nullable', 'in:active,inactive'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    private function categoryRules(bool $create = true): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:120'],
            'parent_uuid' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    private function ingredientRules(bool $create = true): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:60'],
            'base_unit_uuid' => [$create ? 'required' : 'sometimes', 'uuid'],
            'tax_code_uuid' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => [$create ? 'required' : 'sometimes', 'boolean'],
        ];
    }

    private function kitchenRules(bool $create = true): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:120'],
            'branch_uuid' => [$create ? 'required' : 'sometimes', 'nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
