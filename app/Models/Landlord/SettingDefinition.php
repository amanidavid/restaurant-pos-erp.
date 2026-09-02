<?php

namespace App\Models\Landlord;

class SettingDefinition extends LandlordModel
{
    protected function casts(): array
    {
        return ['default_value' => 'array', 'validation_rules' => 'array', 'is_sensitive' => 'boolean', 'is_tenant_overridable' => 'boolean'];
    }
}
