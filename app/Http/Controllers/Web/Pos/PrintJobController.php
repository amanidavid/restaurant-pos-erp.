<?php

namespace App\Http\Controllers\Web\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\Pos\PrintJobResource;
use App\Models\Tenant\PrintJob;
use App\Models\Tenant\Receipt;
use App\Services\Tenant\Pos\PrintJobService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintJobController extends Controller
{
    public function __construct(private readonly PrintJobService $jobs) {}

    public function receipt(Request $request, Receipt $receipt): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('print', [$receipt, $branch]);

        $job = $this->jobs->queueReceipt(
            $receipt,
            $branch,
            $request->user('tenant_api')->tenantUser()->firstOrFail(),
        );

        return ApiResponse::resource(new PrintJobResource($job), 'Receipt print job queued successfully.', 202);
    }

    public function show(PrintJob $printJob): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('view', [$printJob, $branch]);

        return ApiResponse::resource(
            new PrintJobResource($printJob->load(['printDevice', 'printTemplate', 'receipt'])),
            'Print job details loaded successfully.',
        );
    }
}
