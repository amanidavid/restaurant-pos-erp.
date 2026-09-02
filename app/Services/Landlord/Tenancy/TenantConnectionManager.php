<?php

namespace App\Services\Landlord\Tenancy;

use App\Models\Landlord\Tenant;
use App\Support\ApiMessages;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TenantConnectionManager
{
    public function connectByUuid(?string $tenantUuid): Tenant
    {
        $tenantUuid = is_string($tenantUuid) ? trim($tenantUuid) : null;

        if (! $tenantUuid) {
            throw new BadRequestHttpException(ApiMessages::INVALID_TENANT_HEADER);
        }

        if (! Str::isUuid($tenantUuid)) {
            throw new BadRequestHttpException(ApiMessages::INVALID_TENANT_HEADER);
        }

        $tenant = Tenant::query()
            ->where('uuid', $tenantUuid)
            ->with('activeDatabasePlacement.databaseCluster')
            ->first();

        if (! $tenant) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantUuid]);
        }

        if (! in_array($tenant->status, ['trial', 'active'], true)) {
            throw new BadRequestHttpException(ApiMessages::TENANT_CONTEXT_UNAVAILABLE);
        }

        $this->configure($tenant);

        app()->instance('currentTenant', $tenant);
        app()->instance('tenantUuid', $tenant->uuid);

        return $tenant;
    }

    public function configure(Tenant $tenant): void
    {
        $tenant->loadMissing('activeDatabasePlacement.databaseCluster');
        $database = $tenant->activeDatabasePlacement;

        if (! $database) {
            throw new BadRequestHttpException(ApiMessages::TENANT_CONTEXT_UNAVAILABLE);
        }

        $cluster = $database->databaseCluster;

        config()->set('database.connections.tenant.host', $cluster?->host ?? config('database.connections.tenant.host'));
        config()->set('database.connections.tenant.port', $cluster?->port ?? config('database.connections.tenant.port'));
        config()->set('database.connections.tenant.database', $database->database_name);
        config()->set('database.connections.tenant.username', $database->database_username);
        config()->set('database.connections.tenant.sslmode', $cluster?->ssl_mode ?? config('database.connections.tenant.sslmode', 'prefer'));

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    public function forget(): void
    {
        config()->set('database.connections.tenant.database', null);

        DB::purge('tenant');
    }
}
