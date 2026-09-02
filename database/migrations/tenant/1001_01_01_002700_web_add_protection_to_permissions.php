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

        if (! $schema->hasTable('permissions') || $schema->hasColumn('permissions', 'is_protected')) {
            return;
        }

        $schema->table('permissions', function (Blueprint $table): void {
            $table->boolean('is_protected')->default(false)->after('is_system');
            $table->index(['is_system', 'is_protected', 'name'], 'permissions_system_protected_name_index');
        });

        DB::connection(self::CONNECTION)
            ->table('permissions')
            ->where('is_system', true)
            ->update(['is_protected' => true]);
    }

    public function down(): void
    {
        // Retained because system permissions may be referenced by source code.
    }
};
