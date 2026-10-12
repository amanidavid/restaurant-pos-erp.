<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrTimekeepingRoutesTest extends TestCase
{
    #[Test]
    public function timekeeping_routes_are_versioned_and_branch_scoped(): void
    {
        $routes = [
            'api.v1.web.hr.shift-definitions.index' => 'GET',
            'api.v1.web.hr.shift-definitions.store' => 'POST',
            'api.v1.web.hr.shift-definitions.update' => 'PATCH',
            'api.v1.web.hr.shift-assignments.index' => 'GET',
            'api.v1.web.hr.shift-assignments.store' => 'POST',
            'api.v1.web.hr.shift-assignments.batch.store' => 'POST',
            'api.v1.web.hr.shift-assignments.update' => 'PATCH',
            'api.v1.web.hr.attendance.clock-in' => 'POST',
            'api.v1.web.hr.attendance.clock-out' => 'POST',
            'api.v1.web.hr.attendance.clock-in.batch' => 'POST',
            'api.v1.web.hr.attendance.clock-out.batch' => 'POST',
            'api.v1.web.hr.attendance.me' => 'GET',
            'api.v1.web.hr.attendance-records.index' => 'GET',
            'api.v1.web.hr.attendance-records.manual.store' => 'POST',
            'api.v1.web.hr.attendance-records.adjust' => 'PATCH',
            'api.v1.web.me.shifts.index' => 'GET',
        ];

        foreach ($routes as $name => $method) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} is not registered.");
            $this->assertContains($method, $route->methods());
            $this->assertContains('tenant.auth', $route->gatherMiddleware());
            $this->assertContains('tenant.session', $route->gatherMiddleware());
            $this->assertContains('tenant.branch', $route->gatherMiddleware());
        }
    }
}
