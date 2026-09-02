<?php

namespace Database\Seeders\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeederHelpers
{
    protected function connection(string $name): ConnectionInterface
    {
        return DB::connection($name);
    }

    protected function uuid(): string
    {
        return (string) (method_exists(Str::class, 'uuid7') ? Str::uuid7() : Str::uuid());
    }

    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    protected function upsertRow(string $connection, string $table, array $unique, array $values): int
    {
        $db = $this->connection($connection);
        $query = $db->table($table);

        foreach ($unique as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first(['id']);
        $now = Carbon::now();

        if ($existing) {
            $db->table($table)->where('id', $existing->id)->update(array_merge(
                $values,
                ['updated_at' => $now],
            ));

            return (int) $existing->id;
        }

        return (int) $db->table($table)->insertGetId(array_merge(
            ['uuid' => $this->uuid()],
            $unique,
            $values,
            ['created_at' => $now, 'updated_at' => $now],
        ));
    }

    protected function tableExists(string $connection, string $table): bool
    {
        return $this->connection($connection)->getSchemaBuilder()->hasTable($table);
    }
}
