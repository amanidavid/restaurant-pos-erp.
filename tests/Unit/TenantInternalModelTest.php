<?php

namespace Tests\Unit;

use App\Models\Tenant\MenuItemModifierGroup;
use App\Models\Tenant\ReservationTable;
use App\Models\Tenant\UserDepartmentAccess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TenantInternalModelTest extends TestCase
{
    #[Test]
    public function internal_tenant_link_models_do_not_generate_public_uuids(): void
    {
        foreach ([
            new MenuItemModifierGroup,
            new ReservationTable,
            new UserDepartmentAccess,
        ] as $model) {
            $this->assertSame('tenant', $model->getConnectionName());
            $this->assertFalse($model->usesUniqueIds);
        }
    }
}
