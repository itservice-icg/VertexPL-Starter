<?php

namespace Tests\Unit;

use App\Http\Requests\SaveNcrReportRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SaveNcrReportRequestTimezoneTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_found_date_uses_the_configured_display_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.display_timezone' => 'Asia/Bangkok']);
        Carbon::setTestNow('2026-11-15 18:00:00');

        $rules = (new SaveNcrReportRequest())->rules();

        $todayInBangkok = Validator::make(
            ['found_date' => '2026-11-16'],
            ['found_date' => $rules['found_date']]
        );
        $tomorrowInBangkok = Validator::make(
            ['found_date' => '2026-11-17'],
            ['found_date' => $rules['found_date']]
        );

        $this->assertFalse($todayInBangkok->fails());
        $this->assertTrue($tomorrowInBangkok->fails());
    }
}
