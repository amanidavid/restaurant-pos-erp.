<?php

namespace Tests\Unit;

use App\Support\TanzaniaPhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TanzaniaPhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'local leading zero' => ['0712 345 678', '+255712345678'],
            'local without zero' => ['712345678', '+255712345678'],
            'country code' => ['255712345678', '+255712345678'],
            'international' => ['+255 712 345 678', '+255712345678'],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_it_normalizes_tanzanian_phone_numbers(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, TanzaniaPhoneNumber::normalize($input));
    }
}
