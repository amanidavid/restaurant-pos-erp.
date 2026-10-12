<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tenant');

        $db->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $db->statement("CREATE TABLE IF NOT EXISTS attendance_adjustments (id BIGSERIAL PRIMARY KEY, uuid UUID NOT NULL UNIQUE, attendance_record_id BIGINT NOT NULL REFERENCES attendance_records(id) ON DELETE CASCADE, adjusted_by_tenant_user_id BIGINT NOT NULL REFERENCES tenant_users(id) ON DELETE RESTRICT, reason TEXT NOT NULL, previous_values JSONB NOT NULL, new_values JSONB NOT NULL, created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW())");
        $db->statement('CREATE INDEX IF NOT EXISTS attendance_adjustments_record_created_index ON attendance_adjustments (attendance_record_id, created_at DESC)');
        $db->unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'shift_assignments_active_range_exclusion'
                      AND conrelid = 'shift_assignments'::regclass
                ) THEN
                    ALTER TABLE shift_assignments
                    ADD CONSTRAINT shift_assignments_active_range_exclusion
                    EXCLUDE USING gist (
                        branch_id WITH =,
                        tenant_user_id WITH =,
                        daterange(valid_from, COALESCE(valid_to, 'infinity'::date), '[]') WITH &&
                    ) WHERE (status = 'active');
                END IF;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE shift_assignments DROP CONSTRAINT IF EXISTS shift_assignments_active_range_exclusion');
        $db->statement('DROP TABLE IF EXISTS attendance_adjustments');
    }
};
