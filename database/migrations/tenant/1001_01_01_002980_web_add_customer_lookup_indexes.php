<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('CREATE INDEX IF NOT EXISTS customers_company_name_prefix_index ON customers (company_id, name text_pattern_ops)');
        $connection->statement('CREATE INDEX IF NOT EXISTS customers_company_phone_prefix_index ON customers (company_id, phone text_pattern_ops) WHERE phone IS NOT NULL');
        $connection->statement('CREATE INDEX IF NOT EXISTS customers_company_email_prefix_index ON customers (company_id, lower(email) text_pattern_ops) WHERE email IS NOT NULL');
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS customers_company_email_lower_unique ON customers (company_id, lower(email)) WHERE email IS NOT NULL');
    }

    public function down(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('DROP INDEX IF EXISTS customers_company_email_lower_unique');
        $connection->statement('DROP INDEX IF EXISTS customers_company_email_prefix_index');
        $connection->statement('DROP INDEX IF EXISTS customers_company_phone_prefix_index');
        $connection->statement('DROP INDEX IF EXISTS customers_company_name_prefix_index');
    }
};
