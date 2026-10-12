<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class TenantCatalogPaymentInventorySeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'tenant';

    public function run(): void
    {
        $company = $this->connection(self::CONNECTION)->table('companies')->first(['id', 'base_currency_code']);

        if (! $company) {
            $this->command?->warn('TenantCatalogPaymentInventorySeeder skipped: no company found.');

            return;
        }

        $this->seedUnits((int) $company->id);
        $this->seedTaxCodes((int) $company->id);
        $this->seedPriceList((int) $company->id, $company->base_currency_code ?? 'TZS');
        $this->seedSalesChannels((int) $company->id);
        $this->seedServiceTypes((int) $company->id);
        $this->seedPaymentMethods((int) $company->id);
        $this->seedStockMovementTypes();
        $this->call(TenantShiftDefinitionSeeder::class);
    }

    private function seedSalesChannels(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'sales_channels')) {
            return;
        }

        foreach ([['Point of Sale', 'pos'], ['Phone Order', 'phone'], ['Manual Sale', 'manual']] as [$name, $type]) {
            $this->upsertRow(self::CONNECTION, 'sales_channels', ['company_id' => $companyId, 'name' => $name], [
                'type' => $type,
                'status' => 'active',
            ]);
        }
    }

    private function seedServiceTypes(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'service_types')) {
            return;
        }

        foreach ([['Dine In', 'dine_in'], ['Takeaway', 'takeaway'], ['Delivery', 'delivery'], ['Pickup', 'pickup']] as [$name, $type]) {
            $this->upsertRow(self::CONNECTION, 'service_types', ['company_id' => $companyId, 'name' => $name], [
                'type' => $type,
                'status' => 'active',
            ]);
        }
    }

    private function seedUnits(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'units_of_measure')) {
            return;
        }

        foreach ([
            ['Piece', 'pcs', 'count', '1', true],
            ['Kilogram', 'kg', 'weight', '1', true],
            ['Gram', 'g', 'weight', '0.001', false],
            ['Litre', 'l', 'volume', '1', true],
            ['Millilitre', 'ml', 'volume', '0.001', false],
            ['Minute', 'min', 'time', '1', true],
        ] as [$name, $symbol, $type, $factor, $base]) {
            $this->upsertRow(self::CONNECTION, 'units_of_measure', [
                'company_id' => $companyId,
                'name' => $name,
            ], [
                'symbol' => $symbol,
                'unit_type' => $type,
                'base_factor' => $factor,
                'is_base_unit' => $base,
                'status' => 'active',
            ]);
        }
    }

    private function seedTaxCodes(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'tax_codes')) {
            return;
        }

        foreach ([
            ['VAT 18%', '18.0000', false],
            ['Zero Rated', '0.0000', false],
            ['Tax Inclusive VAT 18%', '18.0000', true],
        ] as [$name, $rate, $inclusive]) {
            $this->upsertRow(self::CONNECTION, 'tax_codes', [
                'company_id' => $companyId,
                'name' => $name,
            ], [
                'rate_percentage' => $rate,
                'is_inclusive' => $inclusive,
                'status' => 'active',
                'effective_from' => null,
                'effective_to' => null,
            ]);
        }
    }

    private function seedPriceList(int $companyId, string $currency): void
    {
        if (! $this->tableExists(self::CONNECTION, 'price_lists')) {
            return;
        }

        $this->upsertRow(self::CONNECTION, 'price_lists', [
            'company_id' => $companyId,
            'branch_id' => null,
            'name' => 'Default Price List',
        ], [
            'currency_code' => $currency,
            'is_default' => true,
            'effective_from' => null,
            'effective_to' => null,
            'status' => 'active',
        ]);
    }

    private function seedPaymentMethods(int $companyId): void
    {
        if (! $this->tableExists(self::CONNECTION, 'payment_methods')) {
            return;
        }

        foreach ([
            ['Cash', 'cash', false, true],
            ['Mobile Money', 'mobile_money', false, false],
            ['Bank Transfer', 'bank_transfer', false, false],
        ] as [$name, $type, $provider, $change]) {
            $this->upsertRow(self::CONNECTION, 'payment_methods', [
                'company_id' => $companyId,
                'name' => $name,
            ], [
                'method_type' => $type,
                'requires_provider' => $provider,
                'allows_change' => $change,
                'status' => 'active',
            ]);
        }

        $this->connection(self::CONNECTION)
            ->table('payment_methods')
            ->where('company_id', $companyId)
            ->whereIn('method_type', ['card', 'aggregator'])
            ->update(['status' => 'inactive', 'updated_at' => now()]);
    }

    private function seedStockMovementTypes(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'stock_movement_types')) {
            return;
        }

        foreach ([
            ['Purchase Receipt', 'in'],
            ['Stock Adjustment In', 'in'],
            ['Stock Adjustment Out', 'out'],
            ['Transfer In', 'in'],
            ['Transfer Out', 'out'],
            ['Stock Count Gain', 'in'],
            ['Stock Count Loss', 'out'],
            ['Waste', 'out'],
        ] as [$name, $direction]) {
            $this->upsertRow(self::CONNECTION, 'stock_movement_types', [
                'name' => $name,
            ], [
                'direction' => $direction,
                'is_system' => true,
                'status' => 'active',
            ]);
        }
    }
}
