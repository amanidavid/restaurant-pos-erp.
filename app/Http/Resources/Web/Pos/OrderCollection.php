<?php

namespace App\Http\Resources\Web\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    public $collects = OrderResource::class;

    /** @param array<string, int> $statusCounts */
    public function __construct(mixed $resource, private readonly array $statusCounts)
    {
        parent::__construct($resource);
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        $default['meta']['status_counts'] = $this->statusCounts;

        return $default;
    }
}
