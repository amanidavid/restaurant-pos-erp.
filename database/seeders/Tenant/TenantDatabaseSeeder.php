<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TenantAccessSeeder::class,
            TenantNumberSequenceSeeder::class,
            TenantCatalogPaymentInventorySeeder::class,
            TenantFinanceSeeder::class,
            TenantReportingNotificationAuditSeeder::class,
        ]);
    }
}
