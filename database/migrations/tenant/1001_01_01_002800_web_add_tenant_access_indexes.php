<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('users')) {
            DB::connection(self::CONNECTION)->statement(
                'CREATE INDEX IF NOT EXISTS users_display_name_pattern_index '
                .'ON users (display_name text_pattern_ops)'
            );
        }

        if ($schema->hasTable('tenant_user_branches')) {
            DB::connection(self::CONNECTION)->statement(
                'CREATE INDEX IF NOT EXISTS tenant_user_branches_branch_status_user_index '
                .'ON tenant_user_branches (branch_id, status, tenant_user_id)'
            );
        }

        if ($schema->hasTable('model_has_roles')) {
            DB::connection(self::CONNECTION)->statement(
                'CREATE INDEX IF NOT EXISTS model_has_roles_access_lookup_index '
                .'ON model_has_roles (model_type, model_id, branch_id, role_id)'
            );
        }

        if ($schema->hasTable('model_has_permissions')) {
            DB::connection(self::CONNECTION)->statement(
                'CREATE INDEX IF NOT EXISTS model_has_permissions_access_lookup_index '
                .'ON model_has_permissions (model_type, model_id, branch_id, permission_id)'
            );
        }
    }

    public function down(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('DROP INDEX IF EXISTS users_display_name_pattern_index');
        $connection->statement('DROP INDEX IF EXISTS tenant_user_branches_branch_status_user_index');
        $connection->statement('DROP INDEX IF EXISTS model_has_roles_access_lookup_index');
        $connection->statement('DROP INDEX IF EXISTS model_has_permissions_access_lookup_index');
    }
};
