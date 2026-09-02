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

        if ($schema->hasTable('settings')) {
            return;
        }

        $schema->create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 80);
            $table->string('name', 120);
            $table->jsonb('payload');
            $table->boolean('locked')->default(false);
            $table->timestampsTz();

            $table->unique(['group', 'name'], 'settings_group_name_unique');
            $table->index(['group', 'updated_at'], 'settings_group_updated_index');
        });

        $schema->create('branch_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('updated_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->uuid('setting_definition_uuid')->nullable();
            $table->string('group', 80);
            $table->string('name', 120);
            $table->jsonb('payload');
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('locked')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'group', 'name'], 'branch_settings_branch_group_name_unique');
            $table->index(['branch_id', 'status'], 'branch_settings_branch_status_index');
            $table->index(['group', 'name', 'status'], 'branch_settings_group_name_status_index');
            $table->index(['setting_definition_uuid'], 'branch_settings_definition_uuid_index');
            $table->index(['updated_by_tenant_user_id', 'updated_at'], 'branch_settings_updated_by_index');
        });

        $schema->create('tenant_user_preferences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('group', 80);
            $table->string('name', 120);
            $table->jsonb('payload');
            $table->timestampsTz();

            $table->unique(['tenant_user_id', 'branch_id', 'group', 'name'], 'tenant_user_preferences_scope_unique');
            $table->index(['tenant_user_id', 'group'], 'tenant_user_preferences_user_group_index');
            $table->index(['branch_id', 'group'], 'tenant_user_preferences_branch_group_index');
        });

        $schema->create('configuration_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('snapshot_area', 40);
            $table->string('reason', 160)->nullable();
            $table->jsonb('snapshot_payload');
            $table->timestampTz('captured_at');
            $table->timestampsTz();

            $table->index(['snapshot_area', 'captured_at'], 'configuration_snapshots_area_captured_index');
            $table->index(['created_by_tenant_user_id', 'captured_at'], 'configuration_snapshots_created_by_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE branch_settings ADD CONSTRAINT branch_settings_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE configuration_snapshots ADD CONSTRAINT configuration_snapshots_area_check CHECK (snapshot_area IN ('tenant', 'branch', 'pos', 'receipt', 'inventory', 'payroll', 'tax', 'integration', 'reporting', 'notification'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('configuration_snapshots');
        $schema->dropIfExists('tenant_user_preferences');
        $schema->dropIfExists('branch_settings');
        $schema->dropIfExists('settings');
    }
};
