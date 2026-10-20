<?php

namespace Tests\Feature;

use App\Models\NcrCorrectiveAction;
use App\Models\NcrReport;
use Tests\TestCase;

class NcrOverdueTest extends TestCase
{
    public function test_overdue_uses_calendar_dates_and_preserves_terminal_status_rules(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        foreach ([null, today()->subDay()->toDateString(), today()->toDateString(), today()->addDay()->toDateString()] as $date) {
            $expected = $date !== null && $date < today()->toDateString();
            foreach (['ACTION_IN_PROGRESS', 'CLOSED'] as $status) {
                $report = new NcrReport();
                $report->setRawAttributes(['due_date' => $date, 'current_status' => $status]);
                $this->assertSame($expected && $status !== 'CLOSED', $report->is_overdue);
            }
            foreach (['OPEN', 'IN_PROGRESS', 'COMPLETED', 'VERIFIED', 'CANCELLED'] as $status) {
                $action = new NcrCorrectiveAction();
                $action->setRawAttributes(['due_date' => $date, 'action_status' => $status]);
                $this->assertSame($expected && in_array($status, ['OPEN', 'IN_PROGRESS'], true), $action->is_overdue);
            }
        }
    }
}
