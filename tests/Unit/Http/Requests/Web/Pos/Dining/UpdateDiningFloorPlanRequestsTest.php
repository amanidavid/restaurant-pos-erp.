<?php

namespace Tests\Unit\Http\Requests\Web\Pos\Dining;

use App\Http\Requests\Web\Pos\Dining\UpdateDiningAreaRequest;
use App\Http\Requests\Web\Pos\Dining\UpdateDiningTableRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateDiningFloorPlanRequestsTest extends TestCase
{
    #[Test]
    public function area_and_table_updates_validate_only_supported_setup_fields(): void
    {
        $areaRules = (new UpdateDiningAreaRequest)->rules();
        $tableRules = (new UpdateDiningTableRequest)->rules();

        $this->assertArrayHasKey('status', $areaRules);
        $this->assertArrayHasKey('description', $areaRules);
        $this->assertArrayHasKey('dining_area_uuid', $tableRules);
        $this->assertSame(['sometimes', 'required', 'integer', 'min:1', 'max:100'], $tableRules['capacity']);
    }
}
