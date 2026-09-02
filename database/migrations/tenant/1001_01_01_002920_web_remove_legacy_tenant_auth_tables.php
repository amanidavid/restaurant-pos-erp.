<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('tenant_users')
            && $schema->hasColumn('tenant_users', 'landlord_user_uuid')
            && DB::connection('tenant')->table('tenant_users')->whereNull('landlord_user_uuid')->exists()) {
            throw new RuntimeException('Cannot remove legacy tenant authentication: unmapped tenant users remain. Run tenants:backfill-landlord-identity first.');
        }

        // OTP records are now stored in landlord.password_reset_otps.
        $schema->dropIfExists('password_reset_otps');

        if ($schema->hasTable('tenant_users') && $schema->hasColumn('tenant_users', 'user_id')) {
            DB::connection('tenant')->statement('ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_user_id_foreign');
            DB::connection('tenant')->statement('ALTER TABLE tenant_users DROP COLUMN user_id');
        }

        $schema->dropIfExists('users');
    }

    public function down(): void
    {
        throw new RuntimeException('Legacy tenant authentication tables cannot be restored automatically. Restore them from backup.');
    }
};
