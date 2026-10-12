<?php

namespace Tests\Feature;

use App\Support\ApiMessages;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiValidationResponseTest extends TestCase
{
    public function test_exception_response_exposes_the_specific_action_required(): void
    {
        Route::post('api/testing/validation-response', function (): void {
            throw ValidationException::withMessages([
                'shift' => ['Open a shift before collecting payment.'],
                'payment' => ['Select a payment method.'],
            ]);
        });

        $this->postJson('/api/testing/validation-response')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Open a shift before collecting payment.')
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', null)
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors.shift.0', 'Open a shift before collecting payment.')
            ->assertJsonPath('errors.payment.0', 'Select a payment method.');
    }

    public function test_explicit_messages_are_preserved_and_empty_errors_use_the_fallback(): void
    {
        $errors = ['name' => ['Enter a name.']];
        $custom = ApiResponse::validation($errors, 'Complete your profile.')->getData(true);
        $this->assertSame('Complete your profile.', $custom['message']);
        $this->assertSame($errors, $custom['errors']);
        $this->assertSame(ApiMessages::VALIDATION_FAILED, ApiResponse::validation([])->getData(true)['message']);
        $this->assertSame('Enter a name.', ApiResponse::badRequest($errors)->getData(true)['message']);
        $this->assertSame(400, ApiResponse::badRequest($errors)->getStatusCode());
    }
}
