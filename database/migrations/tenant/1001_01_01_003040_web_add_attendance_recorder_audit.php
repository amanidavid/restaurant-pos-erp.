<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('tenant');
        $db->statement('ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS recorded_by_tenant_user_id BIGINT NULL REFERENCES tenant_users(id) ON DELETE SET NULL');
        $db->statement('ALTER TABLE attendance_records ADD COLUMN IF NOT EXISTS recorded_at TIMESTAMP WITH TIME ZONE NULL');
        $db->statement('CREATE INDEX IF NOT EXISTS attendance_records_recorder_index ON attendance_records (recorded_by_tenant_user_id, recorded_at DESC) WHERE recorded_by_tenant_user_id IS NOT NULL');
    }

    public function down(): void
    {
        $db = DB::connection('tenant');
        $db->statement('DROP INDEX IF EXISTS attendance_records_recorder_index');
        $db->statement('ALTER TABLE attendance_records DROP COLUMN IF EXISTS recorded_at');
        $db->statement('ALTER TABLE attendance_records DROP COLUMN IF EXISTS recorded_by_tenant_user_id');
    }
};
