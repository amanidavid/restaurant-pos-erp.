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

        if ($schema->hasTable('users')) {
            return;
        }

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('display_name', 180);
            $table->string('email', 254)->nullable();
            $table->string('email_normalized', 254)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('phone_normalized', 20)->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampTz('password_changed_at')->nullable();
            $table->unsignedBigInteger('token_version')->default(1);
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->index(['status', 'created_at'], 'users_status_created_index');
            $table->index(['display_name'], 'users_display_name_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('ALTER TABLE users ADD CONSTRAINT users_login_identifier_check CHECK (email_normalized IS NOT NULL OR phone_normalized IS NOT NULL)');
        $connection->statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended', 'disabled'))");
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->dropIfExists('users');
    }
};
