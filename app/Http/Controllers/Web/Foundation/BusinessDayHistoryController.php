<?php

namespace App\Http\Controllers\Web\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Reports\HistoryRequest;
use App\Http\Resources\Web\Foundation\BusinessDayHistoryResource;
use App\Models\Tenant\Branch;
use App\Services\Tenant\Foundation\BusinessDayHistoryService;
use App\Services\Tenant\Foundation\HistoryDateRange;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BusinessDayHistoryController extends Controller
{
    public function index(HistoryRequest $request, Branch $branch, HistoryDateRange $dates, BusinessDayHistoryService $history): JsonResponse
    {
        $this->authorize('viewBusinessDate', $branch);
        abort_unless($branch->id === $this->currentBranchId(), 404);
        $range = $dates->resolve($branch->timezone, $request->validated());
        $result = $history->history($branch, $range, $this->perPage($request));

        return ApiResponse::resource(
            BusinessDayHistoryResource::collection($result['page'])->additional([
                'filters' => $range,
                'summary' => $result['summary'],
            ]),
            'Business day history retrieved successfully.',
        );
    }
}
