<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('tenant_users') || ! $schema->hasColumn('tenant_users', 'user_id')) {
            return;
        }

        $connection = DB::connection('tenant');
        $connection->statement('ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_user_id_foreign');
        $connection->statement('ALTER TABLE tenant_users ALTER COLUMN user_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Existing rows may intentionally have a null legacy user_id after cutover.
        // The legacy foreign key is therefore not recreated automatically.
    }
};
