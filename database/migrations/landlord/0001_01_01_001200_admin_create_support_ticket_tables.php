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

        if ($schema->hasTable('support_tickets')) {
            return;
        }

        $schema->create('support_ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('support_area', 40);
            $table->foreignId('default_assignee_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->unsignedSmallInteger('default_priority')->default(5);
            $table->boolean('requires_support_access_grant')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['name'], 'support_ticket_categories_name_unique');
            $table->index(['support_area', 'status'], 'support_ticket_categories_area_status_index');
            $table->index(['default_assignee_platform_user_id', 'status'], 'support_ticket_categories_assignee_status_index');
        });

        $schema->create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('tenant_account_id')->nullable()->constrained('tenant_accounts')->nullOnDelete();
            $table->foreignId('support_ticket_category_id')->nullable()->constrained('support_ticket_categories')->nullOnDelete();
            $table->foreignId('assigned_to_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('closed_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->foreignId('support_access_grant_id')->nullable()->constrained('support_access_grants')->nullOnDelete();
            $table->string('ticket_number', 40)->unique();
            $table->string('subject', 180);
            $table->text('description');
            $table->string('support_area', 40);
            $table->unsignedSmallInteger('priority')->default(5);
            $table->string('status', 24)->default('open');
            $table->string('source', 24)->default('tenant_portal');
            $table->uuid('tenant_branch_uuid')->nullable();
            $table->uuid('tenant_user_uuid')->nullable();
            $table->uuid('tenant_reference_uuid')->nullable();
            $table->string('tenant_reference_type', 60)->nullable();
            $table->string('tenant_reference_label', 180)->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('first_response_due_at')->nullable();
            $table->timestampTz('resolution_due_at')->nullable();
            $table->timestampTz('first_responded_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status', 'opened_at'], 'support_tickets_tenant_status_opened_index');
            $table->index(['assigned_to_platform_user_id', 'status', 'priority'], 'support_tickets_assignee_status_priority_index');
            $table->index(['support_ticket_category_id', 'status'], 'support_tickets_category_status_index');
            $table->index(['support_area', 'status', 'priority'], 'support_tickets_area_status_priority_index');
            $table->index(['tenant_reference_type', 'tenant_reference_uuid'], 'support_tickets_tenant_reference_index');
            $table->index(['support_access_grant_id'], 'support_tickets_access_grant_index');
        });

        $schema->create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('tenant_account_id')->nullable()->constrained('tenant_accounts')->nullOnDelete();
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('sender_type', 24);
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestampTz('sent_at');
            $table->timestampsTz();

            $table->index(['support_ticket_id', 'sent_at'], 'support_ticket_messages_ticket_sent_index');
            $table->index(['tenant_account_id', 'sent_at'], 'support_ticket_messages_tenant_account_sent_index');
            $table->index(['platform_user_id', 'sent_at'], 'support_ticket_messages_platform_user_sent_index');
        });

        $schema->create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('support_ticket_message_id')->nullable()->constrained('support_ticket_messages')->cascadeOnDelete();
            $table->foreignId('uploaded_by_tenant_account_id')->nullable()->constrained('tenant_accounts')->nullOnDelete();
            $table->foreignId('uploaded_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('file_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('storage_disk', 80);
            $table->string('storage_path', 700);
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['storage_disk', 'storage_path'], 'support_ticket_attachments_disk_path_unique');
            $table->index(['support_ticket_id', 'status'], 'support_ticket_attachments_ticket_status_index');
            $table->index(['checksum_sha256'], 'support_ticket_attachments_checksum_index');
        });

        $schema->create('support_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('tenant_account_id')->nullable()->constrained('tenant_accounts')->nullOnDelete();
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('event_type', 60);
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['support_ticket_id', 'occurred_at'], 'support_ticket_events_ticket_occurred_index');
            $table->index(['event_type', 'occurred_at'], 'support_ticket_events_type_occurred_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE support_ticket_categories ADD CONSTRAINT support_ticket_categories_area_check CHECK (support_area IN ('technical', 'billing', 'subscription', 'payment', 'integration', 'backup', 'restore', 'realtime', 'performance', 'security', 'training', 'other'))");
        $connection->statement("ALTER TABLE support_ticket_categories ADD CONSTRAINT support_ticket_categories_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement('ALTER TABLE support_ticket_categories ADD CONSTRAINT support_ticket_categories_priority_check CHECK (default_priority BETWEEN 1 AND 9)');
        $connection->statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_area_check CHECK (support_area IN ('technical', 'billing', 'subscription', 'payment', 'integration', 'backup', 'restore', 'realtime', 'performance', 'security', 'training', 'other'))");
        $connection->statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_status_check CHECK (status IN ('open', 'assigned', 'waiting_tenant', 'waiting_admin', 'resolved', 'closed', 'cancelled'))");
        $connection->statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_source_check CHECK (source IN ('tenant_portal', 'admin_created', 'email', 'phone', 'system'))");
        $connection->statement('ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_priority_check CHECK (priority BETWEEN 1 AND 9)');
        $connection->statement('ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_dates_check CHECK ((first_responded_at IS NULL OR first_responded_at >= opened_at) AND (resolved_at IS NULL OR resolved_at >= opened_at) AND (closed_at IS NULL OR resolved_at IS NULL OR closed_at >= resolved_at))');
        $connection->statement("ALTER TABLE support_ticket_messages ADD CONSTRAINT support_ticket_messages_sender_check CHECK (sender_type IN ('tenant', 'platform', 'system'))");
        $connection->statement("ALTER TABLE support_ticket_messages ADD CONSTRAINT support_ticket_messages_sender_reference_check CHECK ((sender_type = 'tenant' AND tenant_account_id IS NOT NULL) OR (sender_type = 'platform' AND platform_user_id IS NOT NULL) OR (sender_type = 'system'))");
        $connection->statement("ALTER TABLE support_ticket_attachments ADD CONSTRAINT support_ticket_attachments_status_check CHECK (status IN ('active', 'removed'))");
        $connection->statement('ALTER TABLE support_ticket_attachments ADD CONSTRAINT support_ticket_attachments_size_check CHECK (file_size_bytes > 0)');
        $connection->statement("ALTER TABLE support_ticket_events ADD CONSTRAINT support_ticket_events_type_check CHECK (event_type IN ('created', 'assigned', 'message_added', 'internal_note_added', 'priority_changed', 'status_changed', 'attachment_added', 'access_grant_requested', 'access_grant_approved', 'resolved', 'closed', 'reopened', 'cancelled'))");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        $schema->dropIfExists('support_ticket_events');
        $schema->dropIfExists('support_ticket_attachments');
        $schema->dropIfExists('support_ticket_messages');
        $schema->dropIfExists('support_tickets');
        $schema->dropIfExists('support_ticket_categories');
    }
};
