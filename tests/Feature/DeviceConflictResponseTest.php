<?php

namespace Tests\Feature;

use App\Exceptions\Tenant\Auth\DeviceConflictException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeviceConflictResponseTest extends TestCase
{
    public function test_device_conflict_has_a_stable_code_and_conflict_status(): void
    {
        Route::get('/api/testing/device-conflict', function (): never {
            throw new DeviceConflictException;
        });

        $this->getJson('/api/testing/device-conflict')
            ->assertConflict()
            ->assertExactJson([
                'success' => false,
                'message' => 'This account is already active on another device. Please logout the other device first.',
                'code' => DeviceConflictException::ERROR_CODE,
                'data' => null,
                'errors' => null,
            ]);
    }
}
