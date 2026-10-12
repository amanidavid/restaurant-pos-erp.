<?php

namespace App\Http\Controllers\Web\Pos\Counter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pos\Counter\StoreCountersRequest;
use App\Http\Requests\Web\Pos\Counter\UpdateCounterRequest;
use App\Http\Resources\Web\Pos\CounterResource;
use App\Models\Tenant\Counter;
use App\Services\Tenant\Pos\Counter\CounterSetupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CounterSetupController extends Controller
{
    public function __construct(private readonly CounterSetupService $counters) {}

    public function store(StoreCountersRequest $request): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('createCounters', [Counter::class, $branch]);

        return ApiResponse::resource(
            CounterResource::collection($this->counters->create($branch, $request->validated('counters'))),
            'Counters created successfully.',
            201,
        );
    }

    public function update(UpdateCounterRequest $request, Counter $counter): JsonResponse
    {
        $branch = $this->currentBranch();
        $this->authorize('updateCounter', [Counter::class, $branch]);

        return ApiResponse::resource(
            new CounterResource($this->counters->update($counter, $branch, $request->validated())),
            'Counter updated successfully.',
        );
    }
}
