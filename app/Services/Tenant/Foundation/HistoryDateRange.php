<?php

namespace App\Services\Tenant\Foundation;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class HistoryDateRange
{
    /** @return array{period: string, from: string, to: string} */
    public function resolve(string $timezone, array $filters): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $period = $filters['period'] ?? 'today';

        [$from, $to] = match ($period) {
            'custom' => [$filters['from'], $filters['to']],
            'this_week' => [$today->startOfWeek(CarbonInterface::MONDAY)->toDateString(), $today->endOfWeek(CarbonInterface::SUNDAY)->toDateString()],
            'this_month' => [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()],
            default => [$today->toDateString(), $today->toDateString()],
        };

        return ['period' => $period, 'from' => $from, 'to' => $to];
    }
}
