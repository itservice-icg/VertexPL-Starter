<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\NcrReport;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NcrMasterDataUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_usage_count_ignores_item_unit_snapshot_without_unit_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('ncr_items', 'unit_id'));

        $user = User::factory()->create();
        $department = Department::create([
            'code' => 'QA',
            'name_th' => 'Quality Assurance',
            'is_active' => true,
        ]);
        $unit = Unit::create([
            'code' => 'PCS',
            'name_th' => 'ชิ้น',
            'is_active' => true,
        ]);
        $report = NcrReport::create([
            'ncr_no' => 'NCR-2026-00001',
            'report_date' => '2026-08-20',
            'found_date' => '2026-08-20',
            'reporter_id' => $user->id,
            'reporter_department_id' => $department->id,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'unit_name' => 'PCS',
            'problem_description' => 'Unit is stored as a snapshot on the NCR.',
            'current_status' => 'DRAFT',
            'version_no' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $report->items()->create([
            'line_no' => 1,
            'defect_quantity' => 1,
            'total_quantity' => 10,
            'unit_name' => 'PCS',
            'created_by' => $user->id,
        ]);

        $this->assertSame(0, $unit->usageCount());
        $this->assertFalse($unit->isInUse());
    }
}
