<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('business_dates', function (Blueprint $table): void {
            $table->index(['branch_id', 'business_date', 'id'], 'business_dates_branch_date_id_index');
        });
        Schema::connection('tenant')->table('cash_movements', function (Blueprint $table): void {
            $table->index(['cash_account_id', 'business_date_id', 'moved_at', 'id'], 'cash_movements_account_day_time_index');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('cash_movements', function (Blueprint $table): void {
            $table->dropIndex('cash_movements_account_day_time_index');
        });
        Schema::connection('tenant')->table('business_dates', function (Blueprint $table): void {
            $table->dropIndex('business_dates_branch_date_id_index');
        });
    }
};
