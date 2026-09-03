<?php

namespace App\Http\Controllers\Web\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Menu\StoreMenuItemRequest;
use App\Http\Requests\Web\Menu\SyncRecipeRequest;
use App\Http\Requests\Web\Menu\UpdateMenuItemRequest;
use App\Http\Resources\Web\Menu\MenuItemResource;
use App\Http\Resources\Web\Menu\RecipeResource;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\Menu\MenuSetupService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function __construct(private readonly MenuSetupService $menuSetupService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [MenuItem::class, $this->currentBranch()]);

        $items = MenuItem::query()
            ->with(['menuCategory:id,uuid,name', 'taxCode:id,uuid,name'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', trim((string) $request->input('search')).'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(
            MenuItemResource::collection($items),
            ApiMessages::listRetrieved('menu items')
        );
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $this->authorize('create', [MenuItem::class, $this->currentBranch()]);

        $menuItem = $this->menuSetupService->createMenuItem($request->validated());

        return ApiResponse::resource(
            new MenuItemResource($menuItem),
            ApiMessages::created('menu item'),
            201
        );
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('update', [$menuItem, $this->currentBranch()]);

        $menuItem = $this->menuSetupService->updateMenuItem($menuItem, $request->validated());

        return ApiResponse::resource(
            new MenuItemResource($menuItem),
            ApiMessages::updated('menu item')
        );
    }

    public function recipe(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('viewRecipe', [$menuItem, $this->currentBranch()]);

        $recipe = $menuItem->recipe()->with(['yieldUnit', 'ingredients.ingredient', 'ingredients.unit'])->first();

        if (! $recipe) {
            return ApiResponse::notFound(
                message: 'A recipe has not been configured for this menu item.',
            );
        }

        return ApiResponse::resource(
            new RecipeResource($recipe),
            ApiMessages::detailsRetrieved('recipe')
        );
    }

    public function syncRecipe(SyncRecipeRequest $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('syncRecipe', [$menuItem, $this->currentBranch()]);

        $recipe = $this->menuSetupService->syncRecipe($menuItem, $request->validated());

        return ApiResponse::resource(
            new RecipeResource($recipe),
            ApiMessages::updated('recipe')
        );
    }

}
