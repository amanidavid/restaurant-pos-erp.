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

        if ($schema->hasTable('tenant_subscriptions')) {
            return;
        }

        $schema->create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('subscription_number', 40)->unique();
            $table->string('status', 24)->default('draft');
            $table->timestampTz('starts_at');
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_starts_at')->nullable();
            $table->timestampTz('current_period_ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->decimal('price_snapshot', 18, 2);
            $table->char('currency_code', 3);
            $table->timestampsTz();

            $table->index(['tenant_id', 'status'], 'tenant_subscriptions_tenant_status_index');
            $table->index(['status', 'current_period_ends_at'], 'tenant_subscriptions_status_period_end_index');
            $table->index(['package_id', 'status'], 'tenant_subscriptions_package_status_index');
        });

        $schema->create('tenant_feature_overrides', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('feature_id')->constrained('features')->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('override_type', 24);
            $table->boolean('is_enabled')->nullable();
            $table->unsignedInteger('limit_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignId('created_by_platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['tenant_id', 'feature_id'], 'tenant_feature_overrides_tenant_feature_index');
            $table->index(['subscription_id', 'feature_id'], 'tenant_feature_overrides_subscription_feature_index');
            $table->index(['override_type', 'ends_at'], 'tenant_feature_overrides_type_end_index');
        });

        $schema->create('subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('tenant_subscriptions')->restrictOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('status', 24)->default('draft');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->char('currency_code', 3);
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status'], 'subscription_invoices_tenant_status_index');
            $table->index(['subscription_id', 'status'], 'subscription_invoices_subscription_status_index');
            $table->index(['status', 'due_at'], 'subscription_invoices_status_due_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_status_check CHECK (status IN ('draft', 'trial', 'active', 'past_due', 'suspended', 'expired', 'cancelled'))");
        $connection->statement('ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_period_dates_check CHECK (current_period_ends_at IS NULL OR current_period_starts_at IS NULL OR current_period_ends_at >= current_period_starts_at)');
        $connection->statement('ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_end_dates_check CHECK (ended_at IS NULL OR ended_at >= starts_at)');
        $connection->statement("ALTER TABLE tenant_feature_overrides ADD CONSTRAINT tenant_feature_overrides_type_check CHECK (override_type IN ('enable', 'disable', 'limit'))");
        $connection->statement('ALTER TABLE tenant_feature_overrides ADD CONSTRAINT tenant_feature_overrides_limit_value_check CHECK (limit_value IS NULL OR limit_value > 0)');
        $connection->statement('ALTER TABLE tenant_feature_overrides ADD CONSTRAINT tenant_feature_overrides_dates_check CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)');
        $connection->statement("ALTER TABLE subscription_invoices ADD CONSTRAINT subscription_invoices_status_check CHECK (status IN ('draft', 'issued', 'paid', 'void', 'overdue'))");
        $connection->statement('ALTER TABLE subscription_invoices ADD CONSTRAINT subscription_invoices_period_dates_check CHECK (billing_period_end >= billing_period_start)');
        $connection->statement('ALTER TABLE subscription_invoices ADD CONSTRAINT subscription_invoices_amounts_check CHECK (subtotal_amount >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND paid_amount <= total_amount)');
        $connection->statement("CREATE UNIQUE INDEX tenant_subscriptions_one_current_unique ON tenant_subscriptions (tenant_id) WHERE status IN ('trial', 'active', 'past_due', 'suspended')");
        $connection->statement("CREATE UNIQUE INDEX tenant_feature_overrides_one_open_unique ON tenant_feature_overrides (tenant_id, feature_id) WHERE ends_at IS NULL");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('subscription_invoices');
        $schema->dropIfExists('tenant_feature_overrides');
        $schema->dropIfExists('tenant_subscriptions');
    }
};
