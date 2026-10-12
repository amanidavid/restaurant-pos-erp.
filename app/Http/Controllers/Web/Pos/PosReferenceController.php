<?php

namespace App\Http\Controllers\Web\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\IndexPosReferenceRequest;
use App\Http\Resources\Web\Pos\CounterResource;
use App\Http\Resources\Web\Pos\DiningAreaResource;
use App\Http\Resources\Web\Pos\DiningTableResource;
use App\Http\Resources\Web\Pos\PaymentMethodResource;
use App\Http\Resources\Web\Pos\SalesChannelResource;
use App\Http\Resources\Web\Pos\ServiceTypeResource;
use App\Models\Tenant\Counter;
use App\Models\Tenant\DiningArea;
use App\Models\Tenant\DiningTable;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\SalesChannel;
use App\Models\Tenant\ServiceType;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PosReferenceController extends Controller
{
    public function salesChannels(IndexPosReferenceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewSalesChannels', [SalesChannel::class, $branch]);

        $rows = $this->companyQuery(SalesChannel::query(), $request, $branch->company_id)
            ->select(['id', 'uuid', 'company_id', 'name', 'type', 'status'])
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(SalesChannelResource::collection($rows), ApiMessages::listRetrieved('sales channels'));
    }

    public function serviceTypes(IndexPosReferenceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewServiceTypes', [ServiceType::class, $branch]);

        $rows = $this->companyQuery(ServiceType::query(), $request, $branch->company_id)
            ->select(['id', 'uuid', 'company_id', 'name', 'type', 'status'])
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(ServiceTypeResource::collection($rows), ApiMessages::listRetrieved('service types'));
    }

    public function paymentMethods(IndexPosReferenceRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewPaymentMethods', [PaymentMethod::class, $branch]);

        $rows = $this->companyQuery(PaymentMethod::query(), $request, $branch->company_id)
            ->select(['id', 'uuid', 'company_id', 'name', 'method_type', 'requires_provider', 'allows_change', 'status'])
            ->when(! $request->filled('status'), fn (Builder $query) => $query->where('status', 'active'))
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(PaymentMethodResource::collection($rows), ApiMessages::listRetrieved('payment methods'));
    }

    public function counters(IndexPosReferenceRequest $request): JsonResponse
    {
        $this->authorize('viewCounters', [Counter::class, $this->currentBranch()]);

        $rows = $this->branchQuery(Counter::query(), $request)
            ->select(['id', 'uuid', 'branch_id', 'name', 'type', 'status'])
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(CounterResource::collection($rows), ApiMessages::listRetrieved('counters'));
    }

    public function diningAreas(IndexPosReferenceRequest $request): JsonResponse
    {
        $this->authorize('viewDiningAreas', [DiningArea::class, $this->currentBranch()]);

        $rows = $this->branchQuery(DiningArea::query(), $request)
            ->select(['id', 'uuid', 'branch_id', 'name', 'description', 'status'])
            ->withCount('diningTables')
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(DiningAreaResource::collection($rows), ApiMessages::listRetrieved('dining areas'));
    }

    public function diningTables(IndexPosReferenceRequest $request): JsonResponse
    {
        $this->authorize('viewDiningTables', [DiningTable::class, $this->currentBranch()]);

        $rows = $this->branchQuery(DiningTable::query(), $request)
            ->select(['id', 'uuid', 'branch_id', 'dining_area_id', 'name', 'capacity', 'shape', 'status'])
            ->with('diningArea:id,uuid,name')
            ->when($request->filled('dining_area_uuid'), fn (Builder $query) => $query->whereHas(
                'diningArea',
                fn (Builder $area) => $area->where('uuid', $request->string('dining_area_uuid')->toString()),
            ))
            ->simplePaginate($this->perPage($request));

        return ApiResponse::resource(DiningTableResource::collection($rows), ApiMessages::listRetrieved('dining tables'));
    }

    private function companyQuery(Builder $query, IndexPosReferenceRequest $request, int $companyId): Builder
    {
        return $this->filters($query->where('company_id', $companyId), $request);
    }

    private function branchQuery(Builder $query, IndexPosReferenceRequest $request): Builder
    {
        return $this->filters($query->where('branch_id', $this->currentBranchId()), $request);
    }

    private function filters(Builder $query, IndexPosReferenceRequest $request): Builder
    {
        return $query
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', trim($request->string('search')->toString()).'%'))
            ->orderBy('name')
            ->orderBy('id');
    }
}
