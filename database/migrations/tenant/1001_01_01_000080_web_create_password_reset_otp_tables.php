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

            $table->index(
                ['identifier_type', 'identifier_normalized', 'status', 'expires_at'],
                'password_reset_otps_identifier_status_expiry_index'
            );
            $table->index(['user_id', 'status', 'expires_at'], 'password_reset_otps_user_status_expiry_index');
            $table->index(['status', 'expires_at'], 'password_reset_otps_status_expiry_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("ALTER TABLE password_reset_otps ADD CONSTRAINT password_reset_otps_identifier_type_check CHECK (identifier_type IN ('email', 'phone'))");
        $connection->statement("ALTER TABLE password_reset_otps ADD CONSTRAINT password_reset_otps_channel_check CHECK (channel IN ('email', 'sms'))");
        $connection->statement("ALTER TABLE password_reset_otps ADD CONSTRAINT password_reset_otps_status_check CHECK (status IN ('pending', 'verified', 'used', 'expired', 'blocked'))");
        $connection->statement('ALTER TABLE password_reset_otps ADD CONSTRAINT password_reset_otps_dates_check CHECK (expires_at >= requested_at)');
        $connection->statement("CREATE UNIQUE INDEX password_reset_otps_one_pending_user_channel_unique ON password_reset_otps (user_id, channel) WHERE status IN ('pending', 'verified')");
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('password_reset_otps');
    }
};
