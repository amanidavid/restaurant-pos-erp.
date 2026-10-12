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
            'CREATE INDEX IF NOT EXISTS menu_categories_company_parent_lower_name_index '
            .'ON menu_categories (company_id, COALESCE(parent_id, 0), LOWER(name))'
        );

        $connection->unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_menu_category_scope_uniqueness()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                    AND NEW.company_id IS NOT DISTINCT FROM OLD.company_id
                    AND NEW.parent_id IS NOT DISTINCT FROM OLD.parent_id
                    AND LOWER(NEW.name) = LOWER(OLD.name) THEN
                    RETURN NEW;
                END IF;

                PERFORM pg_advisory_xact_lock(
                    hashtext('menu-category:' || NEW.company_id || ':' || COALESCE(NEW.parent_id::text, 'root') || ':' || LOWER(NEW.name))
                );

                IF EXISTS (
                    SELECT 1
                    FROM menu_categories
                    WHERE company_id = NEW.company_id
                      AND parent_id IS NOT DISTINCT FROM NEW.parent_id
                      AND LOWER(name) = LOWER(NEW.name)
                      AND id <> COALESCE(NEW.id, 0)
                ) THEN
                    RAISE EXCEPTION 'This menu category already exists.'
                        USING ERRCODE = '23505',
                              CONSTRAINT = 'menu_categories_company_parent_name_unique';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        $connection->statement('DROP TRIGGER IF EXISTS menu_categories_scope_unique_trigger ON menu_categories');
        $connection->statement(
            'CREATE TRIGGER menu_categories_scope_unique_trigger '
            .'BEFORE INSERT OR UPDATE OF company_id, parent_id, name ON menu_categories '
            .'FOR EACH ROW EXECUTE FUNCTION enforce_menu_category_scope_uniqueness()'
        );
    }

    public function down(): void
    {
        $connection = DB::connection(self::CONNECTION);

        $connection->statement('DROP TRIGGER IF EXISTS menu_categories_scope_unique_trigger ON menu_categories');
        $connection->statement('DROP FUNCTION IF EXISTS enforce_menu_category_scope_uniqueness()');
        $connection->statement('DROP INDEX IF EXISTS menu_categories_company_parent_lower_name_index');
    }
};
