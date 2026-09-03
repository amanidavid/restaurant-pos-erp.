<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosReferenceRoutesTest extends TestCase
{
    #[Test]
    public function pos_reference_routes_are_versioned_and_tenant_protected(): void
    {
        $routes = [
            'api.v1.web.pos.sales-channels.index' => 'api/v1/web/sales-channels',
            'api.v1.web.pos.service-types.index' => 'api/v1/web/service-types',
            'api.v1.web.pos.payment-methods.index' => 'api/v1/web/payment-methods',
            'api.v1.web.pos.counters.index' => 'api/v1/web/counters',
            'api.v1.web.pos.dining-areas.index' => 'api/v1/web/dining-areas',
            'api.v1.web.pos.dining-tables.index' => 'api/v1/web/dining-tables',
        ];

        foreach ($routes as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} is not registered.");
            $this->assertSame($uri, $route->uri());
            $this->assertContains('tenant.auth', $route->gatherMiddleware());
            $this->assertContains('tenant.session', $route->gatherMiddleware());
        }
    }
}
