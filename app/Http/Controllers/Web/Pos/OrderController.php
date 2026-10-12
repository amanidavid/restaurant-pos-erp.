<?php

namespace App\Http\Controllers\Web\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\AddOrderItemsRequest;
use App\Http\Requests\Web\Pos\ChangeOrderStatusRequest;
use App\Http\Requests\Web\Pos\IndexOrdersRequest;
use App\Http\Requests\Web\Pos\StoreOrderRequest;
use App\Http\Requests\Web\Pos\SendOrderToKitchenRequest;
use App\Http\Requests\Web\Pos\ServeOrderItemRequest;
use App\Http\Requests\Web\Pos\UpdateOrderItemRequest;
use App\Http\Requests\Web\Pos\UpdateOrderRequest;
use App\Http\Requests\Web\Pos\VoidOrderItemRequest;
use App\Http\Resources\Web\Pos\OrderCollection;
use App\Http\Resources\Web\Pos\OrderResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Services\Tenant\Pos\OrderService;
use App\Services\Tenant\Pos\KitchenDispatchService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const ORDER_STATUSES = ['draft', 'open', 'sent_to_kitchen', 'partially_ready', 'ready', 'served', 'closed', 'cancelled'];

    public function __construct(
        private readonly OrderService $orders,
        private readonly KitchenDispatchService $kitchenDispatch,
    ) {}

    public function index(IndexOrdersRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [Order::class, $branch]);
        $orders = $this->ordersQuery($branch, $request)
            ->withCount('items')
            ->with([
                'salesChannel:id,uuid,name', 'serviceType:id,uuid,name', 'counter:id,uuid,name',
                'diningTable:id,uuid,name', 'customer:id,uuid,name',
                'itemsPreview:id,uuid,order_id,item_name_snapshot,quantity,notes,status',
            ])
            ->orderByDesc('opened_at')
            ->paginate($this->perPage($request));

        return ApiResponse::resource(
            new OrderCollection($orders, $this->statusCounts($branch, $request)),
            ApiMessages::listRetrieved('orders'),
        );
    }

    private function ordersQuery(Branch $branch, Request $request, bool $includeStatusFilter = true): Builder
    {
        return Order::query()
            ->where('branch_id', $branch->id)
            ->when($includeStatusFilter && $request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('order_number', 'like', trim($request->string('search')->toString()).'%'))
            ->when($request->filled('month_from'), fn (Builder $query) => $query->where('opened_at', '>=', $this->monthStart($request->string('month_from')->toString())))
            ->when($request->filled('month_to'), fn (Builder $query) => $query->where('opened_at', '<', $this->monthStart($request->string('month_to')->toString())->addMonth()));
    }

    private function monthStart(string $month): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
    }

    /** @return array<string, int> */
    private function statusCounts(Branch $branch, Request $request): array
    {
        $counts = array_fill_keys(self::ORDER_STATUSES, 0);
        $rows = $this->ordersQuery($branch, $request, false)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($rows as $status => $count) {
            $counts[$status] = (int) $count;
        }

        return ['all' => array_sum($counts)] + $counts;
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
