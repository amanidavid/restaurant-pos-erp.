<?php

namespace Tests\Feature;

use App\Models\Tenant\BranchMenuItem;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\TaxCode;
use App\Services\Tenant\Inventory\InventoryService;
use App\Services\Tenant\Pos\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class ServiceReadabilityRegressionTest extends TestCase
{
    public function test_item_tax_calculations_preserve_inclusive_exclusive_and_untaxed_totals(): void
    {
        $method = new ReflectionMethod(OrderService::class, 'itemAmounts');

        foreach ([[null, [0.0, 200.0]], [false, [20.0, 220.0]], [true, [18.18, 200.0]]] as [$inclusive, $expected]) {
            $taxCode = $inclusive === null ? null : (new TaxCode)->forceFill([
                'rate_percentage' => '10.00', 'is_inclusive' => $inclusive,
            ]);
            $menuItem = (new MenuItem)->setRelation('taxCode', $taxCode);
            $menu = (new BranchMenuItem)->setRelation('menuItem', $menuItem);

            $this->assertSame($expected, $method->invoke(app(OrderService::class), '90.00', 2.0, 10.0, $menu));
        }
    }

    public function test_inventory_ingredient_lookup_is_batched_and_preserves_missing_references(): void
    {
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('tenant');
        DB::connection('tenant')->getSchemaBuilder()->create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
        });
        DB::connection('tenant')->table('ingredients')->insert([
            ['id' => 1, 'uuid' => 'ingredient-a'], ['id' => 2, 'uuid' => 'ingredient-b'],
        ]);
        DB::connection('tenant')->enableQueryLog();

        $method = new ReflectionMethod(InventoryService::class, 'ingredientsForItems');
        $ingredients = $method->invoke(app(InventoryService::class), [
            ['ingredient_uuid' => 'ingredient-a'], ['ingredient_uuid' => 'ingredient-a'],
            ['ingredient_uuid' => 'ingredient-b'], ['ingredient_uuid' => 'missing'],
        ]);

        $this->assertCount(1, DB::connection('tenant')->getQueryLog());
        $this->assertCount(2, $ingredients);
        $this->assertSame(1, $ingredients->get('ingredient-a')->id);
        $this->assertNull($ingredients->get('missing'));
    }
}
