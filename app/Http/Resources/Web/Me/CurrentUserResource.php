<?php

namespace App\Http\Resources\Web\Me;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $tenantUser = $this->resource['tenant_user'];
        $company = $this->resource['company'];
        $activeBranch = $this->resource['active_branch'];

        return [
            'user' => [
                'uuid' => $user->uuid,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'last_login_at' => $user->last_login_at
                    ?->timezone('Africa/Dar_es_Salaam')
                    ->format('Y-m-d H:i:s'),
            ],
            'tenant_user' => [
                'uuid' => $tenantUser->uuid,
                'display_name' => $tenantUser->display_name,
                'status' => $tenantUser->status,
                'employee_number' => $tenantUser->employee_number,
                'employment_status' => $tenantUser->employment_status,
            ],
            'company' => $company ? [
                'uuid' => $company->uuid,
                'legal_name' => $company->legal_name,
                'trading_name' => $company->trading_name,
                'country_code' => $company->country_code,
                'timezone' => $company->timezone,
                'locale' => $company->locale,
                'currency_code' => $company->base_currency_code,
                'status' => $company->status,
            ] : null,
            'branches' => $this->resource['branches']->map(fn ($branch): array => [
                'uuid' => $branch->uuid,
                'name' => $branch->name,
                'timezone' => $branch->timezone,
                'currency_code' => $branch->currency_code,
                'status' => $branch->status,
                'is_primary' => (bool) $branch->pivot_is_primary,
            ])->values(),
            'active_branch_uuid' => $activeBranch?->uuid,
        ];
    }
}
