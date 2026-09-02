<?php

namespace App\Console\Commands\Tenant;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantAccount;
use App\Models\Landlord\User as LandlordUser;
use App\Models\Tenant\User as TenantAuthUser;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class BackfillLandlordIdentityCommand extends Command
{
    protected $signature = 'tenants:backfill-landlord-identity
        {--tenant= : One tenant public UUID}
        {--all : Process every active tenant}';

    protected $description = 'Map tenant staff profiles to canonical landlord users without deleting legacy credentials';

    public function handle(TenantConnectionManager $connectionManager): int
    {
        $tenantUuid = $this->option('tenant');

        if (! $this->option('all') && (! is_string($tenantUuid) || trim($tenantUuid) === '')) {
            $this->error('Provide --tenant=<tenant-uuid> or use --all.');

            return SymfonyCommand::INVALID;
        }

        $query = Tenant::query()
            ->select(['id', 'uuid'])
            ->whereIn('status', ['trial', 'active', 'provisioning'])
            ->orderBy('id');

        if (is_string($tenantUuid) && trim($tenantUuid) !== '') {
            $query->where('uuid', trim($tenantUuid));
        }

        $mapped = 0;
        $failed = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                $connectionManager->connectByUuid($tenant->uuid);
                $mapped += $this->backfillTenant((int) $tenant->id);
                DB::disconnect('tenant');
                $this->info("Identity mapping completed: {$tenant->uuid}.");
            } catch (\Throwable $exception) {
                DB::disconnect('tenant');
                $failed++;
                $this->error("Identity mapping failed for {$tenant->uuid}: {$exception->getMessage()}");
            }
        }

        $this->line("Completed. Profiles mapped: {$mapped}; tenants failed: {$failed}.");

        return $failed === 0 ? SymfonyCommand::SUCCESS : SymfonyCommand::FAILURE;
    }

    private function backfillTenant(int $tenantId): int
    {
        $tenantConnection = DB::connection('tenant');
        $landlordConnection = DB::connection('landlord');
        $mapped = 0;

        $tenantUsers = $tenantConnection->table('tenant_users as tu')
            ->join('users as legacy', 'legacy.id', '=', 'tu.user_id')
            ->whereNull('tu.landlord_user_uuid')
            ->select([
                'tu.id as tenant_user_id',
                'legacy.id as legacy_user_id',
                'legacy.first_name',
                'legacy.last_name',
                'legacy.display_name',
                'legacy.email_normalized',
                'legacy.phone_normalized',
                'legacy.password',
                'tu.status',
            ])
            ->orderBy('tu.id')
            ->get();

        foreach ($tenantUsers as $tenantUser) {
            if (! $tenantUser->email_normalized && ! $tenantUser->phone_normalized) {
                continue;
            }

            $landlordUser = $landlordConnection->table('users')
                ->where(function ($query) use ($tenantUser): void {
                    if ($tenantUser->email_normalized) {
                        $query->whereRaw('LOWER(email) = ?', [$tenantUser->email_normalized]);
                    }

                    if ($tenantUser->phone_normalized) {
                        $query->orWhere('phone', $tenantUser->phone_normalized);
                    }
                })
                ->select(['id', 'uuid'])
                ->first();

            if (! $landlordUser) {
                $landlordUser = LandlordUser::query()->create([
                    'uuid' => (string) Str::uuid7(),
                    'first_name' => $tenantUser->first_name,
                    'last_name' => $tenantUser->last_name,
                    'display_name' => $tenantUser->display_name,
                    'email' => $tenantUser->email_normalized,
                    'phone' => $tenantUser->phone_normalized,
                    'password' => $tenantUser->password,
                    'status' => 'active',
                    'password_changed_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            $updated = $tenantConnection->table('tenant_users')
                ->where('id', $tenantUser->tenant_user_id)
                ->whereNull('landlord_user_uuid')
                ->update([
                    'landlord_user_uuid' => $landlordUser->uuid,
                    'last_synchronized_at' => now(),
                    'updated_at' => now(),
                ]);

            // Permission rows currently point to the legacy tenant user ID.
            // Move them to the canonical landlord user ID before authentication cutover.
            foreach (['model_has_roles', 'model_has_permissions'] as $permissionTable) {
                $tenantConnection->table($permissionTable)
                    ->where('model_type', TenantAuthUser::class)
                    ->where('model_id', $tenantUser->legacy_user_id)
                    ->update(['model_id' => $landlordUser->id]);
            }

            TenantAccount::query()->updateOrCreate(
                [
                    'user_id' => $landlordUser->id,
                    'tenant_id' => $tenantId,
                ],
                [
                    'status' => $tenantUser->status === 'active' ? 'active' : 'suspended',
                    'is_owner' => false,
                    'joined_at' => Carbon::now(),
                ],
            );

            $mapped += $updated;
        }

        return $mapped;
    }
}
