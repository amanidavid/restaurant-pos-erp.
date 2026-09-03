<?php

namespace Tests\Unit;

use App\Models\Tenant\UnitOfMeasure;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnitOfMeasureTest extends TestCase
{
    #[Test]
    public function it_uses_the_existing_units_of_measure_table(): void
    {
        $this->assertSame('units_of_measure', (new UnitOfMeasure)->getTable());
    }
}
