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

        if ($schema->hasTable('features')) {
            return;
        }

        $schema->create('features', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('category', 40);
            $table->string('status', 24)->default('active');
            $table->boolean('is_billable')->default(true);
            $table->timestampsTz();

            $table->index(['category', 'status'], 'features_category_status_index');
            $table->index(['status', 'created_at'], 'features_status_created_index');
        });

        $schema->create('packages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('billing_interval', 24);
            $table->decimal('base_price', 18, 2)->default(0);
            $table->char('currency_code', 3);
            $table->string('status', 24)->default('draft');
            $table->boolean('is_custom')->default(false);
            $table->timestampsTz();

            $table->index(['status', 'billing_interval'], 'packages_status_interval_index');
            $table->index(['is_custom', 'status'], 'packages_custom_status_index');
        });

        $schema->create('package_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('limit_value')->nullable();
            $table->string('limit_period', 24)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['package_id', 'feature_id'], 'package_features_package_feature_unique');
            $table->index(['feature_id', 'is_enabled'], 'package_features_feature_enabled_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE features ADD CONSTRAINT features_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE packages ADD CONSTRAINT packages_status_check CHECK (status IN ('draft', 'active', 'retired'))");
        $connection->statement("ALTER TABLE packages ADD CONSTRAINT packages_billing_interval_check CHECK (billing_interval IN ('monthly', 'yearly', 'one_time'))");
        $connection->statement("ALTER TABLE package_features ADD CONSTRAINT package_features_limit_period_check CHECK (limit_period IS NULL OR limit_period IN ('day', 'week', 'month', 'quarter', 'year', 'lifetime'))");
        $connection->statement('ALTER TABLE package_features ADD CONSTRAINT package_features_limit_value_check CHECK (limit_value IS NULL OR limit_value > 0)');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('package_features');
        $schema->dropIfExists('packages');
        $schema->dropIfExists('features');
    }
};
