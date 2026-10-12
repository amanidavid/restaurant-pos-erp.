<?php

namespace Tests\Unit\Services\Tenant\Organisation;

use App\Models\Tenant\Department;
use App\Services\Tenant\Organisation\DepartmentService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DepartmentServiceTest extends TestCase
{
    #[Test]
    public function tree_nodes_preserve_the_parent_uuid_and_nest_children(): void
    {
        $operations = $this->department(['id' => 10, 'uuid' => 'operations-uuid', 'name' => 'Operations', 'status' => 'active']);
        $kitchen = $this->department(['id' => 11, 'uuid' => 'kitchen-uuid', 'parent_id' => 10, 'name' => 'Kitchen', 'status' => 'active']);

        $method = new ReflectionMethod(DepartmentService::class, 'treeNodes');
        $tree = $method->invoke(
            new DepartmentService,
            [0 => [$operations], 10 => [$kitchen]],
            [10 => 'operations-uuid', 11 => 'kitchen-uuid'],
            0,
            [],
        );

        $this->assertSame('operations-uuid', $tree[0]['uuid']);
        $this->assertNull($tree[0]['parent_uuid']);
        $this->assertSame('kitchen-uuid', $tree[0]['children'][0]['uuid']);
        $this->assertSame('operations-uuid', $tree[0]['children'][0]['parent_uuid']);
    }

    /** @param array<string, mixed> $attributes */
    private function department(array $attributes): Department
    {
        $department = $this->getMockBuilder(Department::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();

        $department->method('getAttribute')
            ->willReturnCallback(fn (string $key): mixed => $attributes[$key] ?? null);

        return $department;
    }
}
