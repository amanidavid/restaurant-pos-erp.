<?php

use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use Database\Seeders\Tenant\TenantAccessSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tenants:seed-access
    {--tenant= : Seed one tenant by its public UUID}
    {--all : Seed every trial or active tenant registered by the landlord}',
    function (TenantConnectionManager $tenantConnectionManager): int {
        $tenantUuid = $this->option('tenant');
        $seedAll = (bool) $this->option('all');

        if (! $seedAll && (! is_string($tenantUuid) || trim($tenantUuid) === '')) {
            $this->error('Choose --all to seed every available tenant, or provide --tenant=<tenant-uuid>.');

            return Command::INVALID;
        }

        $query = Tenant::query()
            ->select(['id', 'uuid'])
            ->whereIn('status', ['trial', 'active'])
            ->orderBy('id');

        if (is_string($tenantUuid) && trim($tenantUuid) !== '') {
            $query->where('uuid', trim($tenantUuid));
        }

        $seeded = 0;
        $failed = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                $connectedTenant = $tenantConnectionManager->connectByUuid($tenant->uuid);
                $databaseName = $connectedTenant->getDatabaseName();

                app(PermissionRegistrar::class)->forgetCachedPermissions();

                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => TenantAccessSeeder::class,
                    '--force' => true,
                ]);

                app(PermissionRegistrar::class)->forgetCachedPermissions();
                DB::disconnect('tenant');

                $seeded++;
                $this->info("Access seeded: {$tenant->uuid} ({$databaseName}).");
            } catch (\Throwable $exception) {
                DB::disconnect('tenant');
                $failed++;
                $this->error("Access seed failed for {$tenant->uuid}: {$exception->getMessage()}");
            }
        }

        if ($seeded === 0 && $failed === 0) {
            $this->warn('No eligible tenant was found in the landlord registry.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->line("Completed. Seeded: {$seeded}; failed: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
)->purpose('Seed tenant roles and permissions using tenant databases registered in the landlord database');
