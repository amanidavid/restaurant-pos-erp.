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

        if ($schema->hasTable('number_sequences')) {
            return;
        }

        $schema->create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('sequence_key', 80);
            $table->string('display_name', 160);
            $table->string('prefix', 30)->nullable();
            $table->string('suffix', 30)->nullable();
            $table->string('period_type', 24)->default('yearly');
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedSmallInteger('padding_length')->default(2);
            $table->boolean('reset_per_branch')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['company_id', 'sequence_key', 'is_active'], 'number_sequences_company_key_active_index');
            $table->index(['branch_id', 'sequence_key', 'is_active'], 'number_sequences_branch_key_active_index');
        });

        $schema->create('number_sequence_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('number_sequence_id')->constrained('number_sequences')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('sequence_key', 80);
            $table->unsignedBigInteger('allocated_number');
            $table->string('formatted_number', 80);
            $table->string('consumer_table', 120)->nullable();
            $table->unsignedBigInteger('consumer_id')->nullable();
            $table->uuid('consumer_uuid')->nullable();
            $table->string('status', 24)->default('reserved');
            $table->timestampTz('reserved_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampsTz();

            $table->unique(['number_sequence_id', 'allocated_number'], 'number_allocations_sequence_number_unique');
            $table->unique(['number_sequence_id', 'formatted_number'], 'number_allocations_sequence_formatted_unique');
            $table->index(['branch_id', 'formatted_number'], 'number_allocations_branch_formatted_index');
            $table->index(['sequence_key', 'status', 'reserved_at'], 'number_allocations_key_status_reserved_index');
            $table->index(['consumer_table', 'consumer_id'], 'number_allocations_consumer_index');
            $table->index(['consumer_uuid'], 'number_allocations_consumer_uuid_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE number_sequences ADD CONSTRAINT number_sequences_period_type_check CHECK (period_type IN ('none', 'daily', 'monthly', 'yearly'))");
        $connection->statement('ALTER TABLE number_sequences ADD CONSTRAINT number_sequences_period_dates_check CHECK (period_ends_on IS NULL OR period_starts_on IS NULL OR period_ends_on >= period_starts_on)');
        $connection->statement('ALTER TABLE number_sequences ADD CONSTRAINT number_sequences_values_check CHECK (next_number > 0 AND padding_length BETWEEN 1 AND 12)');
        $connection->statement('CREATE UNIQUE INDEX number_sequences_company_no_period_unique ON number_sequences (company_id, sequence_key) WHERE branch_id IS NULL AND period_starts_on IS NULL');
        $connection->statement('CREATE UNIQUE INDEX number_sequences_company_period_unique ON number_sequences (company_id, sequence_key, period_starts_on) WHERE branch_id IS NULL AND period_starts_on IS NOT NULL');
        $connection->statement('CREATE UNIQUE INDEX number_sequences_branch_no_period_unique ON number_sequences (branch_id, sequence_key) WHERE branch_id IS NOT NULL AND period_starts_on IS NULL');
        $connection->statement('CREATE UNIQUE INDEX number_sequences_branch_period_unique ON number_sequences (branch_id, sequence_key, period_starts_on) WHERE branch_id IS NOT NULL AND period_starts_on IS NOT NULL');
        $connection->statement("ALTER TABLE number_sequence_allocations ADD CONSTRAINT number_allocations_status_check CHECK (status IN ('reserved', 'used', 'voided'))");
        $connection->statement('ALTER TABLE number_sequence_allocations ADD CONSTRAINT number_allocations_dates_check CHECK ((used_at IS NULL OR used_at >= reserved_at) AND (voided_at IS NULL OR voided_at >= reserved_at))');
        $connection->statement('ALTER TABLE number_sequence_allocations ADD CONSTRAINT number_allocations_consumer_check CHECK ((status = \'used\' AND (consumer_id IS NOT NULL OR consumer_uuid IS NOT NULL)) OR status <> \'used\')');
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP INDEX IF EXISTS number_sequences_branch_period_unique');
        $connection->statement('DROP INDEX IF EXISTS number_sequences_branch_no_period_unique');
        $connection->statement('DROP INDEX IF EXISTS number_sequences_company_period_unique');
        $connection->statement('DROP INDEX IF EXISTS number_sequences_company_no_period_unique');

        $schema->dropIfExists('number_sequence_allocations');
        $schema->dropIfExists('number_sequences');
    }
};
