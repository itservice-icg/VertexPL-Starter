<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\NcrReport;

class SaveNcrReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $unitCodes = DB::table('units')->get(['code', 'symbol'])->reduce(function (array $codes, object $unit): array {
            $codes[$unit->code] = $unit->code;
            if ($unit->symbol && ! array_key_exists($unit->symbol, $codes)) {
                $codes[$unit->symbol] = $unit->code;
            }

            return $codes;
        }, []);
        $normalizeUnit = static fn ($value) => $value === null || $value === '' ? null : ($unitCodes[$value] ?? $value);

        $items = collect((array) $this->input('items', []))->map(function (array $item) use ($normalizeUnit): array {
            $legacyUnit = $normalizeUnit($item['unit_name'] ?? null);
            $defectUnit = $normalizeUnit($item['defect_unit_name'] ?? $legacyUnit);
            $totalUnit = $normalizeUnit($item['total_unit_name'] ?? $legacyUnit);

            $item['defect_unit_name'] = $defectUnit;
            $item['total_unit_name'] = $totalUnit;
            $item['unit_name'] = $defectUnit === $totalUnit ? $defectUnit : null;

            return $item;
        })->values()->all();

        $rawScopeCodes = $this->has('legacy_form_scope_codes')
            ? (array) $this->input('legacy_form_scope_codes')
            : explode(',', (string) $this->input('legacy_form_scope_code', ''));
        $scopeCodes = array_values(array_unique(array_filter(
            $rawScopeCodes,
            static fn ($code) => $code !== null && $code !== ''
        )));

        $this->merge([
            'items' => $items,
            'legacy_form_scope_code' => $scopeCodes ? implode(',', $scopeCodes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'report_date'=>['required','date'],'found_date'=>['required','date','before_or_equal:today'],'found_time'=>['nullable','date_format:H:i'],
            'legacy_form_scope_code'=>['nullable', Rule::in(['AI', 'UP'])],
            'legacy_form_scope_codes'=>['nullable','array','max:1'],
            'legacy_form_scope_codes.*'=>['required', Rule::in(['AI', 'UP']), 'distinct'],
            'reporter_department_id'=>['nullable','exists:departments,id'],'related_department_id'=>['nullable','exists:departments,id'],'problem_type_id'=>['required','exists:problem_types,id'],'severity_level_id'=>['required','exists:severity_levels,id'],
            'company_name'=>['nullable','string','max:255'],
            'generic_name'=>['nullable','string','max:255'],'supplier_id'=>['nullable','integer','exists:suppliers,id'],'supplier_name'=>['nullable','string','max:255'],'production_date'=>['nullable','date'],'found_location'=>['nullable','string','max:255'],
            'machine_or_process'=>['nullable','string','max:255'],'reference_no'=>['nullable','string','max:100'],'problem_description'=>['required','string','max:10000'],
            'initial_disposition_type_id'=>['nullable','exists:disposition_types,id'],
            'initial_disposition_detail'=>['nullable','string','max:5000'],
            'initial_responsible_department_id'=>['nullable','exists:departments,id'],
            'initial_proposed_due_date'=>['nullable','date'],
            'version_no'=>['nullable','integer','min:1'],'attachments.*'=>['file','max:10240','mimes:pdf,jpg,jpeg,png,xlsx,xls,docx'],
            'items'=>['required','array','min:1','max:50'],
            'items.*.item_id'=>['nullable','integer','exists:ncr_items,id'],'items.*.product_id'=>['nullable','integer','exists:products,id'],'items.*.customer_id'=>['nullable','integer','exists:customers,id'],
            'items.*.product_code'=>['nullable','string','max:255'],'items.*.product_name'=>['nullable','string','max:255'],
            'items.*.lot_no'=>['nullable','string','max:255'],'items.*.po_no'=>['nullable','string','max:255'],'items.*.batch_no'=>['nullable','string','max:255'],
            'items.*.customer_name'=>['nullable','string','max:255'],'items.*.defect_quantity'=>['required','numeric','min:0'],
            'items.*.total_quantity'=>['required','numeric','gt:0'],'items.*.unit_name'=>['nullable',Rule::exists('units','code')],
            'items.*.defect_unit_name'=>['nullable',Rule::exists('units','code')],'items.*.total_unit_name'=>['nullable',Rule::exists('units','code')],
            'items.*.problem_description'=>['nullable','string','max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ncr = $this->route('ncr');
            $existingItems = $ncr instanceof NcrReport ? $ncr->items()->get()->keyBy('id') : collect();
            $existingSupplierId = $ncr instanceof NcrReport ? $ncr->supplier_id : null;

            if ($this->filled('supplier_id')) {
                $supplier = DB::table('suppliers')->where('id', $this->input('supplier_id'))->first();
                if ($supplier && ! $supplier->is_active && (int) $existingSupplierId !== (int) $supplier->id) {
                    $validator->errors()->add('supplier_id', 'กรุณาเลือกผู้ผลิต/ผู้ขายที่เปิดใช้งาน');
                }
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                $existingItem = ! empty($item['item_id']) ? $existingItems->get((int) $item['item_id']) : null;
                if (! empty($item['item_id']) && ! $existingItem) {
                    $validator->errors()->add("items.{$index}.item_id", 'ไม่พบรายการสินค้าใน NCR นี้');
                }

                if (empty($item['product_id'])) {
                    if (! $existingItem || $existingItem->product_id) {
                        $validator->errors()->add("items.{$index}.product_id", 'กรุณาเลือกสินค้าจากทะเบียนกลาง');
                    }
                } else {
                    $product = DB::table('products')->where('id', $item['product_id'])->first();
                    if ($product && ! $product->is_active && (! $existingItem || (int) $existingItem->product_id !== (int) $product->id)) {
                        $validator->errors()->add("items.{$index}.product_id", 'กรุณาเลือกสินค้าที่เปิดใช้งาน');
                    }
                }

                if (! empty($item['customer_id'])) {
                    $customer = DB::table('customers')->where('id', $item['customer_id'])->first();
                    if ($customer && ! $customer->is_active && (! $existingItem || (int) $existingItem->customer_id !== (int) $customer->id)) {
                        $validator->errors()->add("items.{$index}.customer_id", 'กรุณาเลือกลูกค้าที่เปิดใช้งาน');
                    }
                }

                if (isset($item['defect_quantity'], $item['total_quantity']) && (float) $item['defect_quantity'] > (float) $item['total_quantity']) {
                    $validator->errors()->add("items.{$index}.defect_quantity", 'จำนวนที่พบต้องไม่เกินจำนวนทั้งหมด');
                }
            }
        });
    }
}
