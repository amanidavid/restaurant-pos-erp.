<?php

namespace Database\Seeders\Tenant;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class TenantShiftDefinitionSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'tenant';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'shift_definitions')) {
            $this->command?->warn('TenantShiftDefinitionSeeder skipped: shift definitions table is unavailable.');

            return;
        }

        $branches = $this->connection(self::CONNECTION)->table('branches')
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'company_id']);

        foreach ($branches as $branch) {
            foreach ($this->restaurantShifts() as $shift) {
                $exists = $this->connection(self::CONNECTION)->table('shift_definitions')
                    ->where('company_id', $branch->company_id)
                    ->where('branch_id', $branch->id)
                    ->where('name', $shift['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $this->upsertRow(self::CONNECTION, 'shift_definitions', [
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'name' => $shift['name'],
                ], $shift);
            }
        }
    }

    /** @return array<int, array{name: string, starts_at: string, ends_at: string, grace_minutes: int, crosses_midnight: bool, status: string}> */
    private function restaurantShifts(): array
    {
        return [
            ['name' => 'Breakfast Shift', 'starts_at' => '06:00', 'ends_at' => '14:00', 'grace_minutes' => 15, 'crosses_midnight' => false, 'status' => 'active'],
            ['name' => 'Day Shift', 'starts_at' => '08:00', 'ends_at' => '17:00', 'grace_minutes' => 15, 'crosses_midnight' => false, 'status' => 'active'],
            ['name' => 'Evening Shift', 'starts_at' => '14:00', 'ends_at' => '23:00', 'grace_minutes' => 15, 'crosses_midnight' => false, 'status' => 'active'],
            ['name' => 'Night Shift', 'starts_at' => '22:00', 'ends_at' => '06:00', 'grace_minutes' => 15, 'crosses_midnight' => true, 'status' => 'active'],
        ];
    }
}
