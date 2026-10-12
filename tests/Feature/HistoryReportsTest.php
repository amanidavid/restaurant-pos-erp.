<?php

namespace Tests\Feature;

use App\Http\Requests\Web\Reports\HistoryRequest;
use App\Http\Resources\Web\Foundation\BusinessDayHistoryResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDayCashReconciliation;
use App\Models\Tenant\CashAccount;
use App\Services\Tenant\Finance\CashAccountHistoryService;
use App\Services\Tenant\Foundation\BusinessDayHistoryService;
use App\Services\Tenant\Foundation\HistoryDateRange;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HistoryReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('tenant');
        foreach ([
            'business_dates' => ['branch_id', 'business_date', 'business_day_number', 'status', 'opened_at', 'closed_at'],
            'orders' => ['branch_id', 'business_date_id', 'status', 'subtotal_amount', 'discount_amount', 'total_amount'],
            'payments' => ['order_id'],
            'refunds' => ['payment_id', 'branch_id', 'status', 'amount'],
            'business_day_cash_reconciliations' => ['branch_id', 'business_date_id', 'cash_account_id', 'status', 'expected_cash_amount', 'counted_cash_amount', 'variance_amount', 'opening_float_amount', 'closed_at'],
            'cash_movements' => ['branch_id', 'business_date_id', 'cash_account_id', 'moved_at'],
        ] as $name => $columns) {
            DB::connection('tenant')->getSchemaBuilder()->create($name, function (Blueprint $table) use ($columns): void {
                $table->id();
                $table->string('uuid')->nullable();
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
    }

    public function test_business_summary_is_unpaginated_branch_scoped_and_refund_adjusted(): void
    {
        foreach ([1 => 1, 2 => 1, 3 => 2] as $id => $branch) {
            DB::connection('tenant')->table('business_dates')->insert([
                'id' => $id, 'branch_id' => $branch, 'uuid' => 'day-'.$id,
                'business_date' => '2026-10-06', 'status' => $id === 1 ? 'closed' : 'open',
            ]);
        }
        foreach ([[1, 1, 'closed', 100, 10, 108], [2, 2, 'closed', 50, 0, 50], [3, 2, 'served', 50, 0, 50], [4, 1, 'cancelled', 999, 0, 999], [5, 3, 'closed', 999, 0, 999]] as [$id, $day, $status, $subtotal, $discount, $total]) {
            DB::connection('tenant')->table('orders')->insert([
                'id' => $id, 'branch_id' => $day === 3 ? 2 : 1, 'business_date_id' => $day, 'status' => $status,
                'subtotal_amount' => $subtotal, 'discount_amount' => $discount, 'total_amount' => $total,
            ]);
        }
        DB::connection('tenant')->table('payments')->insert(['id' => 1, 'order_id' => 1]);
        DB::connection('tenant')->table('refunds')->insert([
            ['payment_id' => 1, 'branch_id' => 1, 'status' => 'completed', 'amount' => 12],
            ['payment_id' => 1, 'branch_id' => 1, 'status' => 'pending', 'amount' => 100],
        ]);
        $result = app(BusinessDayHistoryService::class)->history($this->branch(), $this->range(), 1);
        $this->assertSame('130.00', $result['summary']['net_sales_amount']);
        $this->assertSame(3, $result['summary']['orders_count']);
        $this->assertSame(1, $result['summary']['open_business_days_count']);
        $this->assertSame(2, $result['page']->total());
        $response = ApiResponse::resource(BusinessDayHistoryResource::collection($result['page'])->additional([
            'summary' => $result['summary'], 'filters' => $this->range(),
        ]), 'History loaded.')->getData(true);
        $this->assertSame('130.00', $response['summary']['net_sales_amount']);
        $this->assertCount(1, $response['data']);
        $this->assertSame(2, $response['meta']['total']);
    }

    public function test_cash_cards_only_sum_closed_selected_account_reconciliations(): void
    {
        DB::connection('tenant')->table('business_dates')->insert(['id' => 1, 'branch_id' => 1, 'business_date' => '2026-10-06']);
        foreach ([[1, 'closed', 100, 90, -10], [1, 'open', 500, 500, 0], [2, 'closed', 999, 999, 0]] as [$account, $status, $expected, $counted, $variance]) {
            DB::connection('tenant')->table('business_day_cash_reconciliations')->insert([
                'business_date_id' => 1, 'branch_id' => 1, 'cash_account_id' => $account,
                'status' => $status, 'expected_cash_amount' => $expected, 'counted_cash_amount' => $counted, 'variance_amount' => $variance,
            ]);
        }
        $result = app(CashAccountHistoryService::class)->history($this->branch(), (new CashAccount)->forceFill(['id' => 1]), $this->range(), 1);
        $this->assertSame(['expected_cash_amount' => '100.00', 'counted_cash_amount' => '90.00', 'variance_amount' => '-10.00'], $result['summary']);
        $this->assertSame(2, $result['page']->total());
    }

    public function test_date_presets_and_invalid_custom_range(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05T22:00:00Z'));
        try {
            $dates = app(HistoryDateRange::class);
            $this->assertSame('2026-10-06', $dates->resolve('Africa/Nairobi', [])['from']);
            $this->assertSame('2026-10-05', $dates->resolve('Africa/Nairobi', ['period' => 'this_week'])['from']);
            $this->assertSame('2026-10-31', $dates->resolve('Africa/Nairobi', ['period' => 'this_month'])['to']);
        } finally {
            CarbonImmutable::setTestNow();
        }
        $this->assertTrue(Validator::make(['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01'], (new HistoryRequest)->rules())->fails());
        foreach (['branches.business-days.index', 'cash-accounts.reconciliations.index', 'cash-reconciliations.movements.index'] as $name) {
            $route = Route::getRoutes()->getByName('api.v1.web.'.$name);
            $this->assertContains('tenant.auth', $route->gatherMiddleware());
            $this->assertContains('tenant.branch', $route->gatherMiddleware());
        }
    }

    private function branch(): Branch
    {
        return (new Branch)->forceFill(['id' => 1, 'timezone' => 'Africa/Nairobi']);
    }

    public function test_empty_period_returns_zero_cards_and_movements_are_account_day_and_branch_scoped(): void
    {
        $empty = app(BusinessDayHistoryService::class)->history($this->branch(), $this->range(), 15);
        $this->assertSame(['net_sales_amount' => '0.00', 'orders_count' => 0, 'open_business_days_count' => 0], $empty['summary']);
        $this->assertSame(0, $empty['page']->total());

        foreach ([[1, 1, 1], [2, 1, 1], [1, 2, 1], [1, 1, 2]] as [$branch, $day, $account]) {
            DB::connection('tenant')->table('cash_movements')->insert([
                'branch_id' => $branch, 'business_date_id' => $day, 'cash_account_id' => $account,
                'moved_at' => '2026-10-06 12:00:00',
            ]);
        }
        $reconciliation = (new BusinessDayCashReconciliation)->forceFill([
            'cash_account_id' => 1, 'business_date_id' => 1,
        ]);
        $page = app(CashAccountHistoryService::class)->movements($this->branch(), $reconciliation, 15);
        $this->assertSame(1, $page->total());
    }

    private function range(): array
    {
        return ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31'];
    }
}
