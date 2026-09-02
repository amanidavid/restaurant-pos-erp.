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

        if ($schema->hasTable('setting_definitions')) {
            return;
        }

        $schema->create('setting_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('feature_id')->nullable()->constrained('features')->nullOnDelete();
            $table->string('group', 80);
            $table->string('name', 120);
            $table->string('label', 180);
            $table->text('description')->nullable();
            $table->string('scope', 24);
            $table->string('value_type', 24);
            $table->jsonb('default_payload')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->jsonb('allowed_values')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('requires_restart')->default(false);
            $table->boolean('requires_reconnect')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['group', 'name'], 'setting_definitions_group_name_unique');
            $table->index(['scope', 'status'], 'setting_definitions_scope_status_index');
            $table->index(['feature_id', 'status'], 'setting_definitions_feature_status_index');
            $table->index(['is_sensitive', 'status'], 'setting_definitions_sensitive_status_index');
        });

        $schema->create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('setting_definition_id')->nullable()->constrained('setting_definitions')->nullOnDelete();
            $table->foreignId('updated_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('group', 80);
            $table->string('name', 120);
            $table->jsonb('payload');
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('locked')->default(false);
            $table->timestampTz('effective_from')->nullable();
            $table->timestampsTz();

            $table->unique(['group', 'name'], 'platform_settings_group_name_unique');
            $table->index(['setting_definition_id'], 'platform_settings_definition_index');
            $table->index(['updated_by_platform_user_id', 'updated_at'], 'platform_settings_updated_by_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE setting_definitions ADD CONSTRAINT setting_definitions_scope_check CHECK (scope IN ('platform', 'tenant', 'branch', 'tenant_user'))");
        $connection->statement("ALTER TABLE setting_definitions ADD CONSTRAINT setting_definitions_value_type_check CHECK (value_type IN ('string', 'integer', 'decimal', 'boolean', 'array', 'object', 'time', 'date'))");
        $connection->statement("ALTER TABLE setting_definitions ADD CONSTRAINT setting_definitions_status_check CHECK (status IN ('active', 'inactive', 'retired'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('platform_settings');
        $schema->dropIfExists('setting_definitions');
    }
};
