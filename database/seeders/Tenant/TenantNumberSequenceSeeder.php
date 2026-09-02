<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class TenantNumberSequenceSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'tenant';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'number_sequences')) {
            return;
        }

        $company = $this->connection(self::CONNECTION)->table('companies')->first(['id']);

        if (! $company) {
            $this->command?->warn('TenantNumberSequenceSeeder skipped: no company found.');
            return;
        }

        $companySequences = [
            ['journal_entry', 'Journal Entries', 'JRN', 'yearly', 2],
            ['expense', 'Expenses', 'EXP', 'yearly', 2],
            ['supplier_bill', 'Supplier Bills', 'BILL', 'yearly', 2],
        ];

        foreach ($companySequences as [$key, $name, $prefix, $period, $padding]) {
            $this->upsertRow(self::CONNECTION, 'number_sequences', [
                'company_id' => $company->id,
                'branch_id' => null,
                'sequence_key' => $key,
                'period_starts_on' => null,
            ], [
                'display_name' => $name,
                'prefix' => $prefix,
                'suffix' => null,
                'period_type' => $period,
                'period_ends_on' => null,
                'next_number' => 1,
                'padding_length' => $padding,
                'reset_per_branch' => false,
                'is_active' => true,
            ]);
        }

        $branches = $this->connection(self::CONNECTION)->table('branches')->get(['id', 'name']);
        $branchSequences = [
            ['business_day', 'Business Days', 'BD', 'daily', 2],
            ['shift', 'Shifts', 'SHIFT', 'daily', 2],
            ['order', 'Orders', 'ORD', 'yearly', 2],
            ['receipt', 'Receipts', 'REC', 'yearly', 2],
            ['refund', 'Refunds', 'REF', 'yearly', 2],
            ['kitchen_ticket', 'Kitchen Tickets', 'KIT', 'daily', 2],
            ['reservation', 'Reservations', 'RSV', 'yearly', 2],
            ['purchase_order', 'Purchase Orders', 'PO', 'yearly', 2],
            ['goods_receipt', 'Goods Receipts', 'GRN', 'yearly', 2],
            ['stock_adjustment', 'Stock Adjustments', 'ADJ', 'yearly', 2],
            ['stock_transfer', 'Stock Transfers', 'TRF', 'yearly', 2],
            ['stock_count', 'Stock Counts', 'CNT', 'yearly', 2],
            ['waste_record', 'Waste Records', 'WST', 'yearly', 2],
            ['cash_reconciliation', 'Cash Reconciliations', 'CASH', 'daily', 2],
        ];

        foreach ($branches as $branch) {
            foreach ($branchSequences as [$key, $name, $prefix, $period, $padding]) {
                $this->upsertRow(self::CONNECTION, 'number_sequences', [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'sequence_key' => $key,
                    'period_starts_on' => null,
                ], [
                    'display_name' => "{$branch->name} {$name}",
                    'prefix' => $prefix,
                    'suffix' => null,
                    'period_type' => $period,
                    'period_ends_on' => null,
                    'next_number' => 1,
                    'padding_length' => $padding,
                    'reset_per_branch' => true,
                    'is_active' => true,
                ]);
            }
        }
    }
}
