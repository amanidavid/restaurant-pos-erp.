<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PosReceiptPrintingRoutesTest extends TestCase
{
    #[Test]
    public function receipt_printing_routes_are_versioned_and_tenant_protected(): void
    {
        $printRoute = Route::getRoutes()->getByName('api.v1.web.pos.receipts.print');
        $showRoute = Route::getRoutes()->getByName('api.v1.web.pos.print-jobs.show');

        $this->assertNotNull($printRoute);
        $this->assertSame('api/v1/web/receipts/{receipt}/print', $printRoute->uri());
        $this->assertContains('tenant.auth', $printRoute->gatherMiddleware());
        $this->assertContains('tenant.session', $printRoute->gatherMiddleware());

        $this->assertNotNull($showRoute);
        $this->assertSame('api/v1/web/print-jobs/{printJob}', $showRoute->uri());
        $this->assertContains('tenant.auth', $showRoute->gatherMiddleware());
        $this->assertContains('tenant.session', $showRoute->gatherMiddleware());

        $this->assertNull(Route::getRoutes()->getByName('api.v1.web.pos.kitchen-tickets.print'));
    }
}
