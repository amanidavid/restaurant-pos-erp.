<?php

namespace Database\Seeders\Landlord;

use Database\Seeders\Support\SeederHelpers;
use Illuminate\Database\Seeder;

class DatabaseClusterSeeder extends Seeder
{
    use SeederHelpers;

    private const CONNECTION = 'landlord';

    public function run(): void
    {
        if (! $this->tableExists(self::CONNECTION, 'database_clusters')) {
            return;
        }

        $this->upsertRow(self::CONNECTION, 'database_clusters', [
            'code' => 'default',
        ], [
            'name' => 'Default PostgreSQL Cluster',
            'host' => config('database.connections.landlord.host', '127.0.0.1'),
            'port' => (int) config('database.connections.landlord.port', 5432),
            'region' => null,
            'ssl_mode' => config('database.connections.landlord.sslmode', 'prefer'),
            'secret_namespace' => null,
            'status' => 'active',
            'last_health_check_at' => null,
        ]);
    }
}
