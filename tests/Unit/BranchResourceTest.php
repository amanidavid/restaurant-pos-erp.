<?php

namespace Tests\Unit;

use App\Http\Resources\Web\Foundation\BranchResource;
use App\Models\Tenant\Branch;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BranchResourceTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_a_loaded_active_configuration_does_not_exist(): void
    {
        $branch = (new Branch)->forceFill([
            'uuid' => '019c0000-0000-7000-8000-000000000001',
            'name' => 'Main Branch',
            'status' => 'active',
        ]);
        $branch->setRelation('activeConfiguration', null);

        $resource = (new BranchResource($branch))->resolve(Request::create('/'));

        $this->assertSame('019c0000-0000-7000-8000-000000000001', $resource['uuid']);
        $this->assertNull($resource['configuration']);
    }
}
