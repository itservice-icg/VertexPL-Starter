<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NcrItem extends Model
{
    protected $guarded = ['id'];

    public function report() { return $this->belongsTo(NcrReport::class, 'ncr_report_id'); }
    public function productReference() { return $this->belongsTo(Product::class, 'product_id'); }
    public function customerReference() { return $this->belongsTo(Customer::class, 'customer_id'); }

    public function getDefectPercentageAttribute(): ?float
    {
        $defectUnit = $this->defect_unit_name ?: $this->unit_name;
        $totalUnit = $this->total_unit_name ?: $this->unit_name;

        if ($defectUnit !== $totalUnit) {
            return null;
        }

        return (float) $this->total_quantity > 0
            ? ((float) $this->defect_quantity / (float) $this->total_quantity) * 100
            : null;
    }
}
