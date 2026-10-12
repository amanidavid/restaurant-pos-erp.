<?php

namespace App\Services\Tenant\Customers;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function create(Branch $branch, array $data): Customer
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $data): Customer {
            $values = $this->values($branch, $data);
            $this->assertUniqueEmail($branch, $values['email'], null);

            return Customer::query()->create([
                'company_id' => $branch->company_id,
                ...$values,
            ])->fresh('customerGroup:id,uuid,name');
        });
    }

    public function update(Customer $customer, Branch $branch, array $data): Customer
    {
        return DB::connection('tenant')->transaction(function () use ($customer, $branch, $data): Customer {
            $customer = Customer::query()
                ->where('company_id', $branch->company_id)
                ->whereKey($customer->id)
                ->lockForUpdate()
                ->firstOrFail();
            $values = $this->values($branch, array_merge($customer->only([
                'name', 'email', 'phone', 'tax_number', 'status',
            ]), $data), array_key_exists('customer_group_uuid', $data));
            $this->assertUniqueEmail($branch, $values['email'], $customer->id);
            $customer->update($values);

            return $customer->fresh('customerGroup:id,uuid,name');
        });
    }

    private function values(Branch $branch, array $data, bool $resolveGroup = true): array
    {
        $values = [
            'name' => Str::title(Str::lower(trim($data['name']))),
            'email' => isset($data['email']) && $data['email'] !== null ? Str::lower(trim($data['email'])) : null,
            'phone' => isset($data['phone']) && $data['phone'] !== null ? trim($data['phone']) : null,
            'tax_number' => isset($data['tax_number']) && $data['tax_number'] !== null ? trim($data['tax_number']) : null,
            'status' => $data['status'] ?? 'active',
        ];

        if ($resolveGroup) {
            $values['customer_group_id'] = $this->groupId($branch, $data['customer_group_uuid'] ?? null);
        }

        return $values;
    }

    private function groupId(Branch $branch, ?string $uuid): ?int
    {
        if (! $uuid) {
            return null;
        }

        $group = CustomerGroup::query()
            ->where('company_id', $branch->company_id)
            ->where('uuid', $uuid)
            ->where('status', 'active')
            ->value('id');
        if (! $group) {
            throw ValidationException::withMessages([
                'customer_group_uuid' => ['Select an active customer group from this company.'],
            ]);
        }

        return (int) $group;
    }

    private function assertUniqueEmail(Branch $branch, ?string $email, ?int $exceptId): void
    {
        if (! $email) {
            return;
        }

        $exists = Customer::query()
            ->where('company_id', $branch->company_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->lockForUpdate()
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['A customer with this email already exists in this company.'],
            ]);
        }
    }
}
