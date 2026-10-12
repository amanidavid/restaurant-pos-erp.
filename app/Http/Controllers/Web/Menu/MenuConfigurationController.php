<?php

namespace App\Http\Controllers\Web\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Menu\StoreModifierGroupRequest;
use App\Http\Requests\Web\Menu\ListBranchMenuItemsRequest;
use App\Http\Requests\Web\Menu\SyncBranchMenuRequest;
use App\Http\Requests\Web\Menu\SyncMenuItemModifiersRequest;
use App\Http\Requests\Web\Menu\UpdateBranchMenuAvailabilityRequest;
use App\Http\Requests\Web\Menu\UpdateModifierGroupRequest;
use App\Http\Resources\Web\Menu\BranchMenuItemResource;
use App\Http\Resources\Web\Menu\ModifierGroupResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ModifierGroup;
use App\Services\Tenant\Menu\MenuConfigurationService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuConfigurationController extends Controller
{
    public function __construct(private readonly MenuConfigurationService $service) {}

    public function modifierGroups(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [ModifierGroup::class, $this->currentBranch()]);

        $groups = ModifierGroup::query()
            ->with([
                'options' => fn ($query) => $query
                    ->with('taxCode:id,uuid,name,rate_percentage')
                    ->orderBy('sort_order'),
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', $this->searchPrefix($request).'%'))
            ->orderBy('name')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(
            ModifierGroupResource::collection($groups),
            ApiMessages::listRetrieved('modifier groups')
        );
    }

    public function storeModifierGroup(StoreModifierGroupRequest $request): JsonResponse
    {
        $this->authorize('create', [ModifierGroup::class, $this->currentBranch()]);

        $group = $this->service->saveModifierGroup($request->validated());

        return ApiResponse::resource(
            new ModifierGroupResource($group),
            ApiMessages::created('modifier group'),
            201
        );
    }

    public function updateModifierGroup(UpdateModifierGroupRequest $request, ModifierGroup $modifierGroup): JsonResponse
    {
        $this->authorize('update', [$modifierGroup, $this->currentBranch()]);

        $group = $this->service->saveModifierGroup($request->validated(), $modifierGroup);

        return ApiResponse::resource(
            new ModifierGroupResource($group),
            ApiMessages::updated('modifier group')
        );
    }

    public function syncModifiers(SyncMenuItemModifiersRequest $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('syncModifiers', [$menuItem, $this->currentBranch()]);

        $this->service->syncItemModifiers($menuItem, $request->validated('modifier_groups'));

        return ApiResponse::success(ApiMessages::updated('menu item modifiers'));
    }

    public function syncBranchMenu(SyncBranchMenuRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('create', [MenuItem::class, $branch]);

        $branchMenuItems = $this->service->syncBranchMenu($branch, $request->validated('items'));

        return ApiResponse::resource(
            BranchMenuItemResource::collection($branchMenuItems),
            ApiMessages::updated('branch menu items')
        );
    }

    public function branchMenuItems(ListBranchMenuItemsRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('viewAny', [MenuItem::class, $branch]);

        $filters = $request->validated();
        $items = $this->service->listBranchMenuItems(
            $branch,
            $this->perPage($request),
            $filters['search'] ?? null,
            $filters['menu_category_uuid'] ?? null,
        );

        return ApiResponse::resource(
            BranchMenuItemResource::collection($items),
            ApiMessages::listRetrieved('branch menu items')
        );
    }

    public function availability(UpdateBranchMenuAvailabilityRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('create', [MenuItem::class, $branch]);

        $branchMenuItems = $this->service->updateAvailability(
            $branch,
            $request->validated('items'),
            $request->validated('reason')
        );

        return ApiResponse::resource(
            BranchMenuItemResource::collection($branchMenuItems),
            ApiMessages::updated('branch menu availability')
        );
    }

    private function searchPrefix(Request $request): string
    {
        return trim((string) $request->input('search'));
    }
}
