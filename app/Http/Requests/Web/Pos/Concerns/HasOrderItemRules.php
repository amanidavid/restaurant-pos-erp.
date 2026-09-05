<?php

namespace App\Http\Requests\Web\Pos\Concerns;

trait HasOrderItemRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function orderItemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.branch_menu_item_uuid' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ] + $this->modifierRules('items.*.modifiers');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function modifierRules(string $attribute): array
    {
        return [
            $attribute => ['sometimes', 'array', 'max:30'],
            $attribute.'.*.modifier_option_uuid' => ['required', 'uuid', 'distinct'],
            $attribute.'.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
