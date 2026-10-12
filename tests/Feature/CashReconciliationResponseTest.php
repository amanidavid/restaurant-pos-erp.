<?php

namespace Tests\Feature;

use App\Http\Resources\Web\Finance\CashMovementResource;
use App\Http\Resources\Web\Finance\CashReconciliationResource;
use App\Models\Tenant\BusinessDate;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Models\Tenant\CashMovement;
use App\Services\Tenant\Finance\CashReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Web\Finance\ReplaceCashCountRequest;
use Tests\TestCase;

class CashReconciliationResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('tenant');
        $schema = DB::connection('tenant')->getSchemaBuilder();
        $schema->create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid');
            $table->string('name');
            $table->string('currency_code');
        });
        $schema->create('cash_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_day_cash_reconciliation_id');
            $table->decimal('denomination_value', 18, 2);
            $table->integer('quantity');
            $table->decimal('line_amount', 18, 2);
        });
        $schema->create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cash_account_id');
            $table->unsignedBigInteger('business_date_id');
            $table->string('movement_reason');
            $table->decimal('amount', 18, 2);
        });
        DB::connection('tenant')->table('cash_accounts')->insert([
            'id' => 1, 'uuid' => 'account-uuid', 'name' => 'Cash Drawer', 'currency_code' => 'TZS',
        ]);
    }

    public function test_preview_returns_saved_lines_and_totals_without_n_plus_one_queries(): void
    {
        DB::connection('tenant')->table('cash_count_lines')->insert([
            'business_day_cash_reconciliation_id' => 1,
            'denomination_value' => '1000.00', 'quantity' => 7, 'line_amount' => '7000.00',
        ]);
        foreach ([['cash_sale', '8000.00'], ['cash_out', '2000.00']] as [$reason, $amount]) {
            DB::connection('tenant')->table('cash_movements')->insert([
                'cash_account_id' => 1, 'business_date_id' => 1, 'movement_reason' => $reason, 'amount' => $amount,
            ]);
        }
        // Another business day's movement must not affect the preview.
        DB::connection('tenant')->table('cash_movements')->insert([
            'cash_account_id' => 1, 'business_date_id' => 2, 'movement_reason' => 'cash_in', 'amount' => '999.00',
        ]);
        $closing = $this->reconciliation(1, 'closing');
        $open = $this->reconciliation(2, 'open');
        $closed = $this->reconciliation(3, 'closed')->forceFill([
            'expected_cash_amount' => '12.00', 'counted_cash_amount' => '11.00', 'variance_amount' => '-1.00',
        ]);
        DB::connection('tenant')->enableQueryLog();
        app(CashReconciliationService::class)->prepareForResponse(new Collection([$closing, $open, $closed]));
        $this->assertCount(3, DB::connection('tenant')->getQueryLog());
        $response = (new CashReconciliationResource($closing))->resolve(Request::create('/'));
        $this->assertSame('account-uuid', $response['cash_account']['uuid']);
        $this->assertSame('7000.00', $response['expected_cash_amount']);
        $this->assertSame('7000.00', $response['counted_cash_amount']);
        $this->assertSame('0.00', $response['variance_amount']);
        $this->assertSame([['denomination_value' => '1000.00', 'quantity' => 7, 'line_amount' => '7000.00']], $response['lines']);
        $this->assertNull($open->counted_cash_amount);
        $this->assertSame('12.00', $closed->expected_cash_amount);
        $this->assertSame('-1.00', $closed->variance_amount);
    }

    public function test_movement_resource_returns_a_success_response_without_internal_ids(): void
    {
        $movement = (new CashMovement)->forceFill([
            'id' => 1, 'uuid' => 'movement-uuid', 'amount' => '5000.00',
            'movement_reason' => 'cash_out', 'notes' => 'Kitchen supplies',
        ]);
        $movement->setRelation('cashAccount', (new CashAccount)->forceFill([
            'uuid' => 'account-uuid', 'name' => 'Cash Drawer', 'currency_code' => 'TZS',
        ]));
        $movement->setRelation('businessDate', (new BusinessDate)->forceFill(['uuid' => 'day-uuid']));
        $response = ApiResponse::resource(new CashMovementResource($movement), 'Cash movement recorded successfully.', 201);
        $data = $response->getData(true);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('day-uuid', $data['data']['business_day_uuid']);
        $this->assertSame('5000.00', $data['data']['amount']);
        $this->assertArrayNotHasKey('id', $data['data']);
    }

    public function test_duplicate_denominations_are_rejected_before_database_insert(): void
    {
        $validator = Validator::make(['lines' => [
            ['denomination_value' => '1000', 'quantity' => 1],
            ['denomination_value' => '1000.00', 'quantity' => 2],
        ]], (new ReplaceCashCountRequest)->rules());
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('lines.0.denomination_value'));
    }

    private function reconciliation(int $id, string $status): BusinessDayCashReconciliation
    {
        return (new BusinessDayCashReconciliation)->forceFill([
            'id' => $id, 'cash_account_id' => 1, 'business_date_id' => 1,
            'uuid' => 'reconciliation-'.$id, 'status' => $status, 'opening_float_amount' => '1000.00',
        ]);
    }
}
