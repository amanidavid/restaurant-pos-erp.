<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = \Illuminate\Support\Facades\DB::connection('tenant');
        $connection->statement('ALTER TABLE business_day_cash_reconciliations DROP CONSTRAINT IF EXISTS business_day_cash_recon_status_check');
        $connection->statement("ALTER TABLE business_day_cash_reconciliations ADD CONSTRAINT business_day_cash_recon_status_check CHECK (status IN ('open', 'closing', 'closed'))");
    }

    public function down(): void
    {
        $connection = \Illuminate\Support\Facades\DB::connection('tenant');
        $connection->statement('ALTER TABLE business_day_cash_reconciliations DROP CONSTRAINT IF EXISTS business_day_cash_recon_status_check');
        $connection->statement("ALTER TABLE business_day_cash_reconciliations ADD CONSTRAINT business_day_cash_recon_status_check CHECK (status IN ('open', 'closing', 'closed', 'approved'))");
    }
};
