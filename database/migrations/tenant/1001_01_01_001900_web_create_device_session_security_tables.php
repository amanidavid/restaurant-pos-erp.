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

        if ($schema->hasTable('branch_devices')) {
            return;
        }

        $schema->create('branch_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('registered_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('name', 140);
            $table->string('device_type', 32);
            $table->string('platform', 32)->nullable();
            $table->string('device_fingerprint_hash', 128)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->boolean('allows_printing')->default(false);
            $table->boolean('requires_pairing')->default(true);
            $table->string('status', 24)->default('pending');
            $table->timestampTz('registered_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'branch_devices_branch_name_unique');
            $table->index(['branch_id', 'device_type', 'status'], 'branch_devices_branch_type_status_index');
            $table->index(['branch_id', 'is_trusted', 'status'], 'branch_devices_branch_trusted_status_index');
            $table->index(['device_fingerprint_hash'], 'branch_devices_fingerprint_index');
        });

        $schema->create('branch_device_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_device_id')->constrained('branch_devices')->cascadeOnDelete();
            $table->string('capability', 40);
            $table->boolean('is_enabled')->default(true);
            $table->timestampsTz();

            $table->unique(['branch_device_id', 'capability'], 'branch_device_capabilities_device_capability_unique');
            $table->index(['capability', 'is_enabled'], 'branch_device_capabilities_capability_enabled_index');
        });

        $schema->create('device_pairing_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
            $table->foreignId('requested_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('approved_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('pairing_token_hash', 128);
            $table->string('device_name_snapshot', 140)->nullable();
            $table->string('device_fingerprint_hash', 128)->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('requested_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['pairing_token_hash'], 'device_pairing_requests_token_hash_unique');
            $table->index(['branch_id', 'status', 'expires_at'], 'device_pairing_requests_branch_status_expiry_index');
            $table->index(['branch_device_id', 'status'], 'device_pairing_requests_device_status_index');
        });

        $schema->create('tenant_user_device_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
            $table->string('session_jti_hash', 128)->unique();
            $table->string('refresh_token_hash', 128)->nullable()->unique();
            $table->string('login_method', 32);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('force_logout_requested')->default(false);
            $table->timestampTz('logged_in_at');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('logged_out_at')->nullable();
            $table->string('logout_reason', 40)->nullable();
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'is_active'], 'tenant_user_sessions_user_active_index');
            $table->index(['branch_id', 'is_active', 'last_seen_at'], 'tenant_user_sessions_branch_active_seen_index');
            $table->index(['branch_device_id', 'is_active', 'last_seen_at'], 'tenant_user_sessions_device_active_seen_index');
            $table->index(['is_active', 'expires_at'], 'tenant_user_sessions_active_expiry_index');
        });

        $schema->create('device_print_agent_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_device_id')->constrained('branch_devices')->cascadeOnDelete();
            $table->foreignId('print_device_id')->nullable()->constrained('print_devices')->nullOnDelete();
            $table->string('connection_id', 160)->nullable();
            $table->string('status', 24)->default('online');
            $table->timestampTz('connected_at');
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->timestampTz('disconnected_at')->nullable();
            $table->jsonb('capabilities')->nullable();
            $table->timestampsTz();

            $table->index(['branch_device_id', 'status'], 'print_agent_sessions_device_status_index');
            $table->index(['print_device_id', 'status'], 'print_agent_sessions_print_device_status_index');
            $table->index(['status', 'last_heartbeat_at'], 'print_agent_sessions_status_heartbeat_index');
        });

        $schema->create('device_login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
            $table->string('login_identifier', 254);
            $table->string('login_method', 32);
            $table->string('status', 24);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampTz('attempted_at');
            $table->timestampsTz();

            $table->index(['login_identifier', 'attempted_at'], 'device_login_attempts_identifier_attempted_index');
            $table->index(['tenant_user_id', 'status', 'attempted_at'], 'device_login_attempts_user_status_attempted_index');
            $table->index(['branch_device_id', 'status', 'attempted_at'], 'device_login_attempts_device_status_attempted_index');
            $table->index(['ip_address', 'attempted_at'], 'device_login_attempts_ip_attempted_index');
        });

        $schema->create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
            $table->foreignId('tenant_user_device_session_id')->nullable()->constrained('tenant_user_device_sessions')->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('severity', 16)->default('info');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'event_type', 'occurred_at'], 'security_events_user_type_occurred_index');
            $table->index(['branch_id', 'severity', 'occurred_at'], 'security_events_branch_severity_occurred_index');
            $table->index(['branch_device_id', 'event_type', 'occurred_at'], 'security_events_device_type_occurred_index');
            $table->index(['event_type', 'occurred_at'], 'security_events_type_occurred_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE branch_devices ADD CONSTRAINT branch_devices_type_check CHECK (device_type IN ('pos_terminal', 'kitchen_display', 'print_agent', 'customer_display', 'mobile_app', 'tablet', 'back_office', 'scanner'))");
        $connection->statement("ALTER TABLE branch_devices ADD CONSTRAINT branch_devices_platform_check CHECK (platform IS NULL OR platform IN ('web', 'android', 'ios', 'windows', 'macos', 'linux'))");
        $connection->statement("ALTER TABLE branch_devices ADD CONSTRAINT branch_devices_status_check CHECK (status IN ('pending', 'active', 'blocked', 'lost', 'retired'))");
        $connection->statement("CREATE UNIQUE INDEX branch_devices_fingerprint_unique ON branch_devices (device_fingerprint_hash) WHERE device_fingerprint_hash IS NOT NULL");
        $connection->statement("ALTER TABLE branch_device_capabilities ADD CONSTRAINT branch_device_capabilities_capability_check CHECK (capability IN ('point_of_sale', 'kitchen_display', 'print_agent', 'cash_drawer', 'card_terminal', 'barcode_scanner', 'stock_count', 'admin_panel'))");
        $connection->statement("ALTER TABLE device_pairing_requests ADD CONSTRAINT device_pairing_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'used', 'expired', 'cancelled'))");
        $connection->statement('ALTER TABLE device_pairing_requests ADD CONSTRAINT device_pairing_requests_dates_check CHECK (expires_at >= requested_at AND (approved_at IS NULL OR approved_at >= requested_at) AND (used_at IS NULL OR used_at >= requested_at))');
        $connection->statement("ALTER TABLE tenant_user_device_sessions ADD CONSTRAINT tenant_user_sessions_login_method_check CHECK (login_method IN ('password', 'phone_otp', 'email_otp', 'google', 'admin_impersonation'))");
        $connection->statement("ALTER TABLE tenant_user_device_sessions ADD CONSTRAINT tenant_user_sessions_logout_reason_check CHECK (logout_reason IS NULL OR logout_reason IN ('user_logout', 'forced_logout', 'expired', 'replaced_by_new_login', 'password_changed', 'device_blocked', 'admin_revoked'))");
        $connection->statement('ALTER TABLE tenant_user_device_sessions ADD CONSTRAINT tenant_user_sessions_dates_check CHECK (expires_at >= logged_in_at AND (logged_out_at IS NULL OR logged_out_at >= logged_in_at))');
        $connection->statement("CREATE UNIQUE INDEX tenant_user_sessions_one_active_unique ON tenant_user_device_sessions (tenant_user_id) WHERE is_active = true");
        $connection->statement("ALTER TABLE device_print_agent_sessions ADD CONSTRAINT print_agent_sessions_status_check CHECK (status IN ('online', 'offline', 'error', 'retired'))");
        $connection->statement('ALTER TABLE device_print_agent_sessions ADD CONSTRAINT print_agent_sessions_dates_check CHECK (disconnected_at IS NULL OR disconnected_at >= connected_at)');
        $connection->statement("CREATE UNIQUE INDEX print_agent_sessions_one_online_device_unique ON device_print_agent_sessions (branch_device_id) WHERE status = 'online'");
        $connection->statement("ALTER TABLE device_login_attempts ADD CONSTRAINT device_login_attempts_method_check CHECK (login_method IN ('password', 'phone_otp', 'email_otp', 'google'))");
        $connection->statement("ALTER TABLE device_login_attempts ADD CONSTRAINT device_login_attempts_status_check CHECK (status IN ('success', 'failed', 'blocked'))");
        $connection->statement("ALTER TABLE security_events ADD CONSTRAINT security_events_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");
        $connection->statement("ALTER TABLE security_events ADD CONSTRAINT security_events_type_check CHECK (event_type IN ('login_success', 'login_failed', 'login_blocked', 'session_started', 'session_replaced', 'session_expired', 'forced_logout', 'device_registered', 'device_approved', 'device_blocked', 'device_lost', 'pairing_requested', 'pairing_used', 'print_agent_connected', 'print_agent_disconnected', 'permission_denied', 'password_changed', 'suspicious_activity'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS print_agent_sessions_one_online_device_unique');
        $connection->statement('DROP INDEX IF EXISTS tenant_user_sessions_one_active_unique');
        $connection->statement('DROP INDEX IF EXISTS branch_devices_fingerprint_unique');

        $schema->dropIfExists('security_events');
        $schema->dropIfExists('device_login_attempts');
        $schema->dropIfExists('device_print_agent_sessions');
        $schema->dropIfExists('tenant_user_device_sessions');
        $schema->dropIfExists('device_pairing_requests');
        $schema->dropIfExists('branch_device_capabilities');
        $schema->dropIfExists('branch_devices');
    }
};
