<?php

namespace Tests\Unit\Http\Requests\Web\Pos\Counter;

use App\Http\Requests\Web\Pos\Counter\StoreCountersRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreCountersRequestTest extends TestCase
{
    #[Test]
    public function it_requires_a_bounded_array_of_counters(): void
    {
        $rules = (new StoreCountersRequest)->rules();

        $this->assertSame(['required', 'array', 'min:1', 'max:25'], $rules['counters']);
        $this->assertArrayHasKey('counters.*.type', $rules);
        $this->assertArrayHasKey('counters.*.status', $rules);
    }
}
