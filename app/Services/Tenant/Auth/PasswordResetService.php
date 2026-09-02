<?php

namespace App\Services\Tenant\Auth;

use App\Jobs\Shared\Notifications\SendPasswordResetEmailJob;
use App\Models\Landlord\PasswordResetOtp;
use App\Models\Tenant\User;
use App\Services\Shared\Notifications\SmsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PasswordResetService
{
    private const OTP_EXPIRES_IN_MINUTES = 10;

    public function __construct(
        private readonly SmsService $smsService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function requestOtp(array $data): array
    {
        [$identifierType, $identifier] = $this->normalizeLogin((string) $data['login']);
        $channel = (string) $data['channel'];
        $user = $this->findUser($identifierType, $identifier);

        if (! $user || $user->status !== 'active') {
            return $this->safeRequestResponse($channel);
        }

        if ($channel === 'email' && ! $user->email) {
            return $this->safeRequestResponse($channel);
        }

        if ($channel === 'sms' && ! $user->phone) {
            return $this->safeRequestResponse($channel);
        }

        $otp = (string) random_int(100000, 999999);

        DB::connection('landlord')->transaction(function () use ($user, $identifierType, $identifier, $channel, $otp): void {
            PasswordResetOtp::query()
                ->where('user_id', $user->id)
                ->where('channel', $channel)
                ->whereIn('status', ['pending', 'verified'])
                ->update([
                    'status' => 'expired',
                    'updated_at' => Carbon::now(),
                ]);

            PasswordResetOtp::query()->create([
                'user_id' => $user->id,
                'identifier_type' => $identifierType,
                'identifier_normalized' => $identifier,
                'channel' => $channel,
                'otp_hash' => Hash::make($otp),
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 5,
                'requested_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES),
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
            ]);
        });

        if ($channel === 'email') {
            SendPasswordResetEmailJob::dispatch(
                (string) $user->email,
                (string) $user->display_name,
                $otp,
                self::OTP_EXPIRES_IN_MINUTES,
            );
        } else {
            $this->smsService->send(
                (string) $user->phone,
                "Your RMIS password reset code is {$otp}. It expires in ".self::OTP_EXPIRES_IN_MINUTES.' minutes.',
            );
        }

        return $this->safeRequestResponse($channel);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function verifyOtp(array $data): array
    {
        [$identifierType, $identifier] = $this->normalizeLogin((string) $data['login']);
        $otp = $this->validOtp($identifierType, $identifier, (string) $data['otp']);

        $otp->forceFill([
            'status' => 'verified',
            'verified_at' => Carbon::now(),
        ])->save();

        return [
            'verified' => true,
            'expires_at' => $otp->expires_at->timezone('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resetPassword(array $data): void
    {
        [$identifierType, $identifier] = $this->normalizeLogin((string) $data['login']);
        $otp = $this->validOtp($identifierType, $identifier, (string) $data['otp'], allowVerified: true);

        DB::connection('landlord')->transaction(function () use ($otp, $data): void {
            $user = User::query()
                ->whereKey($otp->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $user->forceFill([
                'password' => Hash::make((string) $data['password']),
                'password_changed_at' => Carbon::now(),
                'token_version' => $user->token_version + 1,
            ])->save();

            DB::connection('tenant')
                ->table('tenant_user_device_sessions')
                ->whereIn('tenant_user_id', function ($query) use ($user): void {
                    $query->select('id')
                        ->from('tenant_users')
                        ->where('landlord_user_uuid', $user->uuid);
                })
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'force_logout_requested' => true,
                    'logged_out_at' => Carbon::now(),
                    'logout_reason' => 'password_changed',
                    'updated_at' => Carbon::now(),
                ]);

            $otp->forceFill([
                'status' => 'used',
                'used_at' => Carbon::now(),
            ])->save();
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeLogin(string $login): array
    {
        $login = trim($login);

        if (Str::contains($login, '@')) {
            return ['email', Str::lower($login)];
        }

        return ['phone', $this->normalizeTanzaniaPhone($login)];
    }

    private function findUser(string $identifierType, string $identifier): ?User
    {
        return User::query()
            ->when(
                $identifierType === 'email',
                fn ($query) => $query->whereRaw('LOWER(email) = ?', [$identifier]),
                fn ($query) => $query->where('phone', $identifier),
            )
            ->first();
    }

    private function validOtp(
        string $identifierType,
        string $identifier,
        string $otp,
        bool $allowVerified = false,
    ): PasswordResetOtp {
        $statuses = $allowVerified ? ['pending', 'verified'] : ['pending'];

        $record = PasswordResetOtp::query()
            ->where('identifier_type', $identifierType)
            ->where('identifier_normalized', $identifier)
            ->whereIn('status', $statuses)
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->first();

        if (! $record) {
            throw new BadRequestHttpException('Invalid or expired password reset code.');
        }

        if ($record->attempts >= $record->max_attempts) {
            $record->forceFill(['status' => 'blocked'])->save();

            throw new BadRequestHttpException('Password reset code is blocked. Please request a new code.');
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            $record->forceFill([
                'attempts' => $record->attempts + 1,
                'status' => $record->attempts + 1 >= $record->max_attempts ? 'blocked' : $record->status,
            ])->save();

            throw new BadRequestHttpException('Invalid or expired password reset code.');
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeRequestResponse(string $channel): array
    {
        return [
            'channel' => $channel,
            'expires_in_minutes' => self::OTP_EXPIRES_IN_MINUTES,
        ];
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
