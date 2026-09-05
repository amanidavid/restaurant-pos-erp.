<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosOrderItemUpdateRouteTest extends TestCase
{
    #[Test]
    public function order_item_update_route_is_versioned_and_tenant_protected(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.web.pos.orders.items.update');

        $this->assertNotNull($route);
        $this->assertSame('api/v1/web/orders/{order}/items/{orderItem}', $route->uri());
        $this->assertContains('tenant.auth', $route->gatherMiddleware());
        $this->assertContains('tenant.session', $route->gatherMiddleware());
    }
}
