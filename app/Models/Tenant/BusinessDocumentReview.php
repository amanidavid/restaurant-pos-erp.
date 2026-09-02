<?php

namespace App\Models\Tenant;

class BusinessDocumentReview extends TenantModel
{
    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime'];
    }
}
