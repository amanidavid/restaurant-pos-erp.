<?php

namespace App\Http\Controllers\Web\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Menu\ImportMenuItemsRequest;
use App\Http\Resources\Web\Menu\DataImportBatchResource;
use App\Models\Tenant\MenuItem;
use App\Services\Tenant\Menu\MenuImportExportService;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MenuImportExportController extends Controller
{
    public function __construct(private readonly MenuImportExportService $importExportService) {}

    public function import(ImportMenuItemsRequest $request): JsonResponse
    {
        $this->authorize('import', [MenuItem::class, $this->currentBranch()]);

        $batch = $this->importExportService->createImportBatch(
            $request->file('file'),
            $request->user('tenant_api'),
            $request->validated('branch_uuid')
        );

        return ApiResponse::resource(
            new DataImportBatchResource($batch),
            ApiMessages::queued('menu import'),
            202
        );
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->authorize('export', [MenuItem::class, $this->currentBranch()]);

        return $this->importExportService->menuItemsCsv();
    }

}
