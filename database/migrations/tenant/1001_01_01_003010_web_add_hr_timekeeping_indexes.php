<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tenant');

        $db->statement('ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS adjusted_by_tenant_user_id BIGINT NULL REFERENCES tenant_users(id) ON DELETE SET NULL');
        $db->statement('ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS adjusted_at TIMESTAMP WITH TIME ZONE NULL');
        $db->statement('ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS adjustment_reason TEXT NULL');

        $db->statement('CREATE UNIQUE INDEX IF NOT EXISTS shift_definitions_branch_name_unique_index ON shift_definitions (company_id, branch_id, LOWER(name)) WHERE branch_id IS NOT NULL');
        $db->statement('CREATE INDEX IF NOT EXISTS shift_assignments_branch_status_dates_index ON shift_assignments (branch_id, status, valid_from, valid_to)');
        $db->statement('CREATE INDEX IF NOT EXISTS shift_assignments_branch_valid_from_index ON shift_assignments (branch_id, valid_from DESC, id DESC)');
        $db->statement('CREATE INDEX IF NOT EXISTS attendance_records_branch_user_date_index ON attendance_records (branch_id, tenant_user_id, attendance_date DESC)');
        $db->statement('CREATE INDEX IF NOT EXISTS attendance_records_branch_status_date_index ON attendance_records (branch_id, status, attendance_date DESC)');
        $db->statement('CREATE INDEX IF NOT EXISTS attendance_records_branch_date_id_index ON attendance_records (branch_id, attendance_date DESC, id DESC)');
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP INDEX IF EXISTS attendance_records_branch_status_date_index');
        $db->statement('DROP INDEX IF EXISTS attendance_records_branch_user_date_index');
        $db->statement('DROP INDEX IF EXISTS attendance_records_branch_date_id_index');
        $db->statement('DROP INDEX IF EXISTS shift_assignments_branch_valid_from_index');
        $db->statement('DROP INDEX IF EXISTS shift_assignments_branch_status_dates_index');
        $db->statement('DROP INDEX IF EXISTS shift_definitions_branch_name_unique_index');

        $db->statement('ALTER TABLE attendance_records DROP COLUMN IF EXISTS adjustment_reason');
        $db->statement('ALTER TABLE attendance_records DROP COLUMN IF EXISTS adjusted_at');
        $db->statement('ALTER TABLE attendance_records DROP COLUMN IF EXISTS adjusted_by_tenant_user_id');
    }
};
