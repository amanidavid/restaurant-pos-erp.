<?php

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Reports\HistoryPageRequest;
use App\Http\Requests\Web\Reports\HistoryRequest;
use App\Http\Resources\Web\Finance\CashAccountHistoryResource;
use App\Http\Resources\Web\Finance\CashMovementResource;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Services\Tenant\Finance\CashAccountHistoryService;
use App\Services\Tenant\Foundation\HistoryDateRange;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CashAccountHistoryController extends Controller
{
    public function index(HistoryRequest $request, CashAccount $cashAccount, HistoryDateRange $dates, CashAccountHistoryService $history): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [BusinessDayCashReconciliation::class, $branch]);
        abort_unless($cashAccount->branch_id === $branch->id, 404);

        $range = $dates->resolve($branch->timezone, $request->validated());
        $result = $history->history($branch, $cashAccount, $range, $this->perPage($request));

        return ApiResponse::resource(
            CashAccountHistoryResource::collection($result['page'])->additional([
                'cash_account' => [
                    'uuid' => $cashAccount->uuid,
                    'name' => $cashAccount->name,
                    'currency_code' => $cashAccount->currency_code,
                ],
                'filters' => $range,
                'summary' => $result['summary'],
            ]),
            'Cash account reconciliation history retrieved successfully.',
        );
    }

    public function movements(HistoryPageRequest $request, BusinessDayCashReconciliation $reconciliation, CashAccountHistoryService $history): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewAny', [BusinessDayCashReconciliation::class, $branch]);
        abort_unless($reconciliation->branch_id === $branch->id, 404);

        return ApiResponse::resource(
            CashMovementResource::collection($history->movements($branch, $reconciliation, $this->perPage($request))),
            'Cash reconciliation movements retrieved successfully.',
        );
    }
}
