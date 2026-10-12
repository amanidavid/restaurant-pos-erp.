<?php

namespace Tests\Feature;

use App\Models\Tenant\BusinessDate;
use App\Services\Tenant\Foundation\BusinessDateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class BusinessDayCloseValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);
        DB::purge('tenant');
        $schema = DB::connection('tenant')->getSchemaBuilder();

        foreach (['orders', 'shift_sessions', 'business_day_cash_reconciliations'] as $table) {
            $schema->create($table, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('business_date_id');
                $table->unsignedBigInteger('cash_account_id')->nullable();
                $table->string('status');
            });
        }

        $schema->create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('name');
            $table->string('status');
            $table->boolean('requires_reconciliation');
        });
    }

    public function test_optional_account_cannot_replace_a_missing_required_account(): void
    {
        $this->seedAccounts();
        DB::connection('tenant')->table('business_day_cash_reconciliations')->insert([
            ['business_date_id' => 1, 'cash_account_id' => 1, 'status' => 'closed'],
            ['business_date_id' => 1, 'cash_account_id' => 3, 'status' => 'closed'],
        ]);

        try {
            $this->checkCanClose();
            $this->fail('The required Branch Safe reconciliation is missing.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'Open, count, and close reconciliations for these required cash accounts: Branch Safe.',
            ], $exception->errors()['business_day']);
        }
    }

    public function test_extra_closed_optional_account_does_not_block_closing(): void
    {
        $this->seedAccounts();
        foreach ([1, 2, 3] as $accountId) {
            DB::connection('tenant')->table('business_day_cash_reconciliations')->insert([
                'business_date_id' => 1,
                'cash_account_id' => $accountId,
                'status' => 'closed',
            ]);
        }

        $this->checkCanClose();
        $this->addToAssertionCount(1);
    }

    public function test_it_reports_all_actual_blockers(): void
    {
        $this->seedAccounts();
        foreach (['orders', 'shift_sessions', 'business_day_cash_reconciliations'] as $table) {
            DB::connection('tenant')->table($table)->insert([
                'business_date_id' => 1,
                'cash_account_id' => 1,
                'status' => 'open',
            ]);
        }

        try {
            $this->checkCanClose();
            $this->fail('Unfinished work must block closing.');
        } catch (ValidationException $exception) {
            $messages = $exception->errors()['business_day'];
            $this->assertCount(3, $messages);
            $this->assertStringContainsString('orders', $messages[0]);
            $this->assertStringContainsString('1 remaining', $messages[1]);
            $this->assertStringContainsString('Branch Safe', $messages[2]);
        }
    }

    public function test_an_open_shift_does_not_block_business_day_closing(): void
    {
        DB::connection('tenant')->table('shift_sessions')->insert([
            'business_date_id' => 1, 'status' => 'open',
        ]);

        $this->checkCanClose();
        $this->addToAssertionCount(1);
    }

    private function seedAccounts(): void
    {
        foreach ([1 => 'Cash Drawer', 2 => 'Branch Safe', 3 => 'Petty Cash'] as $id => $name) {
            DB::connection('tenant')->table('cash_accounts')->insert([
                'id' => $id,
                'branch_id' => 1,
                'name' => $name,
                'status' => 'active',
                'requires_reconciliation' => $id !== 3,
            ]);
        }
    }

    private function checkCanClose(): void
    {
        $businessDay = (new BusinessDate)->forceFill(['id' => 1, 'branch_id' => 1]);
        $method = new ReflectionMethod(BusinessDateService::class, 'ensureCanClose');
        $method->invoke(new BusinessDateService, $businessDay);
    }
}
