<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganisationDepartmentRoutesTest extends TestCase
{
    #[Test]
    public function department_routes_are_versioned_and_require_branch_context(): void
    {
        $routes = [
            'api.v1.web.organisation.departments.index' => ['GET', 'api/v1/web/organisation/departments'],
            'api.v1.web.organisation.departments.tree' => ['GET', 'api/v1/web/organisation/departments/tree'],
            'api.v1.web.organisation.departments.store' => ['POST', 'api/v1/web/organisation/departments'],
            'api.v1.web.organisation.departments.show' => ['GET', 'api/v1/web/organisation/departments/{department}'],
            'api.v1.web.organisation.departments.update' => ['PATCH', 'api/v1/web/organisation/departments/{department}'],
        ];

        foreach ($routes as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} is not registered.");
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods());
            $this->assertContains('tenant.auth', $route->gatherMiddleware());
            $this->assertContains('tenant.session', $route->gatherMiddleware());
            $this->assertContains('tenant.branch', $route->gatherMiddleware());
        }

        $staffStoreRoute = Route::getRoutes()->getByName('api.v1.web.staff.users.store');

        $this->assertNotNull($staffStoreRoute);
        $this->assertSame('api/v1/web/staff/users', $staffStoreRoute->uri());
        $this->assertSame(['POST'], $staffStoreRoute->methods());
        $this->assertContains('tenant.branch', $staffStoreRoute->gatherMiddleware());
    }
}
