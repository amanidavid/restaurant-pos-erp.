<?php

namespace App\Http\Controllers\Web\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Menu\SyncComboProductRequest;
use App\Http\Resources\Web\Menu\ComboProductResource;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\Menu\ComboProductService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComboProductController extends Controller
{
    public function __construct(private readonly ComboProductService $comboProductService) {}

    public function show(Request $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('syncCombo', [$menuItem, $this->currentBranch()]);

        $combo = $menuItem
            ->comboProduct()
            ->with(['menuItem:id,uuid,name', 'items.componentMenuItem:id,uuid,name,is_combo'])
            ->first();

        if (! $combo) {
            return ApiResponse::notFound(
                message: 'Combo components have not been configured for this menu item.',
            );
        }

        return ApiResponse::resource(
            new ComboProductResource($combo),
            ApiMessages::detailsRetrieved('combo product')
        );
    }

    public function sync(SyncComboProductRequest $request, MenuItem $menuItem): JsonResponse
    {
        $this->authorize('syncCombo', [$menuItem, $this->currentBranch()]);

        $combo = $this->comboProductService->sync($menuItem, $request->validated());

        return ApiResponse::resource(
            new ComboProductResource($combo),
            ApiMessages::updated('combo product')
        );
    }

}
