<?php

namespace App\Http\Middleware\Tenant;

use App\Models\Tenant\Branch;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    /**
     * Resolve the branch requested by a branch-scoped API endpoint.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branchUuid = trim((string) $request->header('X-Branch-UUID'));

        if ($branchUuid === '') {
            return ApiResponse::error(
                message: 'The X-Branch-UUID header is required.',
                errors: ['X-Branch-UUID' => ['Select the branch for this request.']],
                status: 400,
                code: 'BRANCH_CONTEXT_REQUIRED',
            );
        }

        if (! Str::isUuid($branchUuid)) {
            return ApiResponse::error(
                message: 'The X-Branch-UUID header must be a valid UUID.',
                errors: ['X-Branch-UUID' => ['Provide a valid branch UUID.']],
                status: 400,
                code: 'INVALID_BRANCH_CONTEXT',
            );
        }

        $tenantUser = auth('tenant_api')->user()?->tenantUser;

        if (! $tenantUser) {
            return ApiResponse::unauthorized(message: 'Your tenant session is invalid.');
        }

        $branchId = Branch::query()
            ->join('tenant_user_branches', 'tenant_user_branches.branch_id', '=', 'branches.id')
            ->where('tenant_user_branches.tenant_user_id', $tenantUser->id)
            ->where('tenant_user_branches.status', 'active')
            ->where('branches.uuid', $branchUuid)
            ->where('branches.status', 'active')
            ->value('branches.id');

        if (! $branchId) {
            return ApiResponse::forbidden(
                message: 'You do not have access to the selected branch.',
                errors: ['X-Branch-UUID' => ['The selected branch is unavailable.']],
            );
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $branchId);
        app()->instance('currentBranchId', (int) $branchId);

        return $next($request);
    }
}
