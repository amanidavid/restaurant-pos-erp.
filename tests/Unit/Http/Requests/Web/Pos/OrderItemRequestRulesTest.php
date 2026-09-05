<?php

namespace Tests\Unit\Http\Requests\Web\Pos;

use App\Http\Requests\Web\Pos\AddOrderItemsRequest;
use App\Http\Requests\Web\Pos\StoreOrderRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderItemRequestRulesTest extends TestCase
{
    #[Test]
    public function create_and_add_item_requests_share_the_complete_item_contract(): void
    {
        $createRules = (new StoreOrderRequest)->rules();
        $addRules = (new AddOrderItemsRequest)->rules();

        foreach (array_keys($addRules) as $field) {
            $this->assertArrayHasKey($field, $createRules);
            $this->assertSame($createRules[$field], $addRules[$field]);
        }

        $this->assertSame(['required', 'array', 'min:1', 'max:100'], $addRules['items']);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $addRules['items.*.modifiers.*.quantity']);
    }
}
