<?php

namespace App\Http\Controllers\Web\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\StoreOrderPaymentRequest;
use App\Http\Resources\Web\Pos\PaymentResource;
use App\Models\Tenant\Order;
use App\Services\Tenant\Pos\PaymentService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}
    public function store(StoreOrderPaymentRequest $request, Order $order): JsonResponse { $branch = $this->currentBranch(); $this->authorize('collectPayment', [$order, $branch]); $payment = $this->payments->collect($order, $branch, $request->user('tenant_api')->tenantUser()->firstOrFail(), $request->validated()); return ApiResponse::resource(new PaymentResource($payment), ApiMessages::created('payment'), 201); }
    public function index(Request $request, Order $order): JsonResponse { $branch = $this->currentBranch(); $this->authorize('view', [$order, $branch]); $payments = $order->payments()->with('paymentMethod:id,uuid,name,method_type')->orderByDesc('paid_at')->simplePaginate($this->perPage($request)); return ApiResponse::resource(PaymentResource::collection($payments), ApiMessages::listRetrieved('payments')); }
}
