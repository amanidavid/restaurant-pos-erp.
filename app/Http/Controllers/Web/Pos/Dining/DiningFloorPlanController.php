<?php

namespace App\Http\Controllers\Web\Pos\Dining;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\Dining\StoreDiningFloorPlanRequest;
use App\Http\Requests\Web\Pos\Dining\StoreDiningTableRequest;
use App\Http\Requests\Web\Pos\Dining\UpdateDiningAreaRequest;
use App\Http\Requests\Web\Pos\Dining\UpdateDiningTableRequest;
use App\Http\Resources\Web\Pos\DiningAreaResource;
use App\Http\Resources\Web\Pos\DiningTableResource;
use App\Models\Tenant\DiningArea;
use App\Models\Tenant\DiningTable;
use App\Services\Tenant\Pos\Dining\DiningFloorPlanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DiningFloorPlanController extends Controller
{
    public function __construct(private readonly DiningFloorPlanService $floorPlans) {}

    public function store(StoreDiningFloorPlanRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('createFloorPlan', [DiningArea::class, $branch]);

        $areas = $this->floorPlans->create($branch, $request->validated('areas'));

        return ApiResponse::resource(
            DiningAreaResource::collection($areas),
            'Dining floor plan created successfully.',
            201,
        );
    }

    public function showArea(DiningArea $diningArea): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewDiningAreas', [DiningArea::class, $branch]);

        return ApiResponse::resource(
            new DiningAreaResource($this->floorPlans->showArea($diningArea, $branch)),
            'Dining area details retrieved successfully.',
        );
    }

    public function updateArea(UpdateDiningAreaRequest $request, DiningArea $diningArea): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('updateDiningArea', [DiningArea::class, $branch]);

        return ApiResponse::resource(
            new DiningAreaResource($this->floorPlans->updateArea($diningArea, $branch, $request->validated())),
            'Dining area updated successfully.',
        );
    }

    public function storeTable(StoreDiningTableRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('createDiningTable', [DiningTable::class, $branch]);

        return ApiResponse::resource(
            new DiningTableResource($this->floorPlans->createTable($branch, $request->validated())),
            'Dining table created successfully.',
            201,
        );
    }

    public function showTable(DiningTable $diningTable): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('viewDiningTables', [DiningTable::class, $branch]);

        return ApiResponse::resource(
            new DiningTableResource($this->floorPlans->showTable($diningTable, $branch)),
            'Dining table details retrieved successfully.',
        );
    }

    public function updateTable(UpdateDiningTableRequest $request, DiningTable $diningTable): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('updateDiningTable', [DiningTable::class, $branch]);

        return ApiResponse::resource(
            new DiningTableResource($this->floorPlans->updateTable($diningTable, $branch, $request->validated())),
            'Dining table updated successfully.',
        );
    }
}
