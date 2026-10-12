<?php

use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use App\Services\Tenant\Access\AccessAuthorizationService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.branch.{branchUuid}.pos', function (User $user, string $branchUuid): bool {
    $branch = Branch::query()->where('uuid', $branchUuid)->first();

    return $branch !== null
        && $branch->id === app('currentBranchId')
        && app(AccessAuthorizationService::class)->allows($user, 'orders.view', $branch->id);
});

Broadcast::channel('tenant.branch.{branchUuid}.reservations', function (User $user, string $branchUuid): bool {
    $branch = Branch::query()->where('uuid', $branchUuid)->first();

    return $branch !== null
        && $branch->id === app('currentBranchId')
        && app(AccessAuthorizationService::class)->allows($user, 'reservations.view', $branch->id);
});

Broadcast::channel('tenant.branch.{branchUuid}.kitchen-section.{sectionUuid}', function (User $user, string $branchUuid, string $sectionUuid): bool {
    $branch = Branch::query()->where('uuid', $branchUuid)->first();

    return $branch !== null
        && $branch->id === app('currentBranchId')
        && app(AccessAuthorizationService::class)->allows($user, 'kitchen.view', $branch->id);
});
