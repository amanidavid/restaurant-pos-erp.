<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\Branch;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TenantLoginService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function login(array $data, bool $replaceActiveSession = false): array
    {
        $user = $this->findUser((string) $data['login']);

        if (! $user || ! Hash::check((string) $data['password'], (string) $user->password)) {
            throw new UnauthorizedHttpException('', 'Invalid login details.');
        }

        if ($user->status !== 'active') {
            throw new AccessDeniedHttpException('This user account is not active.');
        }

        $tenantUser = $user->tenantUser;

        if (! $tenantUser || $tenantUser->status !== 'active') {
            throw new AccessDeniedHttpException('This tenant user profile is not active.');
        }

        $branches = $this->loadActiveBranches($tenantUser);

        if ($branches->isEmpty()) {
            throw new AccessDeniedHttpException('This user has no active branch access.');
        }

        $activeBranch = $this->resolveActiveBranch($branches, $data['branch_uuid'] ?? null);

        return DB::connection('tenant')->transaction(function () use (
            $user,
            $tenantUser,
            $branches,
            $activeBranch,
            $data,
            $replaceActiveSession
        ): array {
            DB::connection('tenant')
                ->table('tenant_users')
                ->where('id', $tenantUser->id)
                ->lockForUpdate()
                ->first();

            DB::connection('tenant')
                ->table('tenant_user_device_sessions')
                ->where('tenant_user_id', $tenantUser->id)
                ->where('is_active', true)
                ->where('expires_at', '<=', Carbon::now())
                ->update([
                    'is_active' => false,
                    'logged_out_at' => Carbon::now(),
                    'logout_reason' => 'expired',
                    'updated_at' => Carbon::now(),
                ]);

            $activeSession = DB::connection('tenant')
                ->table('tenant_user_device_sessions')
                ->where('tenant_user_id', $tenantUser->id)
                ->where('is_active', true)
                ->whereNull('logged_out_at')
                ->where('expires_at', '>', Carbon::now())
                ->lockForUpdate()
                ->first();

            if ($activeSession && ! $replaceActiveSession) {
                throw new BadRequestHttpException('This account is already active on another device. Please logout the other device first.');
            }

            if ($activeSession && $replaceActiveSession) {
                DB::connection('tenant')
                    ->table('tenant_user_device_sessions')
                    ->where('id', $activeSession->id)
                    ->update([
                        'is_active' => false,
                        'force_logout_requested' => true,
                        'logged_out_at' => Carbon::now(),
                        'logout_reason' => 'replaced_by_new_login',
                        'updated_at' => Carbon::now(),
                    ]);

                $user->forceFill([
                    'token_version' => $user->token_version + 1,
                ])->save();
            }

            $token = JWTAuth::fromUser($user);

            DB::connection('tenant')->table('tenant_user_device_sessions')->insert([
                'uuid' => (string) Str::uuid7(),
                'tenant_user_id' => $tenantUser->id,
                'branch_id' => $activeBranch->id,
                'branch_device_id' => null,
                'session_jti_hash' => hash('sha256', $token),
                'refresh_token_hash' => null,
                'login_method' => 'password',
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
                'is_active' => true,
                'force_logout_requested' => false,
                'logged_in_at' => Carbon::now(),
                'last_seen_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes((int) config('jwt.ttl', 60)),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $user->forceFill([
                'last_login_at' => Carbon::now(),
            ])->save();

            $tenantUser->forceFill([
                'last_accessed_at' => Carbon::now(),
            ])->save();

            return [
                'access_token' => $token,
                'expires_in' => (int) config('jwt.ttl', 60) * 60,
                'user' => $user->refresh(),
                'tenant_user' => $tenantUser->refresh(),
                'branches' => $branches,
                'active_branch' => $activeBranch,
            ];
        });
    }

    private function findUser(string $login): ?User
    {
        if (Str::contains($login, '@')) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower(trim($login))])
                ->with('tenantUser')
                ->first();
        }

        return User::query()
            ->where('phone', $this->normalizeTanzaniaPhone($login))
            ->with('tenantUser')
            ->first();
    }

    /**
     * @return Collection<int, Branch>
     */
    private function loadActiveBranches(TenantUser $tenantUser): Collection
    {
        return Branch::query()
            ->select('branches.*', 'tenant_user_branches.is_primary as pivot_is_primary')
            ->join('tenant_user_branches', 'tenant_user_branches.branch_id', '=', 'branches.id')
            ->where('tenant_user_branches.tenant_user_id', $tenantUser->id)
            ->where('tenant_user_branches.status', 'active')
            ->where('branches.status', 'active')
            ->orderByDesc('tenant_user_branches.is_primary')
            ->orderBy('branches.name')
            ->get();
    }

    /**
     * @param  Collection<int, Branch>  $branches
     */
    private function resolveActiveBranch(Collection $branches, mixed $branchUuid): Branch
    {
        if (! is_string($branchUuid) || trim($branchUuid) === '') {
            return $branches->first();
        }

        $branch = $branches->firstWhere('uuid', $branchUuid);

        if (! $branch) {
            throw new AccessDeniedHttpException('You do not have access to the selected branch.');
        }

        return $branch;
    }

    private function normalizeTanzaniaPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        if (preg_match('/^[67]\d{8}$/', $digits) === 1) {
            $digits = '255'.$digits;
        }

        return '+'.$digits;
    }
}
