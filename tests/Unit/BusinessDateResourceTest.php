<?php

namespace Tests\Unit;

use App\Http\Resources\Web\Foundation\BusinessDateResource;
use App\Models\Tenant\Branch;
use App\Models\Tenant\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDateResourceTest extends TestCase
{
    #[Test]
    public function it_adds_human_readable_times_in_the_branch_timezone(): void
    {
        $branch = (new Branch)->forceFill(['timezone' => 'Africa/Nairobi']);
        $businessDate = (new BusinessDate)->forceFill([
            'planned_close_at' => CarbonImmutable::parse('2026-09-07T23:59:00+00:00'),
            'opened_at' => CarbonImmutable::parse('2026-09-07T07:49:33+00:00'),
        ]);
        $businessDate->setRelation('branch', $branch);

        $resource = (new BusinessDateResource($businessDate))->resolve(Request::create('/'));

        $this->assertSame('08 Sep 2026, 02:59 AM', $resource['planned_close_at_display']);
        $this->assertSame('07 Sep 2026, 10:49 AM', $resource['opened_at_display']);
    }
}
