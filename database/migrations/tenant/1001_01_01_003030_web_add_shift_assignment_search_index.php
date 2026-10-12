<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tenant')->statement(
            'CREATE INDEX IF NOT EXISTS tenant_users_lower_display_name_prefix_index ON tenant_users ((LOWER(display_name)) text_pattern_ops)',
        );
    }

    public function down(): void
    {
        DB::connection('tenant')->statement('DROP INDEX IF EXISTS tenant_users_lower_display_name_prefix_index');
    }
};
