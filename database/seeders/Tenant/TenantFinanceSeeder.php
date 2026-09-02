<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class TenantFinanceSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'tenant';

    public function run(): void
    {
        $company = $this->connection(self::CONNECTION)->table('companies')->first(['id', 'base_currency_code']);

        if (! $company) {
            $this->command?->warn('TenantFinanceSeeder skipped: no company found.');
            return;
        }

        $classes = $this->seedAccountClasses();
        $categories = $this->seedAccountCategories((int) $company->id, $classes);
        $accounts = $this->seedChartAccounts((int) $company->id, $categories);
        $this->seedJournalSources((int) $company->id);
        $this->seedCashAccounts($company->base_currency_code ?? 'TZS', $accounts);
        $this->seedExpenseCategories((int) $company->id, $accounts);
    }

    /**
     * @return array<string, int>
     */
    private function seedAccountClasses(): array
    {
        $classes = [];

        foreach ([
            'Assets' => 'debit',
            'Liabilities' => 'credit',
            'Equity' => 'credit',
            'Income' => 'credit',
            'Expenses' => 'debit',
        ] as $name => $normalBalance) {
            $classes[$name] = $this->upsertRow(self::CONNECTION, 'account_classes', [
                'name' => $name,
            ], [
                'normal_balance' => $normalBalance,
                'is_active' => true,
            ]);
        }

        return $classes;
    }

    /**
     * @param  array<string, int>  $classes
     * @return array<string, int>
     */
    private function seedAccountCategories(int $companyId, array $classes): array
    {
        $categories = [];

        foreach ([
            ['Current Assets', 'Assets', 'current'],
            ['Non Current Assets', 'Assets', 'non_current'],
            ['Current Liabilities', 'Liabilities', 'current'],
            ['Equity', 'Equity', null],
            ['Sales Income', 'Income', 'operating'],
            ['Direct Expenses', 'Expenses', 'direct'],
            ['Indirect Expenses', 'Expenses', 'indirect'],
            ['Operating Expenses', 'Expenses', 'operating'],
            ['Statutory Expenses', 'Expenses', 'statutory'],
        ] as [$name, $className, $nature]) {
            $categories[$name] = $this->upsertRow(self::CONNECTION, 'account_categories', [
                'company_id' => $companyId,
                'account_class_id' => $classes[$className],
                'name' => $name,
            ], [
                'category_nature' => $nature,
                'status' => 'active',
            ]);
        }

        return $categories;
    }

    /**
     * @param  array<string, int>  $categories
     * @return array<string, int>
     */
    private function seedChartAccounts(int $companyId, array $categories): array
    {
        $accounts = [];

        foreach ([
            ['Cash on Hand', '1000', 'Current Assets'],
            ['Bank', '1100', 'Current Assets'],
            ['Accounts Receivable', '1200', 'Current Assets'],
            ['Inventory', '1300', 'Current Assets'],
            ['Accounts Payable', '2000', 'Current Liabilities'],
            ['VAT Payable', '2100', 'Current Liabilities'],
            ['Payroll Payable', '2200', 'Current Liabilities'],
            ['Owner Equity', '3000', 'Equity'],
            ['Sales Revenue', '4000', 'Sales Income'],
            ['Cost of Goods Sold', '5000', 'Direct Expenses'],
            ['Operating Expenses', '6000', 'Operating Expenses'],
            ['Petty Cash Expenses', '6100', 'Operating Expenses'],
        ] as [$name, $number, $categoryName]) {
            $accounts[$name] = $this->upsertRow(self::CONNECTION, 'chart_accounts', [
                'company_id' => $companyId,
                'account_number' => $number,
            ], [
                'account_category_id' => $categories[$categoryName],
                'account_subcategory_id' => null,
                'parent_id' => null,
                'name' => $name,
                'is_control_account' => in_array($name, ['Cash on Hand', 'Bank', 'Accounts Payable', 'Inventory'], true),
                'allow_manual_posting' => ! in_array($name, ['Accounts Payable', 'Inventory'], true),
                'status' => 'active',
            ]);
        }

        return $accounts;
    }

    private function seedJournalSources(int $companyId): void
    {
        foreach ([
            ['Manual Journal', 'manual'],
            ['Sales Posting', 'sales'],
            ['Purchase Posting', 'purchases'],
            ['Inventory Posting', 'inventory'],
            ['Payroll Posting', 'payroll'],
            ['Payment Posting', 'payments'],
            ['Banking Posting', 'banking'],
            ['Settlement Posting', 'settlement'],
        ] as [$name, $area]) {
            $this->upsertRow(self::CONNECTION, 'journal_sources', [
                'company_id' => $companyId,
                'name' => $name,
            ], [
                'source_area' => $area,
                'status' => 'active',
            ]);
        }
    }

    /**
     * @param  array<string, int>  $accounts
     */
    private function seedCashAccounts(string $currency, array $accounts): void
    {
        if (! $this->tableExists(self::CONNECTION, 'cash_accounts')) {
            return;
        }

        $branches = $this->connection(self::CONNECTION)->table('branches')->get(['id']);

        foreach ($branches as $branch) {
            foreach ([
                ['Cash Drawer', 'cash_drawer', true, true],
                ['Petty Cash', 'petty_cash', false, false],
                ['Branch Safe', 'safe', false, true],
            ] as [$name, $type, $default, $reconcile]) {
                $this->upsertRow(self::CONNECTION, 'cash_accounts', [
                    'branch_id' => $branch->id,
                    'name' => $name,
                ], [
                    'counter_id' => null,
                    'chart_account_id' => $accounts['Cash on Hand'] ?? null,
                    'account_type' => $type,
                    'currency_code' => $currency,
                    'is_default' => $default,
                    'requires_reconciliation' => $reconcile,
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * @param  array<string, int>  $accounts
     */
    private function seedExpenseCategories(int $companyId, array $accounts): void
    {
        if (! $this->tableExists(self::CONNECTION, 'expense_categories')) {
            return;
        }

        foreach ([
            ['Cleaning Supplies', 'operating', true, true],
            ['Staff Transport', 'staff', false, true],
            ['Repairs and Maintenance', 'repairs', true, true],
            ['Utilities', 'utilities', true, true],
            ['Marketing', 'marketing', true, true],
            ['Other Expense', 'other', false, true],
        ] as [$name, $type, $receipt, $approval]) {
            $this->upsertRow(self::CONNECTION, 'expense_categories', [
                'company_id' => $companyId,
                'name' => $name,
            ], [
                'chart_account_id' => $accounts['Petty Cash Expenses'] ?? $accounts['Operating Expenses'] ?? null,
                'expense_type' => $type,
                'requires_receipt' => $receipt,
                'requires_approval' => $approval,
                'status' => 'active',
            ]);
        }
    }
}
