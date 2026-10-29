<?php

namespace Tests\Unit;

use App\Support\ThaiDateFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ThaiDateFormatterTest extends TestCase
{
    public function test_formats_dates_with_buddhist_era_year(): void
    {
        $this->assertSame('22/10/2569', ThaiDateFormatter::formatDate('2026-10-22'));
        $this->assertSame('22/10/2569 13:45', ThaiDateFormatter::formatDateTime('2026-10-22 13:45:00'));
        $this->assertSame('22/10/2569 13:45:00', ThaiDateFormatter::formatDateTime('2026-10-22 13:45:00', null, true));
    }

    public function test_applies_the_requested_timezone_to_timestamp_values(): void
    {
        $timestamp = CarbonImmutable::parse('2026-10-21T17:00:00Z');

        $this->assertSame('22/10/2569 00:00', ThaiDateFormatter::formatDateTime($timestamp, 'Asia/Bangkok'));
        $this->assertSame('21/10/2026 17:00:00', $timestamp->format('d/m/Y H:i:s'));
    }

    public function test_returns_an_empty_string_for_missing_or_invalid_values(): void
    {
        $this->assertSame('', ThaiDateFormatter::formatDate(null));
        $this->assertSame('', ThaiDateFormatter::formatDate('not a date'));
    }
}
