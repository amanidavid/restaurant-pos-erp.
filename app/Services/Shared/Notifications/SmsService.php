<?php

namespace App\Services\Shared\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    public function send(string $phone, string $message): void
    {
        if (! (bool) config('services.sms.enabled')) {
            Log::info('SMS is disabled. OTP SMS logged instead.', [
                'phone' => $phone,
                'message' => $message,
            ]);

            return;
        }

        $endpoint = config('services.sms.endpoint');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            Log::info('SMS provider is not configured. OTP SMS logged instead.', [
                'phone' => $phone,
                'message' => $message,
            ]);

            return;
        }

        $response = Http::timeout((int) config('services.sms.timeout', 10))
            ->withOptions([
                'verify' => (bool) config('services.sms.verify_ssl', true),
            ])
            ->withHeaders([
                'X-API-Key' => (string) config('services.sms.api_key'),
                'X-Secret-Key' => (string) config('services.sms.secret_key'),
            ])
            ->post($endpoint, [
                'from' => config('services.sms.sender_id'),
                'to' => $phone,
                'text' => $message,
                'driver' => config('services.sms.driver'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('SMS provider could not send the password reset code.');
        }
    }
}
