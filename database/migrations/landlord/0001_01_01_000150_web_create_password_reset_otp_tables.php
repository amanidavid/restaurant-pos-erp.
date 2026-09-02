<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('landlord');

        if ($schema->hasTable('password_reset_otps')) {
            return;
        }

        $schema->create('password_reset_otps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('identifier_type', 10);
            $table->string('identifier_normalized', 254);
            $table->string('channel', 10);
            $table->string('otp_hash', 255);
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestampTz('requested_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampsTz();

            $table->index(['identifier_type', 'identifier_normalized', 'status', 'expires_at'], 'landlord_password_reset_identifier_status_index');
            $table->index(['user_id', 'status', 'expires_at'], 'landlord_password_reset_user_status_index');
        });

        $connection = DB::connection('landlord');
        $connection->statement("ALTER TABLE password_reset_otps ADD CONSTRAINT landlord_password_reset_identifier_type_check CHECK (identifier_type IN ('email', 'phone'))");
        $connection->statement("ALTER TABLE password_reset_otps ADD CONSTRAINT landlord_password_reset_channel_check CHECK (channel IN ('email', 'sms'))");
        $connection->statement("ALTER TABLE password_reset_otps ADD CONSTRAINT landlord_password_reset_status_check CHECK (status IN ('pending', 'verified', 'used', 'expired', 'blocked'))");
        $connection->statement('ALTER TABLE password_reset_otps ADD CONSTRAINT landlord_password_reset_dates_check CHECK (expires_at >= requested_at)');
        $connection->statement("CREATE UNIQUE INDEX landlord_password_reset_one_pending_unique ON password_reset_otps (user_id, channel) WHERE status IN ('pending', 'verified')");
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('password_reset_otps');
    }
};
