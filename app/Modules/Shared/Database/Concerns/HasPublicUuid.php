<?php

namespace App\Modules\Shared\Database\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueIds;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

trait HasPublicUuid
{
    use HasUniqueIds;

    public function initializeHasPublicUuid(): void
    {
        $this->usesUniqueIds = true;
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $routeField = $field ?? $this->getRouteKeyName();

        if ($routeField === 'uuid' && ! Str::isUuid($value)) {
            throw (new ModelNotFoundException)->setModel(static::class, [$value]);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
