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

        if ($schema->hasTable('tenants')) {
            return;
        }

        $schema->create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('legal_name', 180);
            $table->string('trading_name', 180);
            $table->string('tin', 40)->nullable();
            $table->string('vrn', 40)->nullable();
            $table->char('country_code', 2);
            $table->string('timezone', 64);
            $table->string('locale', 16)->default('en');
            $table->char('currency_code', 3);
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 24)->default('provisioning');
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('offboarded_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at'], 'tenants_status_created_index');
            $table->unique('tin', 'tenants_tin_unique');
            $table->unique('vrn', 'tenants_vrn_unique');
        });

        $schema->create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('domain', 253)->unique();
            $table->string('type', 24);
            $table->boolean('is_primary')->default(false);
            $table->string('verification_token_hash', 64)->nullable();
            $table->string('verification_status', 24)->default('pending');
            $table->timestampTz('verified_at')->nullable();
            $table->string('ssl_status', 24)->default('pending');
            $table->timestampsTz();

            $table->index(['tenant_id', 'verification_status'], 'tenant_domains_tenant_status_index');
        });

        $schema->create('database_clusters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('host', 253);
            $table->unsignedSmallInteger('port')->default(5432);
            $table->string('region', 80)->nullable();
            $table->string('ssl_mode', 20)->default('prefer');
            $table->string('secret_namespace', 255)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampTz('last_health_check_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'region'], 'database_clusters_status_region_index');
        });

        $schema->create('tenant_databases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('database_cluster_id')->constrained('database_clusters')->restrictOnDelete();
            $table->string('database_name', 63);
            $table->string('database_username', 63);
            $table->string('password_secret_reference', 500)->nullable();
            $table->string('status', 24)->default('provisioning');
            $table->boolean('is_primary')->default(false);
            $table->string('schema_version', 100)->nullable();
            $table->timestampTz('provisioned_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['database_cluster_id', 'database_name'],
                'tenant_databases_cluster_database_unique'
            );
            $table->index(['tenant_id', 'status'], 'tenant_databases_tenant_status_index');
        });

        $schema->create('tenant_provisioning_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('database_cluster_id')->nullable()->constrained('database_clusters')->restrictOnDelete();
            $table->unsignedBigInteger('requested_by_platform_user_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('current_step', 100)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('registration_payload')->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'created_at'], 'provisioning_runs_tenant_created_index');
            $table->index(['status', 'created_at'], 'provisioning_runs_status_created_index');
        });

        $schema->create('tenant_provisioning_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_provisioning_run_id')
                ->constrained('tenant_provisioning_runs')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('step_code', 100);
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['tenant_provisioning_run_id', 'sequence'],
                'provisioning_steps_run_sequence_unique'
            );
            $table->unique(
                ['tenant_provisioning_run_id', 'step_code'],
                'provisioning_steps_run_code_unique'
            );
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('provisioning', 'trial', 'active', 'suspended', 'expired', 'offboarded'))");
        $connection->statement("ALTER TABLE tenant_domains ADD CONSTRAINT tenant_domains_type_check CHECK (type IN ('platform_subdomain', 'custom'))");
        $connection->statement("ALTER TABLE database_clusters ADD CONSTRAINT database_clusters_ssl_mode_check CHECK (ssl_mode IN ('disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'))");
        $connection->statement("ALTER TABLE tenant_databases ADD CONSTRAINT tenant_databases_status_check CHECK (status IN ('provisioning', 'active', 'draining', 'retired', 'failed'))");
        $connection->statement('CREATE UNIQUE INDEX tenant_domains_one_primary_unique ON tenant_domains (tenant_id) WHERE is_primary = true');
        $connection->statement("CREATE UNIQUE INDEX tenant_databases_one_active_primary_unique ON tenant_databases (tenant_id) WHERE is_primary = true AND status IN ('provisioning', 'active', 'draining')");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('tenant_provisioning_steps');
        $schema->dropIfExists('tenant_provisioning_runs');
        $schema->dropIfExists('tenant_databases');
        $schema->dropIfExists('database_clusters');
        $schema->dropIfExists('tenant_domains');
        $schema->dropIfExists('tenants');
    }
};
