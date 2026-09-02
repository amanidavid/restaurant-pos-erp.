<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'landlord';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('platform_users')) {
            return;
        }

        $schema->create('platform_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->unique();
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('token_version')->default(1);
            $table->timestampTz('last_accessed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at'], 'platform_users_status_created_index');
        });

        $schema->create('admin_permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('guard_name', 50)->default('admin');
            $table->string('display_name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name'], 'admin_permissions_name_guard_unique');
        });

        $schema->create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('guard_name', 50)->default('admin');
            $table->string('display_name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name'], 'admin_roles_name_guard_unique');
        });

        $schema->create('admin_model_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'admin_model_permissions_model_index');
            $table->primary(
                ['permission_id', 'model_id', 'model_type'],
                'admin_model_permissions_primary'
            );
        });

        $schema->create('admin_model_has_roles', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'admin_model_roles_model_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'admin_model_roles_primary');
        });

        $schema->create('admin_role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('admin_roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'admin_role_permissions_primary');
        });

        $schema->create('support_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('platform_user_id')->constrained('platform_users')->restrictOnDelete();
            $table->foreignId('approved_by_tenant_account_id')
                ->constrained('tenant_accounts')
                ->restrictOnDelete();
            $table->jsonb('permission_scope');
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'expires_at'], 'support_grants_tenant_status_expiry_index');
            $table->index(['platform_user_id', 'status'], 'support_grants_user_status_index');
        });

        $schema->table('tenant_provisioning_runs', function (Blueprint $table): void {
            $table->foreign('requested_by_platform_user_id', 'provisioning_runs_requested_by_foreign')
                ->references('id')
                ->on('platform_users')
                ->restrictOnDelete();
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE platform_users ADD CONSTRAINT platform_users_status_check CHECK (status IN ('active', 'suspended', 'disabled'))");
        $connection->statement("ALTER TABLE support_access_grants ADD CONSTRAINT support_grants_status_check CHECK (status IN ('pending', 'approved', 'active', 'expired', 'revoked'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->table('tenant_provisioning_runs', function (Blueprint $table): void {
            $table->dropForeign('provisioning_runs_requested_by_foreign');
        });
        $schema->dropIfExists('support_access_grants');
        $schema->dropIfExists('admin_role_has_permissions');
        $schema->dropIfExists('admin_model_has_roles');
        $schema->dropIfExists('admin_model_has_permissions');
        $schema->dropIfExists('admin_roles');
        $schema->dropIfExists('admin_permissions');
        $schema->dropIfExists('platform_users');
    }
};
