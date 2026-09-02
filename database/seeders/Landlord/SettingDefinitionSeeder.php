<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class SettingDefinitionSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'setting_definitions')) {
            return;
        }

        $settings = [
            ['platform', 'support_email', 'Support email', 'platform', 'string', false],
            ['platform', 'default_backup_location', 'Default backup location', 'platform', 'string', false],
            ['tenant', 'timezone', 'Tenant timezone', 'tenant', 'string', false],
            ['tenant', 'currency_code', 'Tenant currency', 'tenant', 'string', false],
            ['branch', 'business_day_opens_at', 'Business day opens at', 'branch', 'time', false],
            ['branch', 'business_day_closes_at', 'Business day closes at', 'branch', 'time', false],
            ['branch', 'receipt_footer', 'Receipt footer', 'branch', 'string', false],
            ['branch', 'auto_print_receipt', 'Auto print receipt', 'branch', 'boolean', false],
            ['integration', 'payment_credentials', 'Payment credentials', 'branch', 'object', true],
            ['notification', 'sms_sender_id', 'SMS sender ID', 'tenant', 'string', false],
        ];

        foreach ($settings as [$group, $name, $label, $scope, $type, $sensitive]) {
            $this->upsertRow(self::CONNECTION, 'setting_definitions', [
                'group' => $group,
                'name' => $name,
            ], [
                'label' => $label,
                'description' => $label,
                'scope' => $scope,
                'value_type' => $type,
                'default_payload' => null,
                'validation_rules' => null,
                'allowed_values' => null,
                'is_sensitive' => $sensitive,
                'requires_restart' => false,
                'requires_reconnect' => $group === 'integration',
                'status' => 'active',
            ]);
        }
    }
}
