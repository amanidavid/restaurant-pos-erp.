<?php

namespace Database\Seeders\Landlord;

use Illuminate\Database\Seeder;

class LandlordDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseClusterSeeder::class,
            PlatformAccessSeeder::class,
            FeaturePackageSeeder::class,
            SettingDefinitionSeeder::class,
            ReportScheduleTemplateSeeder::class,
            BackupRetentionSupportSeeder::class,
            PlatformAuditPolicySeeder::class,
        ]);
    }
}
