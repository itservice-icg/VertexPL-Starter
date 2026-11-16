<?php

namespace Tests\Unit;

use App\Http\Requests\SaveNcrReportRequest;
use App\Models\NcrItem;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NcrItemUnitPercentageTest extends TestCase
{
    public function test_validation_rejects_different_units(): void
    {
        $requestRules = (new SaveNcrReportRequest())->rules();
        $requestMessages = (new SaveNcrReportRequest())->messages();
        $unitRules = array_values(array_filter(
            $requestRules['items.0.total_unit_name'],
            static fn ($rule) => is_string($rule)
                && ($rule === 'nullable' || str_starts_with($rule, 'same:') || str_starts_with($rule, 'required_with:'))
        ));

        $mismatched = Validator::make(
            ['items' => [['defect_unit_name' => 'KG', 'total_unit_name' => 'PCS']]],
            ['items.0.total_unit_name' => $unitRules],
            $requestMessages
        );
        $matching = Validator::make(
            ['items' => [['defect_unit_name' => 'KG', 'total_unit_name' => 'KG']]],
            ['items.0.total_unit_name' => $unitRules],
            $requestMessages
        );
        $missingCounterpart = Validator::make(
            ['items' => [['defect_unit_name' => 'KG', 'total_unit_name' => '']]],
            ['items.0.total_unit_name' => $unitRules],
            $requestMessages
        );

        $this->assertTrue($mismatched->fails());
        $this->assertSame('หน่วยนับของจำนวนที่พบและจำนวนทั้งหมดต้องตรงกัน', $mismatched->errors()->first('items.0.total_unit_name'));
        $this->assertFalse($matching->fails());
        $this->assertTrue($missingCounterpart->fails());
        $this->assertSame('กรุณาเลือกหน่วยนับของจำนวนที่พบและจำนวนทั้งหมดให้ครบทั้งสองช่อง', $missingCounterpart->errors()->first('items.0.total_unit_name'));
    }

    public function test_percentage_is_unavailable_when_units_differ(): void
    {
        $item = new NcrItem([
            'defect_quantity' => 2,
            'defect_unit_name' => 'KG',
            'total_quantity' => 10,
            'total_unit_name' => 'PCS',
        ]);

        $this->assertNull($item->defect_percentage);
    }
}
