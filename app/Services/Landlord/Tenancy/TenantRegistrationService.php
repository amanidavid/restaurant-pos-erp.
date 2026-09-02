<?php

namespace App\Services\Landlord\Tenancy;

use App\Models\Landlord\DatabaseCluster;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantDatabase;
use App\Models\Landlord\TenantProvisioningRun;
use App\Jobs\Landlord\Tenancy\ProvisionTenantJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TenantRegistrationService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{tenant: Tenant, provisioning_run: TenantProvisioningRun}
     */
    public function register(array $data): array
    {
        return DB::connection('landlord')->transaction(function () use ($data): array {
            $cluster = DatabaseCluster::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if (! $cluster) {
                throw new RuntimeException('No active database cluster is configured.');
            }

            $tenant = Tenant::query()->create([
                'legal_name' => $data['business_name'],
                'trading_name' => $data['business_name'],
                'country_code' => 'TZ',
                'timezone' => 'Africa/Dar_es_Salaam',
                'locale' => 'en',
                'currency_code' => 'TZS',
                'email' => $data['email'] ?? null,
                'phone' => $this->normalizeTanzaniaPhone($data['phone'] ?? null),
                'status' => 'provisioning',
            ]);

            TenantDatabase::query()->create([
                'tenant_id' => $tenant->id,
                'database_cluster_id' => $cluster->id,
                'database_name' => $this->databaseNameForTenant($tenant),
                'database_username' => config('database.connections.tenant.username')
                    ?: config('database.connections.landlord.username'),
                'password_secret_reference' => null,
                'status' => 'provisioning',
                'is_primary' => true,
            ]);

            $run = TenantProvisioningRun::query()->create([
                'tenant_id' => $tenant->id,
                'database_cluster_id' => $cluster->id,
                'status' => 'pending',
                'current_step' => 'queued',
                'registration_payload' => [
                    'owner' => [
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'display_name' => trim($data['first_name'].' '.$data['last_name']),
                        'email' => $data['email'] ?? null,
                        'email_normalized' => $data['email'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'phone_normalized' => $this->normalizeTanzaniaPhone($data['phone'] ?? null),
                        'password_hash' => Hash::make($data['password']),
                    ],
                    'company' => [
                        'name' => $data['business_name'],
                    ],
                ],
                'requested_at' => Carbon::now(),
            ]);

            foreach ($this->provisioningSteps() as $sequence => $stepCode) {
                $run->steps()->create([
                    'sequence' => $sequence + 1,
                    'step_code' => $stepCode,
                    'status' => 'pending',
                ]);
            }

            ProvisionTenantJob::dispatch($run->id)->afterCommit();

            return [
                'tenant' => $tenant,
                'provisioning_run' => $run,
            ];
        });
    }

    private function databaseNameForTenant(Tenant $tenant): string
    {
        return 'rmis_tenant_'.str_pad((string) $tenant->id, 1, '0', STR_PAD_LEFT);
    }

    private function normalizeTanzaniaPhone(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        if (str_starts_with($digits, '255')) {
            return '+'.$digits;
        }

        if (preg_match('/^[67]\d{8}$/', $digits) === 1) {
            return '+255'.$digits;
        }

        return '+'.$digits;
    }

    /**
     * @return list<string>
     */
    private function provisioningSteps(): array
    {
        return [
            'create_database',
            'configure_connection',
            'run_migrations',
            'create_owner',
            'create_company',
            'create_branch',
            'run_seeders',
            'assign_owner_role',
            'activate_tenant',
        ];
    }
}
