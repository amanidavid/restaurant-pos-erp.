<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        DB::connection(self::CONNECTION)->statement(
            'CREATE INDEX IF NOT EXISTS orders_branch_opened_at_index ON orders (branch_id, opened_at)',
        );
    }

    public function down(): void
    {
        DB::connection(self::CONNECTION)->statement('DROP INDEX IF EXISTS orders_branch_opened_at_index');
    }
};
