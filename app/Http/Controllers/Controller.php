<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    protected function currentBranchId(): int
    {
        return (int) app('currentBranchId');
    }

    protected function currentBranch(): Branch
    {
        return Branch::query()
            ->select(['id', 'uuid', 'company_id', 'name', 'timezone'])
            ->findOrFail($this->currentBranchId());
    }

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }

    protected function searchByName(Builder $query, Request $request): Builder
    {
        return $query->where('name', 'like', trim((string) $request->input('search')).'%');
    }
}
