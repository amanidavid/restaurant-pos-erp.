<?php

namespace App\Models\Landlord;

class BackupStorageLocation extends LandlordModel
{
    protected function casts(): array
    {
        return ['encrypted_credentials' => 'encrypted:array', 'settings' => 'array', 'is_default' => 'boolean'];
    }
}
