<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('tenant_user_branches') || $schema->hasColumn('tenant_user_branches', 'uuid')) {
            return;
        }

        $schema->table('tenant_user_branches', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
        });

        $connection = DB::connection(self::CONNECTION);

        $connection->table('tenant_user_branches')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($assignments) use ($connection): void {
                foreach ($assignments as $assignment) {
                    $connection->table('tenant_user_branches')
                        ->where('id', $assignment->id)
                        ->update(['uuid' => (string) Str::uuid7()]);
                }
            });

        $connection->statement('ALTER TABLE tenant_user_branches ALTER COLUMN uuid SET NOT NULL');
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS tenant_user_branches_uuid_unique ON tenant_user_branches (uuid)');
    }

    public function down(): void
    {
        // Intentionally retained: this repair can also support rows that have already been referenced externally by UUID.
    }
};
