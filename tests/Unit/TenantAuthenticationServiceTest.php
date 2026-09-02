<?php

namespace Tests\Unit;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Landlord\Auth\TenantLoginContextService;
use App\Services\Landlord\Tenancy\TenantConnectionManager;
use App\Services\Tenant\Auth\TenantAuthenticationService;
use App\Services\Tenant\Auth\TenantLoginService;
use Mockery;
use Tests\TestCase;

class TenantAuthenticationServiceTest extends TestCase
{
    public function test_it_connects_automatically_when_the_user_has_one_workspace(): void
    {
        $data = ['login' => 'owner@example.com', 'password' => 'password'];
        $user = new User;
        $tenant = $this->tenant('0195ef14-6868-7bd5-9d8b-088395d78901');
        $tenants = collect([$tenant]);

        $loginService = Mockery::mock(TenantLoginService::class);
        $loginService->expects('authenticate')->with($data)->andReturn($user);
        $loginService->expects('login')
            ->with($data, false, $user)
            ->andReturn(['access_token' => 'test-token']);

        $contextService = Mockery::mock(TenantLoginContextService::class);
        $contextService->expects('activeTenants')->with($user)->andReturn($tenants);
        $contextService->expects('resolve')->with($tenants, null)->andReturn($tenant);

        $connectionManager = Mockery::mock(TenantConnectionManager::class);
        $connectionManager->expects('connectByUuid')->with($tenant->uuid)->andReturn($tenant);

        $result = (new TenantAuthenticationService(
            $loginService,
            $contextService,
            $connectionManager,
        ))->authenticate($data);

        $this->assertFalse($result['requires_tenant_selection']);
        $this->assertSame($tenant, $result['tenant']);
        $this->assertSame('test-token', $result['access_token']);
    }

    public function test_it_returns_workspace_selection_before_opening_a_tenant_connection(): void
    {
        $data = ['login' => 'manager@example.com', 'password' => 'password'];
        $user = new User;
        $tenants = collect([
            $this->tenant('0195ef14-6868-7bd5-9d8b-088395d78901'),
            $this->tenant('0195ef14-6868-7bd5-9d8b-088395d78902'),
        ]);

        $loginService = Mockery::mock(TenantLoginService::class);
        $loginService->expects('authenticate')->with($data)->andReturn($user);
        $loginService->shouldNotReceive('login');

        $contextService = Mockery::mock(TenantLoginContextService::class);
        $contextService->expects('activeTenants')->with($user)->andReturn($tenants);
        $contextService->expects('resolve')->with($tenants, null)->andReturnNull();

        $connectionManager = Mockery::mock(TenantConnectionManager::class);
        $connectionManager->shouldNotReceive('connectByUuid');

        $result = (new TenantAuthenticationService(
            $loginService,
            $contextService,
            $connectionManager,
        ))->authenticate($data);

        $this->assertTrue($result['requires_tenant_selection']);
        $this->assertSame($tenants, $result['tenants']);
    }

    private function tenant(string $uuid): Tenant
    {
        $tenant = new Tenant;
        $tenant->setRawAttributes(['uuid' => $uuid]);

        return $tenant;
    }
}
