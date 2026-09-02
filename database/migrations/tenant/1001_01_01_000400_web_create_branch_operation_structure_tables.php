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

        if ($schema->hasTable('warehouses')) {
            return;
        }

        $schema->create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('type', 24);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'warehouses_branch_name_unique');
            $table->index(['branch_id', 'type', 'status'], 'warehouses_branch_type_status_index');
        });

        $schema->create('counters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('type', 24);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'counters_branch_name_unique');
            $table->index(['branch_id', 'type', 'status'], 'counters_branch_type_status_index');
        });

        $schema->create('dining_areas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'dining_areas_branch_name_unique');
            $table->index(['branch_id', 'status'], 'dining_areas_branch_status_index');
        });

        $schema->create('dining_tables', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('dining_area_id')->nullable()->constrained('dining_areas')->nullOnDelete();
            $table->string('name', 160);
            $table->unsignedSmallInteger('capacity');
            $table->string('shape', 24)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['branch_id', 'name'], 'dining_tables_branch_name_unique');
            $table->index(['branch_id', 'status'], 'dining_tables_branch_status_index');
        });

        $schema->create('shift_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('business_date_id')->nullable()->constrained('business_dates')->nullOnDelete();
            $table->foreignId('opened_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignId('closed_by_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('shift_number', 40);
            $table->string('name', 160);
            $table->string('status', 24)->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->decimal('opening_float_amount', 18, 2)->default(0);
            $table->decimal('expected_cash_amount', 18, 2)->default(0);
            $table->decimal('counted_cash_amount', 18, 2)->nullable();
            $table->decimal('closing_variance_amount', 18, 2)->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['branch_id', 'shift_number'], 'shift_sessions_branch_number_unique');
            $table->index(['branch_id', 'status', 'opened_at'], 'shift_sessions_branch_status_opened_index');
            $table->index(['business_date_id', 'status'], 'shift_sessions_business_date_status_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_type_check CHECK (type IN ('main', 'kitchen', 'bar', 'transit'))");
        $connection->statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_status_check CHECK (status IN ('active', 'inactive', 'closed'))");
        $connection->statement("ALTER TABLE counters ADD CONSTRAINT counters_type_check CHECK (type IN ('cashier', 'bar', 'takeaway', 'reception'))");
        $connection->statement("ALTER TABLE counters ADD CONSTRAINT counters_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE dining_areas ADD CONSTRAINT dining_areas_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_status_check CHECK (status IN ('active', 'inactive', 'maintenance'))");
        $connection->statement('ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_capacity_check CHECK (capacity > 0)');
        $connection->statement("ALTER TABLE shift_sessions ADD CONSTRAINT shift_sessions_status_check CHECK (status IN ('open', 'closing', 'closed'))");
        $connection->statement('ALTER TABLE shift_sessions ADD CONSTRAINT shift_sessions_dates_check CHECK (closed_at IS NULL OR closed_at >= opened_at)');
        $connection->statement('ALTER TABLE shift_sessions ADD CONSTRAINT shift_sessions_cash_amounts_check CHECK (opening_float_amount >= 0 AND expected_cash_amount >= 0 AND (counted_cash_amount IS NULL OR counted_cash_amount >= 0))');
        $connection->statement("CREATE UNIQUE INDEX shift_sessions_one_open_unique ON shift_sessions (branch_id) WHERE status IN ('open', 'closing')");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $schema->dropIfExists('shift_sessions');
        $schema->dropIfExists('dining_tables');
        $schema->dropIfExists('dining_areas');
        $schema->dropIfExists('counters');
        $schema->dropIfExists('warehouses');
    }
};
