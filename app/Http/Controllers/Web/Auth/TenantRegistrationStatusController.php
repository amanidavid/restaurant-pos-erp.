<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\Auth\TenantProvisioningStatusResource;
use App\Models\Landlord\Tenant;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TenantRegistrationStatusController extends Controller
{
    public function __invoke(string $tenant): JsonResponse
    {
        $tenant = Tenant::query()
            ->where('uuid', $tenant)
            ->with([
                'provisioningRuns' => fn ($query) => $query->latest('id')->limit(1),
                'provisioningRuns.steps' => fn ($query) => $query->orderBy('sequence'),
            ])
            ->firstOrFail();

        $run = $tenant->provisioningRuns->first();

        abort_if(! $run, 404);

        return ApiResponse::resource(
            new TenantProvisioningStatusResource([
                'tenant' => $tenant,
                'provisioning_run' => $run,
            ]),
            ApiMessages::detailsRetrieved('tenant_provisioning_status'),
        );
    }
}
