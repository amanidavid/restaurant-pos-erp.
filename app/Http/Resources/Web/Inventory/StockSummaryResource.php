<?php

namespace App\Http\Resources\Web\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ingredient' => [
                'uuid' => $this->ingredient_uuid,
                'name' => $this->ingredient_name,
                'sku' => $this->ingredient_sku,
                'base_unit' => [
                    'uuid' => $this->base_unit_uuid,
                    'name' => $this->base_unit_name,
                    'symbol' => $this->base_unit_symbol,
                ],
            ],
            'warehouse' => [
                'uuid' => $this->warehouse_uuid,
                'name' => $this->warehouse_name,
            ],
            'branch' => [
                'uuid' => $this->branch_uuid,
                'name' => $this->branch_name,
            ],
            'quantity_on_hand' => $this->quantity_on_hand,
            'stock_value' => $this->stock_value,
            'earliest_expiry_date' => $this->earliest_expiry_date,
        ];
    }
}
