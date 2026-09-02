<?php

namespace Tests\Unit;

use App\Models\Landlord\User;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class HasPublicUuidTest extends TestCase
{
    public function test_it_generates_uuid_v7_for_the_secondary_public_key(): void
    {
        $user = new User;

        $user->setUniqueIds();

        $this->assertSame(['uuid'], $user->uniqueIds());
        $this->assertSame(7, Uuid::fromString($user->uuid)->getVersion());
        $this->assertSame('uuid', $user->getRouteKeyName());
        $this->assertTrue($user->getIncrementing());
        $this->assertSame('int', $user->getKeyType());
    }
}
