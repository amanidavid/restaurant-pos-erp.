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

        if ($schema->hasTable('tenant_realtime_monitors')) {
            return;
        }

        $schema->create('tenant_realtime_monitors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_database_id')->nullable()->constrained('tenant_databases')->nullOnDelete();
            $table->boolean('is_realtime_enabled')->default(true);
            $table->string('status', 24)->default('healthy');
            $table->unsignedInteger('pending_events_snapshot')->default(0);
            $table->unsignedInteger('failed_events_snapshot')->default(0);
            $table->timestampTz('last_event_created_at')->nullable();
            $table->timestampTz('last_event_published_at')->nullable();
            $table->timestampTz('last_worker_heartbeat_at')->nullable();
            $table->timestampTz('last_health_check_at')->nullable();
            $table->text('last_failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id'], 'tenant_realtime_monitors_tenant_unique');
            $table->index(['status', 'last_worker_heartbeat_at'], 'tenant_realtime_monitors_status_heartbeat_index');
            $table->index(['is_realtime_enabled', 'status'], 'tenant_realtime_monitors_enabled_status_index');
        });

        $schema->create('tenant_realtime_worker_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_realtime_monitor_id')->nullable()->constrained('tenant_realtime_monitors')->cascadeOnDelete();
            $table->string('worker_name', 120);
            $table->string('event_type', 40);
            $table->string('status', 24)->default('info');
            $table->unsignedInteger('events_processed')->default(0);
            $table->unsignedInteger('events_failed')->default(0);
            $table->text('message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'occurred_at'], 'tenant_realtime_worker_events_tenant_occurred_index');
            $table->index(['worker_name', 'occurred_at'], 'tenant_realtime_worker_events_worker_occurred_index');
            $table->index(['event_type', 'status', 'occurred_at'], 'tenant_realtime_worker_events_type_status_occurred_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE tenant_realtime_monitors ADD CONSTRAINT tenant_realtime_monitors_status_check CHECK (status IN ('healthy', 'degraded', 'stalled', 'disabled'))");
        $connection->statement('ALTER TABLE tenant_realtime_monitors ADD CONSTRAINT tenant_realtime_monitors_counts_check CHECK (pending_events_snapshot >= 0 AND failed_events_snapshot >= 0)');
        $connection->statement("ALTER TABLE tenant_realtime_worker_events ADD CONSTRAINT tenant_realtime_worker_events_type_check CHECK (event_type IN ('heartbeat', 'batch_started', 'batch_completed', 'batch_failed', 'health_check', 'stalled_detected', 'recovered'))");
        $connection->statement("ALTER TABLE tenant_realtime_worker_events ADD CONSTRAINT tenant_realtime_worker_events_status_check CHECK (status IN ('info', 'warning', 'critical'))");
        $connection->statement('ALTER TABLE tenant_realtime_worker_events ADD CONSTRAINT tenant_realtime_worker_events_counts_check CHECK (events_processed >= 0 AND events_failed >= 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('tenant_realtime_worker_events');
        $schema->dropIfExists('tenant_realtime_monitors');
    }
};
