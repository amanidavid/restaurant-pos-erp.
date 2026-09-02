<?php

namespace App\Services\Tenant\Foundation;

use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\Company;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserBranch;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Branch
    {
        return DB::connection('tenant')->transaction(function () use ($data, $actor): Branch {
            $company = Company::query()->orderBy('id')->firstOrFail();
            $tenantUser = $actor->tenantUser()->firstOrFail();
            $nameNormalized = $this->normalizedName((string) $data['name']);

            $this->ensureUniqueName($company->id, $nameNormalized);

            $branch = Branch::query()->create(array_merge(
                $this->branchPayload($data),
                [
                    'company_id' => $company->id,
                    'name_normalized' => $nameNormalized,
                    'timezone' => 'Africa/Dar_es_Salaam',
                    'currency_code' => 'TZS',
                    'status' => 'active',
                    'opened_on' => $data['opened_on'] ?? Carbon::today()->toDateString(),
                ],
            ));

            $this->createInitialConfiguration($branch, $data['configuration'] ?? []);
            $this->assignActorToBranch($tenantUser, $branch);
            $this->copyActorRolesToBranch($actor, $branch);

            return $branch->load('activeConfiguration');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $data): Branch {
            if (array_key_exists('name', $data)) {
                $this->ensureUniqueName(
                    $branch->company_id,
                    $this->normalizedName((string) $data['name']),
                    $branch->id,
                );
            }

            if (($data['status'] ?? null) === 'closed') {
                $this->ensureBranchCanClose($branch);
                $data['closed_on'] = $data['closed_on'] ?? Carbon::today()->toDateString();
            }

            if (($data['status'] ?? null) !== 'closed') {
                $data['closed_on'] = null;
            }

            $payload = $this->branchPayload($data);

            if (array_key_exists('name', $data)) {
                $payload['name_normalized'] = $this->normalizedName((string) $data['name']);
            }

            $branch->forceFill($payload)->save();

            return $branch->refresh()->load('activeConfiguration');
        });
    }

    private function ensureUniqueName(int $companyId, string $nameNormalized, ?int $ignoreBranchId = null): void
    {
        $exists = Branch::query()
            ->where('company_id', $companyId)
            ->where('name_normalized', $nameNormalized)
            ->when($ignoreBranchId, fn ($query) => $query->whereKeyNot($ignoreBranchId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A branch with the same name already exists.'],
            ]);
        }
    }

    private function ensureBranchCanClose(Branch $branch): void
    {
        $hasOpenBusinessDate = BusinessDate::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', ['open', 'closing'])
            ->exists();

        if ($hasOpenBusinessDate) {
            throw ValidationException::withMessages([
                'status' => ['Close the active business day before closing this branch.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function branchPayload(array $data): array
    {
        $payload = [];

        foreach ([
            'name',
            'email',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'region',
            'postal_code',
            'latitude',
            'longitude',
            'opened_on',
            'closed_on',
            'status',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('name', $payload)) {
            $payload['name'] = Str::title(Str::lower(trim((string) $payload['name'])));
        }

        if (! empty($payload['email'])) {
            $payload['email'] = Str::lower(trim((string) $payload['email']));
        }

        if (! empty($payload['phone'])) {
            $payload['phone'] = $this->normalizeTanzaniaPhone((string) $payload['phone']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function createInitialConfiguration(Branch $branch, array $configuration): void
    {
        $branch->configurations()->create([
            'version' => 1,
            'effective_from' => Carbon::today()->toDateString(),
            'business_day_opens_at' => ($configuration['business_day_opens_at'] ?? '08:00').':00',
            'business_day_closes_at' => ($configuration['business_day_closes_at'] ?? '23:59').':00',
            'allow_negative_stock' => (bool) ($configuration['allow_negative_stock'] ?? false),
            'is_active' => true,
        ]);
    }

    private function assignActorToBranch(TenantUser $tenantUser, Branch $branch): void
    {
        $hasPrimary = TenantUserBranch::query()
            ->where('tenant_user_id', $tenantUser->id)
            ->where('status', 'active')
            ->where('is_primary', true)
            ->exists();

        TenantUserBranch::query()->updateOrCreate([
            'tenant_user_id' => $tenantUser->id,
            'branch_id' => $branch->id,
        ], [
            'status' => 'active',
            'is_primary' => ! $hasPrimary,
            'valid_from' => Carbon::today()->toDateString(),
            'assigned_by_id' => $tenantUser->id,
            'revoked_by_id' => null,
            'revoked_at' => null,
        ]);
    }

    private function copyActorRolesToBranch(User $actor, Branch $branch): void
    {
        $currentBranchId = app()->bound('currentBranchId') ? app('currentBranchId') : null;

        if (! $currentBranchId) {
            return;
        }

        $roleIds = DB::connection('tenant')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $actor->id)
            ->where('branch_id', $currentBranchId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::connection('tenant')->table('model_has_roles')->updateOrInsert([
                'role_id' => $roleId,
                'branch_id' => $branch->id,
                'model_type' => User::class,
                'model_id' => $actor->id,
            ]);
        }
    }

    private function normalizedName(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    private function normalizeTanzaniaPhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? $phone;

        if (str_starts_with($phone, '+255')) {
            return $phone;
        }

        if (str_starts_with($phone, '255')) {
            return '+'.$phone;
        }

        if (str_starts_with($phone, '0')) {
            return '+255'.substr($phone, 1);
        }

        return '+255'.$phone;
    }
}
