<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosDiningFloorPlanRouteTest extends TestCase
{
    #[Test]
    public function dining_floor_plan_route_is_versioned_and_tenant_protected(): void
    {
        $storeRoute = Route::getRoutes()->getByName('api.v1.web.pos.dining-areas.store');
        $areaUpdateRoute = Route::getRoutes()->getByName('api.v1.web.pos.dining-areas.update');
        $tableUpdateRoute = Route::getRoutes()->getByName('api.v1.web.pos.dining-tables.update');

        $this->assertNotNull($storeRoute);
        $this->assertSame('api/v1/web/dining-areas', $storeRoute->uri());
        $this->assertSame(['POST'], $storeRoute->methods());
        $this->assertContains('tenant.auth', $storeRoute->gatherMiddleware());
        $this->assertContains('tenant.session', $storeRoute->gatherMiddleware());
        $this->assertContains('tenant.branch', $storeRoute->gatherMiddleware());

        $this->assertNotNull($areaUpdateRoute);
        $this->assertSame('api/v1/web/dining-areas/{diningArea}', $areaUpdateRoute->uri());
        $this->assertSame(['PATCH'], $areaUpdateRoute->methods());

        $this->assertNotNull($tableUpdateRoute);
        $this->assertSame('api/v1/web/dining-tables/{diningTable}', $tableUpdateRoute->uri());
        $this->assertSame(['PATCH'], $tableUpdateRoute->methods());
    }
}
