<?php

namespace Tests\Unit\Http\Requests\Web\Pos\Dining;

use App\Http\Requests\Web\Pos\Dining\StoreDiningFloorPlanRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreDiningFloorPlanRequestTest extends TestCase
{
    #[Test]
    public function it_requires_a_bounded_array_of_areas_and_nested_tables(): void
    {
        $rules = (new StoreDiningFloorPlanRequest)->rules();

        $this->assertSame(['required', 'array', 'min:1', 'max:25'], $rules['areas']);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $rules['areas.*.tables.*.capacity']);
        $this->assertContains('max:200', $rules['areas.*.tables']);
        $this->assertArrayHasKey('areas.*.tables.*.shape', $rules);
    }
}
