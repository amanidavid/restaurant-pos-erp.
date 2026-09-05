<?php

namespace Tests\Unit\Http\Requests\Web\Pos;

use App\Http\Requests\Web\Pos\UpdateOrderItemRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateOrderItemRequestTest extends TestCase
{
    #[Test]
    public function item_update_forbids_product_replacement_and_validates_modifiers(): void
    {
        $rules = (new UpdateOrderItemRequest)->rules();

        $this->assertSame(['prohibited'], $rules['branch_menu_item_uuid']);
        $this->assertContains('decimal:0,4', $rules['quantity']);
        $this->assertSame(['sometimes', 'array', 'max:30'], $rules['modifiers']);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $rules['modifiers.*.quantity']);
    }

    #[Test]
    public function item_update_requires_a_change_and_rejects_product_replacement(): void
    {
        $rules = (new UpdateOrderItemRequest)->rules();

        $this->assertTrue(Validator::make([], $rules)->fails());
        $this->assertTrue(Validator::make([
            'branch_menu_item_uuid' => '0195ef14-6868-7bd5-9d8b-088395d78901',
            'quantity' => '2.0000',
        ], $rules)->fails());
        $this->assertFalse(Validator::make(['quantity' => '2.0000'], $rules)->fails());
    }
}
