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

        if ($schema->hasTable('tenant_users')) {
            return;
        }

        $schema->create('tenant_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->string('display_name', 180);
            $table->string('status', 24)->default('active');
            $table->timestampTz('last_accessed_at')->nullable();
            $table->timestampTz('last_synchronized_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'display_name'], 'tenant_users_status_name_index');
            $table->index(['user_id', 'status'], 'tenant_users_user_status_index');
        });

        $schema->create('tenant_user_branches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->boolean('is_primary')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->foreignId('assigned_by_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('revoked_by_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_user_id', 'branch_id'], 'tenant_user_branches_user_branch_unique');
            $table->index(['branch_id', 'status'], 'tenant_user_branches_branch_status_index');
            $table->index(['tenant_user_id', 'status'], 'tenant_user_branches_user_status_index');
        });

        $schema->create('user_department_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestampsTz();

            $table->index(['department_id', 'branch_id'], 'user_department_access_department_branch_index');
        });

        $schema->table('business_dates', function (Blueprint $table): void {
            $table->foreign('opened_by_id', 'business_dates_opened_by_foreign')
                ->references('id')
                ->on('tenant_users')
                ->restrictOnDelete();
            $table->foreign('closed_by_id', 'business_dates_closed_by_foreign')
                ->references('id')
                ->on('tenant_users')
                ->restrictOnDelete();
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_status_check CHECK (status IN ('active', 'suspended', 'ended'))");
        $connection->statement("ALTER TABLE tenant_user_branches ADD CONSTRAINT tenant_user_branches_status_check CHECK (status IN ('active', 'suspended', 'revoked'))");
        $connection->statement('ALTER TABLE tenant_user_branches ADD CONSTRAINT tenant_user_branches_dates_check CHECK (valid_to IS NULL OR valid_from IS NULL OR valid_to >= valid_from)');
        $connection->statement("CREATE UNIQUE INDEX tenant_user_branches_one_primary_unique ON tenant_user_branches (tenant_user_id) WHERE is_primary = true AND status = 'active'");
        $connection->statement('CREATE UNIQUE INDEX user_department_access_scope_unique ON user_department_access (tenant_user_id, department_id, COALESCE(branch_id, 0))');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->table('business_dates', function (Blueprint $table): void {
            $table->dropForeign('business_dates_opened_by_foreign');
            $table->dropForeign('business_dates_closed_by_foreign');
        });
        $schema->dropIfExists('user_department_access');
        $schema->dropIfExists('tenant_user_branches');
        $schema->dropIfExists('tenant_users');
    }
};
