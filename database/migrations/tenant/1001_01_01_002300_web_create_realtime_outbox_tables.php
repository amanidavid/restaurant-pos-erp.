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

        if ($schema->hasTable('realtime_event_outbox')) {
            return;
        }

        $schema->create('realtime_channel_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('permissions')->nullOnDelete();
            $table->string('channel_key', 80);
            $table->string('channel_pattern', 180);
            $table->string('audience_type', 32);
            $table->boolean('requires_authenticated_user')->default(true);
            $table->boolean('requires_branch_access')->default(true);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'branch_id', 'channel_key'], 'realtime_channel_policies_scope_key_unique');
            $table->index(['branch_id', 'status'], 'realtime_channel_policies_branch_status_index');
            $table->index(['permission_id', 'status'], 'realtime_channel_policies_permission_status_index');
            $table->index(['audience_type', 'status'], 'realtime_channel_policies_audience_status_index');
        });

        $schema->create('realtime_event_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('created_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('kitchen_ticket_id')->nullable()->constrained('kitchen_tickets')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->cascadeOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->cascadeOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignId('notification_message_id')->nullable()->constrained('notification_messages')->cascadeOnDelete();
            $table->foreignId('print_job_id')->nullable()->constrained('print_jobs')->cascadeOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('channel_name', 180);
            $table->string('visibility_scope', 32);
            $table->jsonb('payload');
            $table->string('payload_hash', 128)->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->unsignedSmallInteger('priority')->default(5);
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestampTz('available_at');
            $table->timestampTz('locked_at')->nullable();
            $table->string('locked_by', 120)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['idempotency_key'], 'realtime_event_outbox_idempotency_unique');
            $table->index(['status', 'available_at', 'priority'], 'realtime_event_outbox_status_available_priority_index');
            $table->index(['branch_id', 'status', 'available_at'], 'realtime_event_outbox_branch_status_available_index');
            $table->index(['event_type', 'status', 'available_at'], 'realtime_event_outbox_type_status_available_index');
            $table->index(['channel_name', 'status'], 'realtime_event_outbox_channel_status_index');
            $table->index(['locked_at'], 'realtime_event_outbox_locked_at_index');
            $table->index(['published_at'], 'realtime_event_outbox_published_at_index');
            $table->index(['expires_at'], 'realtime_event_outbox_expires_at_index');
            $table->index(['order_id', 'created_at'], 'realtime_event_outbox_order_created_index');
            $table->index(['kitchen_ticket_id', 'created_at'], 'realtime_event_outbox_kitchen_ticket_created_index');
            $table->index(['payment_id', 'created_at'], 'realtime_event_outbox_payment_created_index');
            $table->index(['refund_id', 'created_at'], 'realtime_event_outbox_refund_created_index');
        });

        $schema->create('realtime_event_publish_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('realtime_event_outbox_id')->constrained('realtime_event_outbox')->cascadeOnDelete();
            $table->string('worker_name', 120)->nullable();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->timestampsTz();

            $table->unique(['realtime_event_outbox_id', 'attempt_number'], 'realtime_publish_attempts_event_attempt_unique');
            $table->index(['status', 'started_at'], 'realtime_publish_attempts_status_started_index');
            $table->index(['worker_name', 'started_at'], 'realtime_publish_attempts_worker_started_index');
        });

        $schema->create('realtime_connection_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('branch_device_id')->nullable()->constrained('branch_devices')->nullOnDelete();
            $table->foreignId('tenant_user_device_session_id')->nullable()->constrained('tenant_user_device_sessions')->nullOnDelete();
            $table->string('connection_id', 180)->nullable();
            $table->string('channel_name', 180)->nullable();
            $table->string('event_type', 40);
            $table->string('status', 24)->default('info');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_user_id', 'occurred_at'], 'realtime_connection_events_user_occurred_index');
            $table->index(['branch_id', 'event_type', 'occurred_at'], 'realtime_connection_events_branch_type_occurred_index');
            $table->index(['branch_device_id', 'occurred_at'], 'realtime_connection_events_device_occurred_index');
            $table->index(['connection_id', 'occurred_at'], 'realtime_connection_events_connection_occurred_index');
            $table->index(['event_type', 'status', 'occurred_at'], 'realtime_connection_events_type_status_occurred_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE realtime_channel_policies ADD CONSTRAINT realtime_channel_policies_audience_check CHECK (audience_type IN ('branch_staff', 'cashier', 'kitchen', 'manager', 'device', 'tenant_admin'))");
        $connection->statement("ALTER TABLE realtime_channel_policies ADD CONSTRAINT realtime_channel_policies_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_type_check CHECK (event_type IN ('order.created', 'order.updated', 'order.cancelled', 'order.sent_to_kitchen', 'kitchen_ticket.created', 'kitchen_ticket.updated', 'kitchen_ticket.ready', 'reservation.created', 'reservation.updated', 'payment.completed', 'payment.voided', 'refund.requested', 'refund.approved', 'refund.completed', 'stock.updated', 'approval.requested', 'approval.decided', 'notification.created', 'print_job.created', 'print_job.updated', 'device.status_changed'))");
        $connection->statement("ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_visibility_check CHECK (visibility_scope IN ('tenant', 'branch', 'user', 'device', 'kitchen_section'))");
        $connection->statement('ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_priority_check CHECK (priority BETWEEN 1 AND 9)');
        $connection->statement("ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_status_check CHECK (status IN ('pending', 'publishing', 'published', 'failed', 'cancelled', 'expired'))");
        $connection->statement('ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_attempts_check CHECK (attempts >= 0 AND max_attempts BETWEEN 1 AND 20 AND attempts <= max_attempts)');
        $connection->statement('ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_dates_check CHECK ((published_at IS NULL OR published_at >= available_at) AND (expires_at IS NULL OR expires_at >= available_at))');
        $connection->statement('ALTER TABLE realtime_event_outbox ADD CONSTRAINT realtime_event_outbox_source_check CHECK (num_nonnulls(order_id, kitchen_ticket_id, reservation_id, payment_id, refund_id, stock_movement_id, approval_request_id, notification_message_id, print_job_id, branch_device_id) >= 1)');
        $connection->statement("CREATE INDEX realtime_event_outbox_pending_fast_index ON realtime_event_outbox (available_at, priority, id) WHERE status = 'pending'");
        $connection->statement("CREATE INDEX realtime_event_outbox_failed_retry_index ON realtime_event_outbox (available_at, attempts, id) WHERE status = 'failed'");
        $connection->statement("ALTER TABLE realtime_event_publish_attempts ADD CONSTRAINT realtime_publish_attempts_status_check CHECK (status IN ('started', 'published', 'failed'))");
        $connection->statement('ALTER TABLE realtime_event_publish_attempts ADD CONSTRAINT realtime_publish_attempts_dates_check CHECK (completed_at IS NULL OR completed_at >= started_at)');
        $connection->statement('ALTER TABLE realtime_event_publish_attempts ADD CONSTRAINT realtime_publish_attempts_values_check CHECK (attempt_number > 0 AND (duration_ms IS NULL OR duration_ms >= 0))');
        $connection->statement("ALTER TABLE realtime_connection_events ADD CONSTRAINT realtime_connection_events_type_check CHECK (event_type IN ('connected', 'disconnected', 'subscribed', 'unsubscribed', 'auth_failed', 'permission_denied'))");
        $connection->statement("ALTER TABLE realtime_connection_events ADD CONSTRAINT realtime_connection_events_status_check CHECK (status IN ('info', 'warning', 'critical'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS realtime_event_outbox_failed_retry_index');
        $connection->statement('DROP INDEX IF EXISTS realtime_event_outbox_pending_fast_index');

        $schema->dropIfExists('realtime_connection_events');
        $schema->dropIfExists('realtime_event_publish_attempts');
        $schema->dropIfExists('realtime_event_outbox');
        $schema->dropIfExists('realtime_channel_policies');
    }
};
