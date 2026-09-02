<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class FeaturePackageSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'features')) {
            return;
        }

        $features = [
            'pos' => ['Point of Sale', 'operations', true],
            'inventory' => ['Inventory and Recipes', 'operations', true],
            'finance' => ['Finance and Accounting', 'finance', true],
            'payroll' => ['Payroll', 'hr', true],
            'reports' => ['Reports and Dashboards', 'reporting', true],
            'realtime' => ['Realtime Updates', 'operations', true],
            'integrations' => ['Integrations', 'integrations', true],
            'backups' => ['Tenant Backups', 'platform', true],
            'support' => ['Support Desk', 'platform', false],
        ];

        $featureIds = [];

        foreach ($features as $code => [$name, $category, $billable]) {
            $featureIds[$code] = $this->upsertRow(self::CONNECTION, 'features', [
                'code' => $code,
            ], [
                'name' => $name,
                'description' => $name,
                'category' => $category,
                'status' => 'active',
                'is_billable' => $billable,
            ]);
        }

        $packages = [
            'starter' => ['Starter', 'monthly', 'TZS', 0, ['pos', 'inventory', 'reports', 'support']],
            'professional' => ['Professional', 'monthly', 'TZS', 0, array_keys($features)],
        ];

        foreach ($packages as $code => [$name, $interval, $currency, $price, $packageFeatures]) {
            $packageId = $this->upsertRow(self::CONNECTION, 'packages', [
                'code' => $code,
            ], [
                'name' => $name,
                'description' => $name,
                'billing_interval' => $interval,
                'base_price' => $price,
                'currency_code' => $currency,
                'status' => 'active',
                'is_custom' => false,
            ]);

            foreach ($packageFeatures as $featureCode) {
                $this->connection(self::CONNECTION)->table('package_features')->updateOrInsert([
                    'package_id' => $packageId,
                    'feature_id' => $featureIds[$featureCode],
                ], [
                    'is_enabled' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
