<?php

namespace App\Http\Requests\Web\Foundation\Concerns;

trait NormalizesBusinessDayTimes
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalizeBusinessDayTimes(array $values): array
    {
        foreach (['business_day_opens_at', 'business_day_closes_at'] as $field) {
            if (! isset($values[$field]) || ! is_string($values[$field])) {
                continue;
            }

            $value = trim($values[$field]);

            // Configuration is minute-based. Accept the :00 form returned by time columns
            // so a client can submit a previously returned configuration unchanged.
            $values[$field] = preg_match('/^\d{2}:\d{2}:00$/', $value) === 1
                ? substr($value, 0, 5)
                : $value;
        }

        return $values;
    }
}
