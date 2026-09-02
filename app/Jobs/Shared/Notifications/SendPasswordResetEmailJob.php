<?php

namespace App\Jobs\Shared\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class SendPasswordResetEmailJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $otp,
        public readonly int $expiresInMinutes,
    ) {}

    public function handle(): void
    {
        Mail::raw($this->messageBody(), function ($message): void {
            $message
                ->to($this->email, $this->displayName)
                ->subject('Password reset code');
        });
    }

    private function messageBody(): string
    {
        return "Hello {$this->displayName},\n\n"
            ."Your password reset code is {$this->otp}.\n"
            ."This code expires in {$this->expiresInMinutes} minutes.\n\n"
            .'If you did not request this code, please ignore this message.';
    }
}
