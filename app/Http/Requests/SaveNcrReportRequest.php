<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\NcrReport;
use App\Models\Customer;
use App\Models\NcrFormScope;

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

        if ($this->exists('company_customer_id') && $this->input('company_customer_id') !== 'legacy') {
            $customerId = $this->filled('company_customer_id') ? $this->input('company_customer_id') : null;
            $items = array_map(function (array $item) use ($customerId): array {
                $item['customer_id'] = $customerId;

                return $item;
            }, $items);
        }

        $scopeInput = $this->input('legacy_form_scope_code');
        $rawScopeCodes = $this->has('legacy_form_scope_codes')
            ? (array) $this->input('legacy_form_scope_codes')
            : ($scopeInput === '__clear__'
                ? []
                : explode(',', (string) ($scopeInput ?: $this->input('legacy_form_scope_code_previous', ''))));
        $scopeCodes = array_values(array_unique(array_filter(
            $rawScopeCodes,
            static fn ($code) => $code !== null && $code !== ''
        )));

        $report = $this->route('ncr');
        if ($report instanceof NcrReport && ! $this->exists('severity_level_id')) {
            $this->merge(['severity_level_id' => $report->severity_level_id]);
        }

        $this->merge([
            'problem_description' => $this->input('problem_description') ?? '',
            'items' => $items,
            'company_name' => $this->filled('company_customer_id')
                ? Customer::query()
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereKey($this->input('company_customer_id'))
                    ->value('name_th')
                : ($this->input('reference_customer_name') ?: $this->input('company_name')),
            'legacy_form_scope_code' => $scopeCodes ? implode(',', $scopeCodes) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'report_date'=>['required','date'],'found_date'=>['required','date','before_or_equal:today'],'found_time'=>['nullable','date_format:H:i'],
            'legacy_form_scope_code'=>['nullable', $this->formScopeRule()],
            'legacy_form_scope_codes'=>['nullable','array','max:1'],
            'legacy_form_scope_codes.*'=>['required', $this->formScopeRule(), 'distinct'],
            'reporter_department_id'=>['nullable','exists:departments,id'],'related_department_id'=>['nullable','exists:departments,id'],'problem_type_id'=>['required','exists:problem_types,id'],'severity_level_id'=>['nullable','exists:severity_levels,id'],
            'company_name'=>['nullable','string','max:255'],
            'reference_product_code'=>['nullable','string','max:255'],
            'reference_product_name'=>['nullable','string','max:255'],
            'reference_customer_name'=>['nullable','string','max:255'],
            'company_customer_id'=>['nullable',function ($attribute, $value, $fail) {
                if ($value === null || $value === '') {
                    return;
                }

                if ($value === 'legacy') {
                    $report = $this->route('ncr');
                    if ($report instanceof NcrReport && $this->input('company_name') === $report->company_name) {
                        return;
                    }
                } elseif (filter_var($value, FILTER_VALIDATE_INT) !== false && Customer::query()
                    ->whereKey($value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->exists()) {
                    return;
                }

                $fail('กรุณาเลือกลูกค้าที่เปิดใช้งานจาก NCR Master Data');
            }],
            'generic_name'=>['nullable','string','max:255'],'supplier_id'=>['nullable','integer','exists:suppliers,id'],'supplier_name'=>['nullable','string','max:255'],'production_date'=>['nullable','date'],'found_location'=>['nullable','string','max:255'],
            'problem_description'=>['nullable','string','max:10000'],
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

    private function formScopeRule(): \Closure
    {
        return function ($attribute, $value, $fail): void {
            $scope = NcrFormScope::query()->where('code', $value)->where('is_active', true)->exists();
            $report = $this->route('ncr');
            $alreadySelected = $report instanceof NcrReport
                && in_array($value, explode(',', (string) $report->legacy_form_scope_code), true);

            if (! $scope && ! $alreadySelected) {
                $fail('กรุณาเลือกขอบเขตแบบฟอร์มที่เปิดใช้งาน');
            }
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('company_customer_id') && $this->input('company_customer_id') !== 'legacy' && $this->filled('reference_customer_name')) {
                $customerName = Customer::query()
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereKey($this->input('company_customer_id'))
                    ->value('name_th');

                if ($customerName !== $this->input('reference_customer_name')) {
                    $validator->errors()->add('reference_customer_name', 'ชื่อลูกค้าต้องตรงกับลูกค้าที่เลือกจาก Master');
                }
            }

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
