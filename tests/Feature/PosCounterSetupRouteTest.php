<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosCounterSetupRouteTest extends TestCase
{
    #[Test]
    public function counter_setup_routes_are_versioned_and_tenant_protected(): void
    {
        $storeRoute = Route::getRoutes()->getByName('api.v1.web.pos.counters.store');
        $updateRoute = Route::getRoutes()->getByName('api.v1.web.pos.counters.update');

        $this->assertNotNull($storeRoute);
        $this->assertSame('api/v1/web/counters', $storeRoute->uri());
        $this->assertSame(['POST'], $storeRoute->methods());
        $this->assertContains('tenant.auth', $storeRoute->gatherMiddleware());
        $this->assertContains('tenant.session', $storeRoute->gatherMiddleware());

        $this->assertNotNull($updateRoute);
        $this->assertSame('api/v1/web/counters/{counter}', $updateRoute->uri());
        $this->assertSame(['PATCH'], $updateRoute->methods());
    }
}
