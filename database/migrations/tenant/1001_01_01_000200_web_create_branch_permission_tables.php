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

        if ($schema->hasTable('permissions')) {
            return;
        }

        $schema->create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('guard_name', 50)->default('tenant_api');
            $table->string('display_name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_protected')->default(false);
            $table->timestampsTz();

            $table->unique(['name', 'guard_name'], 'permissions_name_guard_unique');
        });

        $schema->create('roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('guard_name', 50)->default('tenant_api');
            $table->string('display_name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->timestampsTz();

            $table->index('branch_id', 'roles_branch_index');
        });

        $schema->create('model_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_permissions_model_index');
            $table->index('branch_id', 'model_permissions_branch_index');
            $table->primary(
                ['branch_id', 'permission_id', 'model_id', 'model_type'],
                'model_permissions_primary'
            );
        });

        $schema->create('model_has_roles', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_roles_model_index');
            $table->index('branch_id', 'model_roles_branch_index');
            $table->primary(['branch_id', 'role_id', 'model_id', 'model_type'], 'model_roles_primary');
        });

        $schema->create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_permissions_primary');
        });

        DB::connection(self::CONNECTION)->statement(
            'CREATE UNIQUE INDEX roles_branch_name_guard_unique '
            .'ON roles (COALESCE(branch_id, 0), name, guard_name)'
        );
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('role_has_permissions');
        $schema->dropIfExists('model_has_roles');
        $schema->dropIfExists('model_has_permissions');
        $schema->dropIfExists('roles');
        $schema->dropIfExists('permissions');
    }
};
