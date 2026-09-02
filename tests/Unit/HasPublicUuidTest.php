<?php

namespace Tests\Unit;

use App\Models\Landlord\TenantAccount;
use App\Models\Landlord\User;
use App\Models\Tenant\TenantUserBranch;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class HasPublicUuidTest extends TestCase
{
    public function test_it_generates_uuid_v7_for_the_secondary_public_key(): void
    {
        $user = new User;

        $user->setUniqueIds();

        $this->assertSame(['uuid'], $user->uniqueIds());
        $this->assertSame(7, Uuid::fromString($user->uuid)->getVersion());
        $this->assertSame('uuid', $user->getRouteKeyName());
        $this->assertTrue($user->getIncrementing());
        $this->assertSame('int', $user->getKeyType());
    }

    public function test_tenant_account_generates_uuid_v7_without_replacing_its_internal_id(): void
    {
        $tenantAccount = new TenantAccount;

        $tenantAccount->setUniqueIds();

        $this->assertSame(['uuid'], $tenantAccount->uniqueIds());
        $this->assertSame(7, Uuid::fromString($tenantAccount->uuid)->getVersion());
        $this->assertSame('uuid', $tenantAccount->getRouteKeyName());
        $this->assertTrue($tenantAccount->getIncrementing());
        $this->assertSame('int', $tenantAccount->getKeyType());
    }

    public function test_tenant_user_branch_generates_uuid_v7_without_replacing_its_internal_id(): void
    {
        $tenantUserBranch = new TenantUserBranch;

        $tenantUserBranch->setUniqueIds();

        $this->assertSame(['uuid'], $tenantUserBranch->uniqueIds());
        $this->assertSame(7, Uuid::fromString($tenantUserBranch->uuid)->getVersion());
        $this->assertSame('uuid', $tenantUserBranch->getRouteKeyName());
        $this->assertTrue($tenantUserBranch->getIncrementing());
        $this->assertSame('int', $tenantUserBranch->getKeyType());
    }
}
