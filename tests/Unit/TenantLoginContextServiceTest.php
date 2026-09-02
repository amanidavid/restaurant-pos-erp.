<?php

namespace Tests\Unit;

use App\Models\Landlord\Tenant;
use App\Services\Landlord\Auth\TenantLoginContextService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class TenantLoginContextServiceTest extends TestCase
{
    private TenantLoginContextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TenantLoginContextService;
    }

    public function test_it_automatically_resolves_a_single_workspace(): void
    {
        $tenant = $this->tenant('0195ef14-6868-7bd5-9d8b-088395d78901');

        $this->assertSame($tenant, $this->service->resolve(collect([$tenant]), null));
    }

    public function test_it_requires_selection_for_multiple_workspaces(): void
    {
        $tenants = collect([
            $this->tenant('0195ef14-6868-7bd5-9d8b-088395d78901'),
            $this->tenant('0195ef14-6868-7bd5-9d8b-088395d78902'),
        ]);

        $this->assertNull($this->service->resolve($tenants, null));
        $this->assertSame($tenants[1], $this->service->resolve($tenants, $tenants[1]->uuid));
    }

    public function test_it_rejects_a_workspace_outside_the_authenticated_memberships(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->service->resolve(
            collect([$this->tenant('0195ef14-6868-7bd5-9d8b-088395d78901')]),
            '0195ef14-6868-7bd5-9d8b-088395d78999',
        );
    }

    public function test_it_rejects_an_account_without_an_active_workspace(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->service->resolve(collect(), null);
    }

    private function tenant(string $uuid): Tenant
    {
        $tenant = new Tenant;
        $tenant->setRawAttributes(['uuid' => $uuid]);

        return $tenant;
    }
}
