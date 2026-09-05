<?php
namespace App\Http\Controllers\Web\Pos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\VoidReceiptRequest;
use App\Http\Resources\Web\Pos\ReceiptResource;
use App\Models\Tenant\Order;
use App\Models\Tenant\Receipt;
use App\Services\Tenant\Pos\ReceiptService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ReceiptController extends Controller { public function __construct(private readonly ReceiptService $receipts) {} public function store(Request $request, Order $order): JsonResponse { $branch=$this->currentBranch(); $this->authorize('issueReceipt', [$order,$branch]); return ApiResponse::resource(new ReceiptResource($this->receipts->issue($order,$branch,$request->user('tenant_api')->tenantUser()->firstOrFail())), ApiMessages::created('receipt'),201); } public function show(Receipt $receipt): JsonResponse { $branch=$this->currentBranch(); $this->authorize('view', [$receipt,$branch]); return ApiResponse::resource(new ReceiptResource($receipt->load('order:id,uuid,order_number')),ApiMessages::detailsRetrieved('receipt')); } public function void(VoidReceiptRequest $request, Receipt $receipt): JsonResponse { $branch=$this->currentBranch(); $this->authorize('void', [$receipt,$branch]); return ApiResponse::resource(new ReceiptResource($this->receipts->void($receipt,$branch,$request->user('tenant_api')->tenantUser()->firstOrFail(),$request->validated('reason'))),ApiMessages::updated('receipt')); } }
