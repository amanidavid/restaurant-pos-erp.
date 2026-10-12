<?php

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Finance\CloseCashReconciliationRequest;
use App\Http\Requests\Web\Finance\ReplaceCashCountRequest;
use App\Http\Requests\Web\Finance\StoreCashMovementRequest;
use App\Http\Resources\Web\Finance\CashReconciliationResource;
use App\Http\Resources\Web\Finance\CashMovementResource;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Finance\CashReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;

class CashReconciliationController extends Controller
{
    public function __construct(
        private readonly CashReconciliationService $cashReconciliationService,
    ) {}

    public function index(BusinessDate $businessDay): JsonResponse
    {
        $branch = $this->currentBranch();

        $this->authorize('viewAny', [BusinessDayCashReconciliation::class, $branch]);

        $reconciliations = BusinessDayCashReconciliation::query()
            ->where('business_date_id', $businessDay->id)
            ->where('branch_id', $branch->id)
            ->orderBy('cash_account_id')
            ->simplePaginate(15);

        $this->cashReconciliationService->prepareForResponse($reconciliations->getCollection());

        return ApiResponse::resource(
            CashReconciliationResource::collection($reconciliations),
            'Cash reconciliations retrieved successfully.',
        );
    }

    public function show(BusinessDayCashReconciliation $reconciliation): JsonResponse
    {
        $branch = $this->currentBranch();

        $this->authorize('viewAny', [BusinessDayCashReconciliation::class, $branch]);
        abort_unless($reconciliation->branch_id === $branch->id, 404);

        $this->cashReconciliationService->prepareForResponse(new Collection([$reconciliation]));

        return ApiResponse::resource(
            new CashReconciliationResource($reconciliation),
            'Cash reconciliation retrieved successfully.',
        );
    }

    public function open(BusinessDate $businessDay): JsonResponse
    {
        $branch = $this->currentBranch();

        $this->authorize('create', [BusinessDayCashReconciliation::class, $branch]);

        $reconciliations = $this->cashReconciliationService->openForBusinessDay(
            $branch,
            $businessDay,
            $this->currentTenantUser(),
        );

        $this->cashReconciliationService->prepareForResponse($reconciliations);

        return ApiResponse::resource(
            CashReconciliationResource::collection($reconciliations),
            'Cash reconciliations opened successfully.',
            201,
        );
    }

    public function count(
        ReplaceCashCountRequest $request,
        BusinessDayCashReconciliation $reconciliation,
    ): JsonResponse {
        $branch = $this->currentBranch();

        $this->authorize('update', [$reconciliation, $branch]);

        $updatedReconciliation = $this->cashReconciliationService->replaceCount(
            $reconciliation,
            $branch,
            $request->validated(),
        );

        $this->cashReconciliationService->prepareForResponse(new Collection([$updatedReconciliation]));

        return ApiResponse::resource(
            new CashReconciliationResource($updatedReconciliation),
            'Cash count saved successfully.',
        );
    }

    public function close(
        CloseCashReconciliationRequest $request,
        BusinessDayCashReconciliation $reconciliation,
    ): JsonResponse {
        $branch = $this->currentBranch();

        $this->authorize('close', [$reconciliation, $branch]);

        $closedReconciliation = $this->cashReconciliationService->close(
            $reconciliation,
            $branch,
            $this->currentTenantUser(),
            $request->validated(),
        );

        $this->cashReconciliationService->prepareForResponse(new Collection([$closedReconciliation]));

        return ApiResponse::resource(
            new CashReconciliationResource($closedReconciliation),
            'Cash reconciliation closed successfully.',
        );
    }

    public function movement(
        StoreCashMovementRequest $request,
        CashAccount $cashAccount,
    ): JsonResponse {
        $branch = $this->currentBranch();

        $this->authorize('moveCash', [BusinessDayCashReconciliation::class, $branch]);

        $movement = $this->cashReconciliationService->recordMovement(
            $branch,
            $cashAccount,
            $this->currentTenantUser(),
            $request->validated(),
        );

        return ApiResponse::resource(
            new CashMovementResource($movement->load(['cashAccount:id,uuid,name,currency_code', 'businessDate:id,uuid'])),
            'Cash movement recorded successfully.',
            201,
        );
    }

    private function currentTenantUser(): TenantUser
    {
        return request()
            ->user('tenant_api')
            ->tenantUser()
            ->firstOrFail();
    }
}
