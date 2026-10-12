<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        $schema->table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('business_date_id')->nullable()->after('branch_id')->constrained('business_dates')->nullOnDelete();
            $table->index(['cash_account_id', 'business_date_id', 'movement_reason'], 'cash_movements_account_business_reason_index');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        $schema->table('cash_movements', function (Blueprint $table): void {
            $table->dropIndex('cash_movements_account_business_reason_index');
            $table->dropConstrainedForeignId('business_date_id');
        });
    }
};
