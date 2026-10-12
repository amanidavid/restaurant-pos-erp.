<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        DB::connection(self::CONNECTION)->statement(
            'CREATE INDEX IF NOT EXISTS tax_codes_company_rule_period_index ON tax_codes (company_id, status, rate_percentage, is_inclusive, effective_from, effective_to)',
        );
    }

    public function down(): void
    {
        DB::connection(self::CONNECTION)->statement('DROP INDEX IF EXISTS tax_codes_company_rule_period_index');
    }
};
