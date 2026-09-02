<?php

namespace App\Models\Tenant;

class BusinessDocument extends TenantModel
{
    protected function casts(): array
    {
        return [
            'document_date' => 'immutable_date',
            'expires_on' => 'immutable_date',
        ];
    }
}
