<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class JordanBusinessDays
{
    private const TIMEZONE = 'Asia/Amman';

    /**
     * @var array<int, int>
     */
    private const WEEKEND_DAYS = [
        CarbonInterface::FRIDAY,
        CarbonInterface::SATURDAY,
    ];

    public static function today(): Carbon
    {
        return now(self::TIMEZONE)->startOfDay();
    }

    public static function parse(string $date): Carbon
    {
        return Carbon::parse($date, self::TIMEZONE)->startOfDay();
    }

    public static function addBusinessDays(CarbonInterface|string $date, int $days): Carbon
    {
        $current = $date instanceof CarbonInterface
            ? Carbon::instance($date)->timezone(self::TIMEZONE)->startOfDay()
            : self::parse($date);

        $remaining = max(0, $days);

        while ($remaining > 0) {
            $current = $current->copy()->addDay();

            if (! self::isWeekend($current)) {
                $remaining--;
            }
        }

        return $current;
    }

    public static function isWeekend(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeek, self::WEEKEND_DAYS, true);
    }
}
