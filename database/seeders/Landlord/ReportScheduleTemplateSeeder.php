<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class ReportScheduleTemplateSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'report_schedule_templates')) {
            return;
        }

        $featureId = $this->connection(self::CONNECTION)->table('features')->where('code', 'reports')->value('id');

        $templates = [
            ['Daily Sales Report', 'daily_sales', 'sales', 'daily', '22:00:00', 'pdf'],
            ['Monthly Payroll Summary', 'monthly_payroll_summary', 'payroll', 'monthly', '08:00:00', 'pdf'],
            ['Monthly Tax Summary', 'monthly_tax_summary', 'tax', 'monthly', '08:30:00', 'pdf'],
        ];

        foreach ($templates as [$name, $key, $area, $frequency, $time, $format]) {
            $this->upsertRow(self::CONNECTION, 'report_schedule_templates', [
                'report_key' => $key,
                'frequency' => $frequency,
                'scheduled_time' => $time,
            ], [
                'feature_id' => $featureId,
                'package_id' => null,
                'created_by_platform_user_id' => null,
                'name' => $name,
                'report_area' => $area,
                'timezone_strategy' => 'tenant_timezone',
                'timezone' => null,
                'export_format' => $format,
                'default_filters' => null,
                'delivery_options' => null,
                'allow_tenant_time_override' => false,
                'auto_assign_to_new_tenants' => true,
                'priority' => 5,
                'status' => 'active',
            ]);
        }
    }
}
