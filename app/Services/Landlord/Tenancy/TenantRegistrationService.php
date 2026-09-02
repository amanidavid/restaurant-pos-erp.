<?php

namespace App\Services\Landlord\Tenancy;

use App\Jobs\Landlord\Tenancy\ProvisionTenantJob;
use App\Models\Landlord\DatabaseCluster;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantAccount;
use App\Models\Landlord\TenantDatabase;
use App\Models\Landlord\TenantProvisioningRun;
use App\Models\Landlord\User;
use App\Support\TanzaniaPhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
            $phone = TanzaniaPhoneNumber::normalize($data['phone'] ?? null);
            $this->assertLoginIsAvailable($data['email'] ?? null, $phone);

            $cluster = DatabaseCluster::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            if (! $cluster) {
                throw new RuntimeException('No active database cluster is configured.');
            }

            $passwordHash = Hash::make($data['password']);
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'display_name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'] ?? null,
                'phone' => $phone,
                'password' => $passwordHash,
                'status' => 'active',
                'password_changed_at' => Carbon::now(),
            ]);

            $tenant = Tenant::query()->create([
                'legal_name' => $data['business_name'],
                'trading_name' => $data['business_name'],
                'country_code' => 'TZ',
                'timezone' => 'Africa/Dar_es_Salaam',
                'locale' => 'en',
                'currency_code' => 'TZS',
                'email' => $data['email'] ?? null,
                'phone' => $phone,
                'status' => 'provisioning',
            ]);

            TenantAccount::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'is_owner' => true,
                'joined_at' => Carbon::now(),
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
                        'phone_normalized' => $phone,
                        'password_hash' => $passwordHash,
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

    private function assertLoginIsAvailable(?string $email, ?string $phone): void
    {
        $errors = [];

        if ($email && User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            $errors['email'] = ['An account already exists with this email address. Please sign in instead.'];
        }

        if ($phone && User::query()->where('phone', $phone)->exists()) {
            $errors['phone'] = ['An account already exists with this phone number. Please sign in instead.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
