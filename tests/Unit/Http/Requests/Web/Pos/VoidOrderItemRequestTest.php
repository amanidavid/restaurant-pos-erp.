<?php

namespace Tests\Unit\Http\Requests\Web\Pos;

use App\Http\Requests\Web\Pos\VoidOrderItemRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoidOrderItemRequestTest extends TestCase
{
    #[Test]
    public function a_void_requires_a_meaningful_reason(): void
    {
        $rules = (new VoidOrderItemRequest)->rules();

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make(['reason' => 'No'], $rules)->fails());
        $this->assertFalse(Validator::make(['reason' => 'Customer changed their mind.'], $rules)->fails());
    }
}
