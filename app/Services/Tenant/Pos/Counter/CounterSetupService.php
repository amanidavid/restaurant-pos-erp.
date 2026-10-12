<?php

namespace App\Services\Tenant\Pos\Counter;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Counter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CounterSetupService
{
    /**
     * @param array<int, array{name: string, type: string, status?: string}> $counters
     * @return Collection<int, Counter>
     */
    public function create(Branch $branch, array $counters): Collection
    {
        return DB::connection('tenant')->transaction(function () use ($branch, $counters): Collection {
            Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            $names = collect($counters)->pluck('name');
            if ($names->unique()->count() !== $names->count()) {
                throw ValidationException::withMessages([
                    'counters' => ['Each counter name must appear only once in this request.'],
                ]);
            }

            if (Counter::query()->where('branch_id', $branch->id)->whereIn('name', $names->all())->exists()) {
                throw ValidationException::withMessages([
                    'counters' => ['A counter with one of these names already exists for this branch.'],
                ]);
            }

            try {
                $now = now();
                Counter::query()->insert(collect($counters)->map(fn (array $counter): array => [
                    'uuid' => (string) Str::uuid7(),
                    'branch_id' => $branch->id,
                    'name' => $counter['name'],
                    'type' => $counter['type'],
                    'status' => $counter['status'] ?? 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            } catch (QueryException $exception) {
                if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                    throw ValidationException::withMessages([
                        'counters' => ['A counter with one of these names already exists for this branch.'],
                    ]);
                }

                throw $exception;
            }

            return Counter::query()
                ->where('branch_id', $branch->id)
                ->whereIn('name', $names->all())
                ->orderBy('id')
                ->get();
        });
    }

    public function update(Counter $counter, Branch $branch, array $data): Counter
    {
        return DB::connection('tenant')->transaction(function () use ($counter, $branch, $data): Counter {
            $counter = Counter::query()
                ->where('branch_id', $branch->id)
                ->whereKey($counter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('name', $data)) {
                $exists = Counter::query()
                    ->where('branch_id', $branch->id)
                    ->where('name', $data['name'])
                    ->where('id', '!=', $counter->id)
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'name' => ['A counter with this name already exists for this branch.'],
                    ]);
                }
            }

            $counter->update($data);

            return $counter->refresh();
        });
    }
}
