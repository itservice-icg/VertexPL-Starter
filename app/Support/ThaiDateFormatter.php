<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ThaiDateFormatter
{
    public static function formatDate($value, ?string $timezone = null): string
    {
        $date = self::asDate($value, $timezone);

        return $date ? $date->format('d/m/').sprintf('%04d', $date->year + 543) : '';
    }

    public static function formatDateTime($value, ?string $timezone = null, bool $withSeconds = false): string
    {
        $date = self::asDate($value, $timezone);

        if (! $date) {
            return '';
        }

        return self::formatDate($date).' '.$date->format($withSeconds ? 'H:i:s' : 'H:i');
    }

    private static function asDate($value, ?string $timezone): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : (is_string($value) ? CarbonImmutable::parse($value, $timezone ?: config('app.display_timezone')) : null);

            return $date && $timezone ? $date->setTimezone($timezone) : $date;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
