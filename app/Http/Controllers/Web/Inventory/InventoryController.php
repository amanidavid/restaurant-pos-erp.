<?php

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\IndexStockBatchRequest;
use App\Http\Requests\Web\Inventory\IndexStockAdjustmentRequest;
use App\Http\Requests\Web\Inventory\IndexStockCountRequest;
use App\Http\Requests\Web\Inventory\IndexStockMovementRequest;
use App\Http\Requests\Web\Inventory\IndexStockTransferRequest;
use App\Http\Requests\Web\Inventory\IndexWarehouseRequest;
use App\Http\Requests\Web\Inventory\StockSummaryRequest;
use App\Http\Requests\Web\Inventory\StoreStockAdjustmentRequest;
use App\Http\Requests\Web\Inventory\StoreStockCountRequest;
use App\Http\Requests\Web\Inventory\StoreStockTransferRequest;
use App\Http\Requests\Web\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Web\Inventory\UpdateStockAdjustmentRequest;
use App\Http\Requests\Web\Inventory\UpdateStockCountRequest;
use App\Http\Requests\Web\Inventory\UpdateStockTransferRequest;
use App\Http\Requests\Web\Inventory\UpdateWarehouseRequest;
use App\Http\Resources\Web\Inventory\StockAdjustmentResource;
use App\Http\Resources\Web\Inventory\StockBatchResource;
use App\Http\Resources\Web\Inventory\StockCountResource;
use App\Http\Resources\Web\Inventory\StockMovementResource;
use App\Http\Resources\Web\Inventory\StockSummaryResource;
use App\Http\Resources\Web\Inventory\StockTransferResource;
use App\Http\Resources\Web\Inventory\WarehouseResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\StockAdjustment;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\Warehouse;
use App\Services\Tenant\Inventory\InventoryService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function warehouses(IndexWarehouseRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [Warehouse::class, $branch]);

        $warehouses = $this->inventory->paginateWarehouses(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(WarehouseResource::collection($warehouses), ApiMessages::listRetrieved('warehouses'));
    }

    public function storeWarehouse(StoreWarehouseRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('create', [Warehouse::class, $branch]);
        $warehouse = $this->inventory->createWarehouse($branch, $request->validated());

        return ApiResponse::resource(new WarehouseResource($warehouse), ApiMessages::created('warehouse'), 201);
    }

    public function updateWarehouse(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse = $this->inventory->updateWarehouse($warehouse, $request->validated());

        return ApiResponse::resource(new WarehouseResource($warehouse), ApiMessages::updated('warehouse'));
    }

    public function batches(IndexStockBatchRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [Warehouse::class, $branch]);

        $batches = $this->inventory->paginateStockBatches(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(StockBatchResource::collection($batches), ApiMessages::listRetrieved('stock batches'));
    }

    public function movements(IndexStockMovementRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [Warehouse::class, $branch]);

        $movements = $this->inventory->paginateStockMovements(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(StockMovementResource::collection($movements), ApiMessages::listRetrieved('stock movements'));
    }

    public function summary(StockSummaryRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [Warehouse::class, $branch]);

        $summary = $this->inventory->paginateStockSummary(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(StockSummaryResource::collection($summary), ApiMessages::listRetrieved('stock summary'));
    }

    public function adjustments(IndexStockAdjustmentRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [StockAdjustment::class, $branch]);

        $adjustments = $this->inventory->paginateStockAdjustments(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(StockAdjustmentResource::collection($adjustments), ApiMessages::listRetrieved('stock adjustments'));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('create', [StockAdjustment::class, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $adjustment = $this->inventory->createStockAdjustment($branch, $actor, $request->validated());

        return ApiResponse::resource(new StockAdjustmentResource($adjustment), ApiMessages::created('stock adjustment'), 201);
    }

    public function showAdjustment(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('view', $stockAdjustment);

        return ApiResponse::resource(
            new StockAdjustmentResource($this->inventory->loadStockAdjustment($stockAdjustment)),
            ApiMessages::detailsRetrieved('stock adjustment')
        );
    }

    public function updateAdjustment(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('update', $stockAdjustment);

        $adjustment = $this->inventory->updateStockAdjustment($stockAdjustment, $request->validated());

        return ApiResponse::resource(new StockAdjustmentResource($adjustment), ApiMessages::updated('stock adjustment'));
    }

    public function postAdjustment(Request $request, StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('post', $stockAdjustment);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $adjustment = $this->inventory->postStockAdjustment($stockAdjustment, $actor);

        return ApiResponse::resource(new StockAdjustmentResource($adjustment), 'Stock adjustment posted successfully.');
    }

    public function transfers(IndexStockTransferRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [StockTransfer::class, $branch]);

        $transfers = $this->inventory->paginateStockTransfers(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(StockTransferResource::collection($transfers), ApiMessages::listRetrieved('stock transfers'));
    }

    public function storeTransfer(StoreStockTransferRequest $request): JsonResponse
    {
        $fromBranch = $this->resolveBranch($request, 'from_branch_uuid');
        $this->authorize('create', [StockTransfer::class, $fromBranch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $transfer = $this->inventory->createStockTransfer($fromBranch, $actor, $request->validated());

        return ApiResponse::resource(new StockTransferResource($transfer), ApiMessages::created('stock transfer'), 201);
    }

    public function showTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('view', $stockTransfer);

        return ApiResponse::resource(
            new StockTransferResource($this->inventory->loadStockTransfer($stockTransfer)),
            ApiMessages::detailsRetrieved('stock transfer')
        );
    }

    public function updateTransfer(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('update', $stockTransfer);

        $transfer = $this->inventory->updateStockTransfer($stockTransfer, $request->validated());

        return ApiResponse::resource(new StockTransferResource($transfer), ApiMessages::updated('stock transfer'));
    }

    public function dispatchTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('dispatch', $stockTransfer);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $transfer = $this->inventory->dispatchStockTransfer($stockTransfer, $actor);

        return ApiResponse::resource(new StockTransferResource($transfer), 'Stock transfer dispatched successfully.');
    }

    public function receiveTransfer(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('receive', $stockTransfer);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $transfer = $this->inventory->receiveStockTransfer($stockTransfer, $actor);

        return ApiResponse::resource(new StockTransferResource($transfer), 'Stock transfer received successfully.');
    }

    public function counts(IndexStockCountRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('viewAny', [StockCount::class, $branch]);

        $counts = $this->inventory->paginateStockCounts(
            $branch,
            $request->validated(),
            $this->perPage($request)
        );

        return ApiResponse::resource(StockCountResource::collection($counts), ApiMessages::listRetrieved('stock counts'));
    }

    public function storeCount(StoreStockCountRequest $request): JsonResponse
    {
        $branch = $this->resolveBranch($request);
        $this->authorize('create', [StockCount::class, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $count = $this->inventory->createStockCount($branch, $actor, $request->validated());

        return ApiResponse::resource(new StockCountResource($count), ApiMessages::created('stock count'), 201);
    }

    public function showCount(Request $request, StockCount $stockCount): JsonResponse
    {
        $this->authorize('view', $stockCount);

        return ApiResponse::resource(
            new StockCountResource($this->inventory->loadStockCount($stockCount)),
            ApiMessages::detailsRetrieved('stock count')
        );
    }

    public function updateCount(UpdateStockCountRequest $request, StockCount $stockCount): JsonResponse
    {
        $this->authorize('update', $stockCount);

        $count = $this->inventory->updateStockCount($stockCount, $request->validated());

        return ApiResponse::resource(new StockCountResource($count), ApiMessages::updated('stock count'));
    }

    public function postCount(Request $request, StockCount $stockCount): JsonResponse
    {
        $this->authorize('post', $stockCount);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        $count = $this->inventory->postStockCount($stockCount, $actor);

        return ApiResponse::resource(new StockCountResource($count), 'Stock count posted successfully.');
    }

    private function resolveBranch(Request $request, string $branchUuidKey = 'branch_uuid'): Branch
    {
        $branchUuid = $request->input($branchUuidKey);

        $branch = Branch::query()
            ->select(['id', 'uuid', 'company_id', 'name', 'status'])
            ->when(
                $branchUuid,
                fn ($query) => $query->where('uuid', $branchUuid),
                fn ($query) => $query->whereKey((int) app('currentBranchId'))
            )
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                $branchUuidKey => ['The selected branch could not be found.'],
            ]);
        }

        return $branch;
    }

}
