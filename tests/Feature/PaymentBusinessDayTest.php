<?php

namespace Tests\Feature;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Order;
use App\Models\Tenant\TenantUser;
use App\Services\Tenant\Pos\PaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentBusinessDayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('tenant');
        $schema = DB::connection('tenant')->getSchemaBuilder();
        foreach ([
            'orders' => ['branch_id', 'business_date_id', 'status', 'total_amount', 'paid_amount'],
            'business_dates' => ['branch_id', 'status'],
            'payment_methods' => ['company_id', 'name', 'method_type', 'status', 'requires_provider', 'allows_change'],
            'companies' => ['base_currency_code'],
            'payments' => ['order_id', 'branch_id', 'business_date_id', 'shift_session_id', 'payment_method_id', 'received_by_tenant_user_id', 'amount', 'change_amount', 'currency_code', 'status', 'paid_at', 'notes'],
            'payment_allocations' => ['payment_id', 'order_id', 'amount'],
            'cash_accounts' => ['branch_id', 'status', 'requires_reconciliation'],
            'business_day_cash_reconciliations' => ['business_date_id', 'cash_account_id', 'status'],
            'cash_movements' => ['cash_account_id', 'branch_id', 'business_date_id', 'shift_session_id', 'payment_id', 'performed_by_tenant_user_id', 'movement_reason', 'amount', 'moved_at', 'notes'],
        ] as $name => $columns) {
            $schema->create($name, function (Blueprint $table) use ($columns): void {
                $table->id();
                $table->string('uuid')->nullable();
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
                $table->timestamps();
            });
        }
        DB::connection('tenant')->table('orders')->insert([
            'id' => 1, 'branch_id' => 1, 'business_date_id' => 1, 'status' => 'served', 'total_amount' => '15000.00', 'paid_amount' => '0.00',
        ]);
        DB::connection('tenant')->table('business_dates')->insert(['id' => 1, 'branch_id' => 1, 'status' => 'open']);
        DB::connection('tenant')->table('companies')->insert(['id' => 1, 'base_currency_code' => 'TZS']);
        DB::connection('tenant')->table('payment_methods')->insert([
            'id' => 1, 'uuid' => 'method-uuid', 'company_id' => 1, 'name' => 'Cash',
            'method_type' => 'cash', 'status' => 'active', 'requires_provider' => 0, 'allows_change' => 1,
        ]);
        DB::connection('tenant')->table('cash_accounts')->insert([
            'id' => 1, 'uuid' => 'account-uuid', 'branch_id' => 1, 'status' => 'active', 'requires_reconciliation' => 1,
        ]);
        DB::connection('tenant')->table('business_day_cash_reconciliations')->insert([
            'business_date_id' => 1, 'cash_account_id' => 1, 'status' => 'open',
        ]);
    }

    public function test_cash_payment_and_movement_are_saved_without_a_shift(): void
    {
        $payment = $this->collect();
        $this->assertNull($payment->shift_session_id);
        $this->assertSame('5000.00', $payment->change_amount);
        $movement = DB::connection('tenant')->table('cash_movements')->first();
        $this->assertNull($movement->shift_session_id);
        $this->assertEquals(15000, $movement->amount);
        $this->assertEquals(1, $movement->business_date_id);
        $this->assertSame('cash_sale', $movement->movement_reason);
    }

    public function test_closed_closing_missing_or_other_branch_business_days_reject_payment(): void
    {
        foreach (['closed', 'closing', 'missing', 'other_branch'] as $case) {
            DB::connection('tenant')->table('business_dates')->where('id', 1)->update([
                'status' => $case === 'closing' ? 'closing' : ($case === 'closed' ? 'closed' : 'open'),
                'branch_id' => $case === 'other_branch' ? 2 : 1,
            ]);
            DB::connection('tenant')->table('orders')->where('id', 1)->update(['business_date_id' => $case === 'missing' ? 999 : 1]);
            try {
                $this->collect();
                $this->fail('Payment must be rejected for '.$case);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('business_day', $exception->errors());
            }
        }
        $this->assertSame(0, DB::connection('tenant')->table('payments')->count());
    }

    public function test_cash_payment_still_requires_an_open_reconciliation(): void
    {
        DB::connection('tenant')->table('business_day_cash_reconciliations')->update(['status' => 'closing']);
        try {
            $this->collect();
            $this->fail('Cash reconciliation must be open.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cash_account_uuid', $exception->errors());
        }
    }

    private function collect(): mixed
    {
        return app(PaymentService::class)->collect(
            (new Order)->forceFill(['id' => 1]),
            (new Branch)->forceFill(['id' => 1, 'company_id' => 1]),
            (new TenantUser)->forceFill(['id' => 1]),
            ['payment_method_uuid' => 'method-uuid', 'cash_account_uuid' => 'account-uuid', 'received_amount' => '20000.00'],
        );
    }
}
