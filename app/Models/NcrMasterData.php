<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @property string $code
 * @property string $name_th
 */
abstract class NcrMasterData extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'require_attachment' => 'boolean', 'require_approval' => 'boolean', 'channels' => 'array'];

    public function isInUse(): bool
    {
        return $this->usageCount() > 0;
    }

    public function usageCount(): int
    {
        $references = [
            ProblemType::class => [['ncr_reports', 'problem_type_id']],
            SeverityLevel::class => [['ncr_reports', 'severity_level_id']],
            DispositionType::class => [['ncr_dispositions', 'disposition_type_id']],
            RootCauseType::class => [['ncr_corrective_actions', 'root_cause_type_id']],
            Product::class => [['ncr_items', 'product_id']],
            Customer::class => [['ncr_items', 'customer_id']],
            Supplier::class => [['ncr_reports', 'supplier_id']],
        ];

        $count = 0;
        foreach ($references[static::class] ?? [] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $count += DB::table($table)->where($column, $this->getKey())->count();
            }
        }

        return $count;
    }
}
