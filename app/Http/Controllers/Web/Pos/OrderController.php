<?php

namespace App\Http\Controllers\Web\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\AddOrderItemsRequest;
use App\Http\Requests\Web\Pos\ChangeOrderStatusRequest;
use App\Http\Requests\Web\Pos\StoreOrderRequest;
use App\Http\Requests\Web\Pos\SendOrderToKitchenRequest;
use App\Http\Requests\Web\Pos\ServeOrderItemRequest;
use App\Http\Requests\Web\Pos\UpdateOrderItemRequest;
use App\Http\Requests\Web\Pos\UpdateOrderRequest;
use App\Http\Requests\Web\Pos\VoidOrderItemRequest;
use App\Http\Resources\Web\Pos\OrderResource;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Services\Tenant\Pos\OrderService;
use App\Services\Tenant\Pos\KitchenDispatchService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly KitchenDispatchService $kitchenDispatch,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [Order::class, $branch]);
        $orders = Order::query()->where('branch_id', $branch->id)->with(['salesChannel:id,uuid,name', 'serviceType:id,uuid,name', 'counter:id,uuid,name', 'diningTable:id,uuid,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($q) => $q->where('order_number', 'like', trim($request->string('search')->toString()).'%'))
            ->orderByDesc('opened_at')->simplePaginate($this->perPage($request));

        return ApiResponse::resource(OrderResource::collection($orders), ApiMessages::listRetrieved('orders'));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('create', [Order::class, $branch]);
        $order = $this->orders->create($branch, $request->user('tenant_api')->tenantUser()->firstOrFail(), $request->validated());

        return ApiResponse::resource(new OrderResource($order), ApiMessages::created('order'), 201);
    }

    public function show(Order $order): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('view', [$order, $branch]);

        return ApiResponse::resource(new OrderResource($this->orders->details($order)), ApiMessages::detailsRetrieved('order'));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('update', [$order, $branch]);

        return ApiResponse::resource(new OrderResource($this->orders->update($order, $branch, $request->validated())), ApiMessages::updated('order'));
    }

    public function addItems(AddOrderItemsRequest $request, Order $order): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('update', [$order, $branch]);

        return ApiResponse::resource(new OrderResource($this->orders->add($order, $branch, $request->validated())), ApiMessages::updated('order items'));
    }

    public function updateItem(UpdateOrderItemRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('update', [$order, $branch]);

        return ApiResponse::resource(new OrderResource($this->orders->updateItem($order, $orderItem, $branch, $request->validated())), ApiMessages::updated('order item'));
    }

    public function voidItem(VoidOrderItemRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('voidItem', [$order, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();
        $order = $this->orders->voidItem($order, $orderItem, $branch, $actor, $request->validated('reason'));

        return ApiResponse::resource(new OrderResource($order), ApiMessages::updated('order item'));
    }

    public function sendToKitchen(SendOrderToKitchenRequest $request, Order $order): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('sendToKitchen', [$order, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();
        $order = $this->kitchenDispatch->dispatch($order, $branch, $actor);

        return ApiResponse::resource(new OrderResource($order), ApiMessages::updated('order kitchen status'));
    }

    public function serveItem(ServeOrderItemRequest $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('serve', [$order, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        return ApiResponse::resource(new OrderResource($this->orders->serveItem($order, $orderItem, $branch, $actor)), ApiMessages::updated('order item'));
    }

    public function cancel(ChangeOrderStatusRequest $request, Order $order): JsonResponse
    {
        return $this->change($request, $order, 'cancelled', 'cancel');
    }

    public function serve(ChangeOrderStatusRequest $request, Order $order): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('serve', [$order, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        return ApiResponse::resource(new OrderResource($this->orders->serveOrder($order, $branch, $actor)), ApiMessages::updated('order status'));
    }

    public function close(ChangeOrderStatusRequest $request, Order $order): JsonResponse
    {
        return $this->change($request, $order, 'closed', 'close');
    }

    private function change(ChangeOrderStatusRequest $request, Order $order, string $status, string $ability): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize($ability, [$order, $branch]);
        $actor = $request->user('tenant_api')->tenantUser()->firstOrFail();

        return ApiResponse::resource(new OrderResource($this->orders->transition($order, $branch, $actor, $status, $request->validated('reason'))), ApiMessages::updated('order status'));
    }
}
