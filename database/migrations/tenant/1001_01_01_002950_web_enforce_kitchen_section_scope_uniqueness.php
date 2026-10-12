<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $connection = DB::connection(self::CONNECTION);

        $connection->statement(
            'CREATE INDEX IF NOT EXISTS kitchen_sections_branch_lower_name_index '
            .'ON kitchen_sections (COALESCE(branch_id, 0), LOWER(name))'
        );

        $connection->unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_kitchen_section_scope_uniqueness()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND NEW.branch_id IS NOT DISTINCT FROM OLD.branch_id
                    AND LOWER(NEW.name) = LOWER(OLD.name) THEN
                    RETURN NEW;
                END IF;

                PERFORM pg_advisory_xact_lock(
                    hashtext('kitchen-section:' || COALESCE(NEW.branch_id::text, 'global') || ':' || LOWER(NEW.name))
                );

                IF EXISTS (
                    SELECT 1
                    FROM kitchen_sections
                    WHERE branch_id IS NOT DISTINCT FROM NEW.branch_id
                      AND LOWER(name) = LOWER(NEW.name)
                      AND id <> COALESCE(NEW.id, 0)
                ) THEN
                    RAISE EXCEPTION 'This kitchen section already exists.'
                        USING ERRCODE = '23505',
                              CONSTRAINT = 'kitchen_sections_branch_name_unique';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        $connection->statement('DROP TRIGGER IF EXISTS kitchen_sections_scope_unique_trigger ON kitchen_sections');
        $connection->statement(
            'CREATE TRIGGER kitchen_sections_scope_unique_trigger '
            .'BEFORE INSERT OR UPDATE OF branch_id, name ON kitchen_sections '
            .'FOR EACH ROW EXECUTE FUNCTION enforce_kitchen_section_scope_uniqueness()'
        );
    }

    public function down(): void
    {
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP TRIGGER IF EXISTS kitchen_sections_scope_unique_trigger ON kitchen_sections');
        $connection->statement('DROP FUNCTION IF EXISTS enforce_kitchen_section_scope_uniqueness()');
        $connection->statement('DROP INDEX IF EXISTS kitchen_sections_branch_lower_name_index');
    }
};
