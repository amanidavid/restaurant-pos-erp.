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

        if ($schema->hasTable('users')) {
            return;
        }

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('display_name', 180);
            $table->string('email', 254)->nullable()->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('password_changed_at')->nullable();
            $table->unsignedBigInteger('token_version')->default(1);
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at'], 'users_status_created_index');
        });

        $schema->create('user_identities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_subject', 255);
            $table->string('email_snapshot', 254)->nullable();
            $table->string('display_name_snapshot', 180)->nullable();
            $table->string('avatar_url_snapshot', 2048)->nullable();
            $table->boolean('provider_verified')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('linked_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_subject'], 'user_identities_provider_subject_unique');
            $table->unique(['user_id', 'provider'], 'user_identities_user_provider_unique');
        });

        $schema->create('tenant_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('status', 24)->default('invited');
            $table->boolean('is_owner')->default(false);
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedBigInteger('token_version')->default(1);
            $table->timestampsTz();

            $table->unique(['user_id', 'tenant_id'], 'tenant_accounts_user_tenant_unique');
            $table->index(['tenant_id', 'status'], 'tenant_accounts_tenant_status_index');
            $table->index(['user_id', 'status'], 'tenant_accounts_user_status_index');
        });

        $schema->create('tenant_account_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('invited_by_tenant_account_id')
                ->nullable()
                ->constrained('tenant_accounts')
                ->nullOnDelete();
            $table->string('identifier_type', 10);
            $table->string('identifier_normalized', 254);
            $table->string('token_hash', 64)->unique();
            $table->string('status', 24)->default('pending');
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(
                ['tenant_id', 'identifier_type', 'identifier_normalized'],
                'tenant_invitations_identifier_index'
            );
            $table->index(['status', 'expires_at'], 'tenant_invitations_status_expiry_index');
        });

        $schema->create('user_device_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_account_id')->constrained('tenant_accounts')->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('device_name', 120)->nullable();
            $table->string('device_type', 30)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('refresh_token_hash', 64)->unique();
            $table->uuid('current_jti')->nullable();
            $table->uuid('last_jti')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('started_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revocation_reason', 255)->nullable();
            $table->timestampsTz();

            $table->index(['tenant_account_id', 'is_active'], 'user_sessions_account_active_index');
            $table->index(['is_active', 'expires_at'], 'user_sessions_active_expiry_index');
        });

        $schema->create('password_reset_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier_type', 10);
            $table->string('identifier_normalized', 254);
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(
                ['identifier_type', 'identifier_normalized', 'created_at'],
                'password_resets_identifier_created_index'
            );
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('ALTER TABLE users ADD CONSTRAINT users_login_identifier_check CHECK (email IS NOT NULL OR phone IS NOT NULL)');
        $connection->statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended', 'disabled'))");
        $connection->statement("ALTER TABLE tenant_accounts ADD CONSTRAINT tenant_accounts_status_check CHECK (status IN ('invited', 'active', 'suspended', 'ended'))");
        $connection->statement("ALTER TABLE tenant_account_invitations ADD CONSTRAINT tenant_invitations_identifier_type_check CHECK (identifier_type IN ('email', 'phone'))");
        $connection->statement("ALTER TABLE password_reset_tokens ADD CONSTRAINT password_resets_identifier_type_check CHECK (identifier_type IN ('email', 'phone'))");
        $connection->statement('CREATE UNIQUE INDEX user_device_sessions_one_active_unique ON user_device_sessions (tenant_account_id) WHERE is_active = true');
        $connection->statement("CREATE UNIQUE INDEX tenant_invitations_one_pending_unique ON tenant_account_invitations (tenant_id, identifier_type, identifier_normalized) WHERE status = 'pending'");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('password_reset_tokens');
        $schema->dropIfExists('user_device_sessions');
        $schema->dropIfExists('tenant_account_invitations');
        $schema->dropIfExists('tenant_accounts');
        $schema->dropIfExists('user_identities');
        $schema->dropIfExists('users');
    }
};
