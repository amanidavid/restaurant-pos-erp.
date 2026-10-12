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

        if (! $schema->hasColumn('departments', 'name_normalized')) {
            $schema->table('departments', function (Blueprint $table): void {
                $table->string('name_normalized', 140)->nullable()->after('name');
            });
        }

        $connection = DB::connection(self::CONNECTION);
        $connection->statement("UPDATE departments SET name_normalized = LOWER(TRIM(REGEXP_REPLACE(name, '\\s+', ' ', 'g'))) WHERE name_normalized IS NULL");
        $connection->statement('ALTER TABLE departments ALTER COLUMN name_normalized SET NOT NULL');

        $duplicates = $connection->table('departments')
            ->selectRaw('branch_id, parent_id, name_normalized')
            ->whereNotNull('branch_id')
            ->groupBy('branch_id', 'parent_id', 'name_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new \RuntimeException('Resolve duplicate department names within each branch and parent before running this migration.');
        }

        $connection->statement('CREATE INDEX IF NOT EXISTS departments_branch_status_name_index ON departments (branch_id, status, name_normalized)');
        $connection->statement('CREATE INDEX IF NOT EXISTS departments_branch_status_parent_name_index ON departments (branch_id, status, parent_id, name_normalized)');
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS departments_branch_root_name_unique ON departments (branch_id, name_normalized) WHERE branch_id IS NOT NULL AND parent_id IS NULL');
        $connection->statement('CREATE UNIQUE INDEX IF NOT EXISTS departments_branch_parent_name_unique ON departments (branch_id, parent_id, name_normalized) WHERE branch_id IS NOT NULL AND parent_id IS NOT NULL');
    }

    public function down(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('DROP INDEX IF EXISTS departments_branch_parent_name_unique');
        $connection->statement('DROP INDEX IF EXISTS departments_branch_root_name_unique');
        $connection->statement('DROP INDEX IF EXISTS departments_branch_status_parent_name_index');
        $connection->statement('DROP INDEX IF EXISTS departments_branch_status_name_index');

        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasColumn('departments', 'name_normalized')) {
            $schema->table('departments', function (Blueprint $table): void {
                $table->dropColumn('name_normalized');
            });
        }
    }
};
