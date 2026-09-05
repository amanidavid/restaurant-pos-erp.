<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosOrderKitchenDispatchRouteTest extends TestCase
{
    #[Test]
    public function send_to_kitchen_route_is_versioned_and_tenant_protected(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.web.pos.orders.send-to-kitchen');

        $this->assertNotNull($route);
        $this->assertSame('api/v1/web/orders/{order}/send-to-kitchen', $route->uri());
        $this->assertContains('tenant.auth', $route->gatherMiddleware());
        $this->assertContains('tenant.session', $route->gatherMiddleware());
    }
}
