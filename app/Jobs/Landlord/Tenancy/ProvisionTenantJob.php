<?php

namespace App\Jobs\Landlord\Tenancy;

use App\Models\Landlord\TenantDatabase;
use App\Models\Landlord\TenantProvisioningRun;
use App\Models\Landlord\User as LandlordUser;
use App\Models\Tenant\User as TenantAuthUser;
use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;

class ProvisionTenantJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $provisioningRunId,
    ) {}

    public function handle(): void
    {
        $run = TenantProvisioningRun::query()
            ->with(['tenant.activeDatabasePlacement.databaseCluster', 'steps'])
            ->findOrFail($this->provisioningRunId);

        $this->markRunStarted($run);

        try {
            $this->runStep($run, 'create_database', fn () => $this->createDatabase($run));
            $this->runStep($run, 'configure_connection', fn () => $this->configureTenantConnection($run));
            $this->runStep($run, 'run_migrations', fn () => $this->runTenantMigrations());
            $this->runStep($run, 'create_owner', fn () => $this->createOwner($run, $this->ensureLandlordOwner($run)));
            $this->runStep($run, 'create_company', fn () => $this->createCompany($run));
            $this->runStep($run, 'create_branch', fn () => $this->createBranch($run));
            $this->runStep($run, 'run_seeders', fn () => $this->runTenantSeeders());
            $this->runStep($run, 'assign_owner_role', fn () => $this->assignOwnerRole($run));
            $this->runStep($run, 'activate_tenant', fn () => $this->activateTenant($run));

            $run->forceFill([
                'status' => 'completed',
                'current_step' => 'completed',
                'completed_at' => Carbon::now(),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'failure_code' => class_basename($exception),
                'failure_message' => Str::limit($exception->getMessage(), 1000),
            ])->save();

            throw $exception;
        } finally {
            DB::purge('tenant');
        }
    }

    private function markRunStarted(TenantProvisioningRun $run): void
    {
        if ($run->status === 'completed') {
            return;
        }

        $run->forceFill([
            'status' => 'running',
            'started_at' => $run->started_at ?? Carbon::now(),
        ])->save();
    }

    private function runStep(TenantProvisioningRun $run, string $stepCode, callable $callback): void
    {
        $step = $run->steps->firstWhere('step_code', $stepCode)
            ?? throw new RuntimeException("Provisioning step {$stepCode} is missing.");

        if ($step->status === 'completed') {
            if ($stepCode === 'configure_connection') {
                $this->configureTenantConnection($run);
            }

            return;
        }

        $run->forceFill([
            'current_step' => $stepCode,
            'status' => 'running',
        ])->save();

        $step->forceFill([
            'status' => 'running',
            'attempts' => $step->attempts + 1,
            'started_at' => $step->started_at ?? Carbon::now(),
            'failure_message' => null,
        ])->save();

        try {
            $callback();

            $step->forceFill([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'failure_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $step->forceFill([
                'status' => 'failed',
                'failure_message' => Str::limit($exception->getMessage(), 1000),
            ])->save();

            throw $exception;
        }
    }

    private function createDatabase(TenantProvisioningRun $run): void
    {
        $database = $this->tenantDatabase($run);
        $databaseName = $database->database_name;

        $exists = DB::connection('landlord')
            ->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$databaseName]);

        if ($exists) {
            return;
        }

        DB::connection('landlord')->statement(
            "CREATE DATABASE {$this->quoteIdentifier($databaseName)} WITH TEMPLATE template0 ENCODING 'UTF8'"
        );
    }

    private function configureTenantConnection(TenantProvisioningRun $run): void
    {
        $database = $this->tenantDatabase($run);
        $cluster = $database->databaseCluster;

        config()->set('database.connections.tenant.host', $cluster?->host ?? config('database.connections.tenant.host'));
        config()->set('database.connections.tenant.port', $cluster?->port ?? config('database.connections.tenant.port'));
        config()->set('database.connections.tenant.database', $database->database_name);
        config()->set('database.connections.tenant.username', $database->database_username);
        config()->set('database.connections.tenant.password', config('database.connections.tenant.password'));
        config()->set('database.connections.tenant.sslmode', $cluster?->ssl_mode ?? config('database.connections.tenant.sslmode', 'prefer'));

        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function runTenantMigrations(): void
    {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    private function createOwner(TenantProvisioningRun $run, string $landlordUserUuid): void
    {
        $owner = $this->payload($run)['owner'];

        DB::connection('tenant')->transaction(function () use ($owner, $landlordUserUuid): void {
            $values = [
                'display_name' => $owner['display_name'],
                'status' => 'active',
                'landlord_user_uuid' => $landlordUserUuid,
                'last_synchronized_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            $tenantUserId = DB::connection('tenant')->table('tenant_users')
                ->where('landlord_user_uuid', $landlordUserUuid)
                ->value('id');

            if ($tenantUserId) {
                DB::connection('tenant')->table('tenant_users')->where('id', $tenantUserId)->update($values);
            } else {
                DB::connection('tenant')->table('tenant_users')->insert(array_merge($values, [
                    'uuid' => (string) Str::uuid7(),
                    'created_at' => Carbon::now(),
                ]));
            }
        });
    }

    private function ensureLandlordOwner(TenantProvisioningRun $run): string
    {
        $owner = $this->payload($run)['owner'];

        return DB::connection('landlord')->transaction(function () use ($run, $owner): string {
            $query = LandlordUser::query();

            if ($owner['email_normalized']) {
                $query->whereRaw('LOWER(email) = ?', [$owner['email_normalized']]);
            } elseif ($owner['phone_normalized']) {
                $query->where('phone', $owner['phone_normalized']);
            }

            $user = $query->first();

            if (! $user) {
                $user = LandlordUser::query()->create([
                    'uuid' => (string) Str::uuid7(),
                    'first_name' => $owner['first_name'],
                    'last_name' => $owner['last_name'],
                    'display_name' => $owner['display_name'],
                    'email' => $owner['email'] ?: null,
                    'phone' => $owner['phone_normalized'] ?: null,
                    'password' => $owner['password_hash'],
                    'status' => 'active',
                    'password_changed_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::connection('landlord')->table('tenant_accounts')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'tenant_id' => $run->tenant_id,
                ],
                [
                    'status' => 'active',
                    'is_owner' => true,
                    'joined_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            );

            return (string) $user->uuid;
        });
    }

    private function createCompany(TenantProvisioningRun $run): void
    {
        $payload = $this->payload($run);
        $companyName = $payload['company']['name'];
        $owner = $payload['owner'];

        $companyValues = [
            'trading_name' => $companyName,
            'country_code' => 'TZ',
            'timezone' => 'Africa/Dar_es_Salaam',
            'locale' => 'en',
            'base_currency_code' => 'TZS',
            'email' => $owner['email'],
            'phone' => $owner['phone_normalized'],
            'status' => 'active',
            'updated_at' => Carbon::now(),
        ];

        $companyId = DB::connection('tenant')->table('companies')
            ->where('legal_name', $companyName)
            ->value('id');

        if ($companyId) {
            DB::connection('tenant')->table('companies')->where('id', $companyId)->update($companyValues);
        } else {
            DB::connection('tenant')->table('companies')->insert(array_merge($companyValues, [
                'uuid' => (string) Str::uuid7(),
                'legal_name' => $companyName,
                'created_at' => Carbon::now(),
            ]));
        }
    }

    private function createBranch(TenantProvisioningRun $run): void
    {
        $payload = $this->payload($run);
        $company = DB::connection('tenant')->table('companies')->first(['id', 'trading_name']);

        if (! $company) {
            throw new RuntimeException('Cannot create first branch before company exists.');
        }

        $branchName = $company->trading_name;

        DB::connection('tenant')->transaction(function () use ($company, $branchName): void {
            $branchValues = [
                'name' => $branchName,
                'timezone' => 'Africa/Dar_es_Salaam',
                'currency_code' => 'TZS',
                'status' => 'active',
                'opened_on' => Carbon::today()->toDateString(),
                'updated_at' => Carbon::now(),
            ];

            $branchId = DB::connection('tenant')->table('branches')
                ->where('company_id', $company->id)
                ->where('name_normalized', Str::lower($branchName))
                ->value('id');

            if ($branchId) {
                DB::connection('tenant')->table('branches')->where('id', $branchId)->update($branchValues);
            } else {
                DB::connection('tenant')->table('branches')->insert(array_merge($branchValues, [
                    'uuid' => (string) Str::uuid7(),
                    'company_id' => $company->id,
                    'name_normalized' => Str::lower($branchName),
                    'created_at' => Carbon::now(),
                ]));
            }

            $branch = DB::connection('tenant')->table('branches')
                ->where('company_id', $company->id)
                ->where('name_normalized', Str::lower($branchName))
                ->first(['id']);

            $tenantUser = DB::connection('tenant')->table('tenant_users')->first(['id']);

            if (! $branch || ! $tenantUser) {
                throw new RuntimeException('Cannot assign owner branch access before branch and tenant user exist.');
            }

            DB::connection('tenant')->table('tenant_user_branches')->updateOrInsert([
                'tenant_user_id' => $tenantUser->id,
                'branch_id' => $branch->id,
            ], [
                'status' => 'active',
                'is_primary' => true,
                'valid_from' => Carbon::today()->toDateString(),
                'assigned_by_id' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $configurationValues = [
                'effective_from' => Carbon::today()->toDateString(),
                'business_day_opens_at' => '06:00:00',
                'business_day_closes_at' => '23:59:00',
                'allow_negative_stock' => false,
                'is_active' => true,
                'updated_at' => Carbon::now(),
            ];

            $configurationId = DB::connection('tenant')->table('branch_configurations')
                ->where('branch_id', $branch->id)
                ->where('version', 1)
                ->value('id');

            if ($configurationId) {
                DB::connection('tenant')->table('branch_configurations')
                    ->where('id', $configurationId)
                    ->update($configurationValues);
            } else {
                DB::connection('tenant')->table('branch_configurations')->insert(array_merge($configurationValues, [
                    'uuid' => (string) Str::uuid7(),
                    'branch_id' => $branch->id,
                    'version' => 1,
                    'created_at' => Carbon::now(),
                ]));
            }
        });
    }

    private function runTenantSeeders(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => TenantDatabaseSeeder::class,
            '--force' => true,
        ]);
    }

    private function assignOwnerRole(TenantProvisioningRun $run): void
    {
        $user = TenantAuthUser::query()->first();
        $branchId = DB::connection('tenant')->table('branches')->orderBy('id')->value('id');
        $roleId = DB::connection('tenant')->table('roles')
            ->where('name', 'business-owner')
            ->where('guard_name', 'tenant_api')
            ->value('id');

        if (! $user || ! $branchId || ! $roleId) {
            throw new RuntimeException('Cannot assign Business Owner role before user, branch, and role exist.');
        }

        DB::connection('tenant')->table('model_has_roles')->updateOrInsert([
            'role_id' => $roleId,
            'model_type' => TenantAuthUser::class,
            'model_id' => $user->id,
            'branch_id' => $branchId,
        ]);
    }

    private function activateTenant(TenantProvisioningRun $run): void
    {
        $database = $this->tenantDatabase($run);

        $database->forceFill([
            'status' => 'active',
            'schema_version' => 'v1',
            'provisioned_at' => $database->provisioned_at ?? Carbon::now(),
            'activated_at' => $database->activated_at ?? Carbon::now(),
        ])->save();

        $run->tenant->forceFill([
            'status' => 'trial',
            'activated_at' => $run->tenant->activated_at ?? Carbon::now(),
        ])->save();
    }

    private function tenantDatabase(TenantProvisioningRun $run): TenantDatabase
    {
        $database = $run->tenant->activeDatabasePlacement;

        if (! $database) {
            throw new RuntimeException('Tenant has no active database placement.');
        }

        return $database;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TenantProvisioningRun $run): array
    {
        if (! is_array($run->registration_payload)) {
            throw new RuntimeException('Tenant provisioning registration payload is missing.');
        }

        return $run->registration_payload;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $identifier) !== 1) {
            throw new RuntimeException("Unsafe PostgreSQL identifier: {$identifier}");
        }

        return '"'.$identifier.'"';
    }
}
