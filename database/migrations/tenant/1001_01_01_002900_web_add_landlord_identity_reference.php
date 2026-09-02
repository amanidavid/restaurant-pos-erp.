<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('tenant_users')) {
            return;
        }

        if (! $schema->hasColumn('tenant_users', 'landlord_user_uuid')) {
            $schema->table('tenant_users', function (Blueprint $table): void {
                // Cross-database references cannot be PostgreSQL foreign keys.
                $table->uuid('landlord_user_uuid')->nullable()->after('user_id');
            });
        }

        DB::connection(self::CONNECTION)->statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS tenant_users_landlord_uuid_unique ON tenant_users (landlord_user_uuid) WHERE landlord_user_uuid IS NOT NULL'
        );
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('tenant_users')) {
            return;
        }

        DB::connection(self::CONNECTION)->statement('DROP INDEX IF EXISTS tenant_users_landlord_uuid_unique');

        if ($schema->hasColumn('tenant_users', 'landlord_user_uuid')) {
            $schema->table('tenant_users', function (Blueprint $table): void {
                $table->dropColumn('landlord_user_uuid');
            });
        }
    }
};
