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

        $items = collect((array) $this->input('items', []))->map(function ($item) use ($normalizeUnit) {
            // Leave malformed entries untouched so Laravel validation can reject
            // them instead of triggering a TypeError before validation runs.
            if (! is_array($item)) {
                return $item;
            }

            $legacyUnit = $normalizeUnit($item['unit_name'] ?? null);
            $defectUnit = $normalizeUnit($item['defect_unit_name'] ?? $legacyUnit);
            $totalUnit = $normalizeUnit($item['total_unit_name'] ?? $legacyUnit);

            $item['defect_unit_name'] = $defectUnit;
            $item['total_unit_name'] = $totalUnit;
            $item['unit_name'] = $defectUnit === $totalUnit ? $defectUnit : null;
            unset($item['customer_id'], $item['customer_name']);

            return $item;
        })->values()->all();

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

        $customerId = $this->filled('customer_id') ? $this->input('customer_id') : null;
        $existingCustomerId = $report instanceof NcrReport ? $report->customer_id : null;
        $selectedCustomerName = $customerId
            ? Customer::query()->where('is_active', true)->whereNull('deleted_at')->whereKey($customerId)->value('name_th')
            : null;
        $companyName = $customerId
            ? ($report instanceof NcrReport && (int) $customerId === (int) $existingCustomerId
                ? $report->company_name
                : $selectedCustomerName)
            : null;

        $this->merge([
            'problem_description' => $this->input('problem_description') ?? '',
            'items' => $items,
            'company_name' => $companyName,
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
            'customer_id'=>['required','integer','exists:customers,id'],
            'company_name'=>['nullable','string','max:255'],
            'generic_name'=>['nullable','string','max:255'],'supplier_id'=>['nullable','integer','exists:suppliers,id'],'supplier_name'=>['nullable','string','max:255'],'production_date'=>['nullable','date'],'found_location'=>['nullable','string','max:255'],
            'problem_description'=>['nullable','string','max:10000'],
            'initial_disposition_type_id'=>['nullable','exists:disposition_types,id'],
            'initial_disposition_detail'=>['nullable','string','max:5000'],
            'initial_responsible_department_id'=>['nullable','exists:departments,id'],
            'initial_proposed_due_date'=>['nullable','date'],
            'version_no'=>['nullable','integer','min:1'],'attachments.*'=>['file','max:10240','mimes:pdf,jpg,jpeg,png,xlsx,xls,docx'],
            'items'=>['required','array','size:1'],
            'items.0.item_id'=>['nullable','integer','exists:ncr_items,id'],'items.0.product_id'=>['nullable','integer','exists:products,id'],
            'items.0.product_code'=>['nullable','string','max:255'],'items.0.product_name'=>['required','string','max:255'],
            'items.0.lot_no'=>['nullable','string','max:255'],'items.0.po_no'=>['nullable','string','max:255'],'items.0.batch_no'=>['nullable','string','max:255'],
            'items.0.defect_quantity'=>['required','numeric','regex:/^\d{1,14}(?:\.\d{1,4})?$/','min:0','max:99999999999999.9999'],
            'items.0.total_quantity'=>['required','numeric','regex:/^\d{1,14}(?:\.\d{1,4})?$/','gt:0','max:99999999999999.9999'],'items.0.unit_name'=>['nullable',Rule::exists('units','code')],
            'items.0.defect_unit_name'=>['nullable',Rule::exists('units','code')],'items.0.total_unit_name'=>['nullable',Rule::exists('units','code')],
            'items.0.problem_description'=>['nullable','string','max:5000'],
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
            $ncr = $this->route('ncr');
            $existingItems = $ncr instanceof NcrReport ? $ncr->items()->get()->keyBy('id') : collect();
            $existingSupplierId = $ncr instanceof NcrReport ? $ncr->supplier_id : null;
            $existingCustomerId = $ncr instanceof NcrReport ? $ncr->customer_id : null;

            if ($this->filled('customer_id')) {
                $customer = DB::table('customers')->where('id', $this->input('customer_id'))->first();
                $isExistingCustomer = $customer && (int) $existingCustomerId === (int) $customer->id;

                if (! $customer || ((! $customer->is_active || $customer->deleted_at !== null) && ! $isExistingCustomer)) {
                    $validator->errors()->add('customer_id', 'กรุณาเลือกลูกค้าที่เปิดใช้งานจาก NCR Master Data');
                }
            }

            if ($this->filled('supplier_id')) {
                $supplier = DB::table('suppliers')->where('id', $this->input('supplier_id'))->first();
                if ($supplier && ! $supplier->is_active && (int) $existingSupplierId !== (int) $supplier->id) {
                    $validator->errors()->add('supplier_id', 'กรุณาเลือกผู้ผลิต/ผู้ขายที่เปิดใช้งาน');
                }
            }

            $item = (array) data_get($this->input('items', []), '0', []);
            if ($item) {
                $existingItem = ! empty($item['item_id']) ? $existingItems->get((int) $item['item_id']) : null;
                if (! empty($item['item_id']) && ! $existingItem) {
                    $validator->errors()->add('items.0.item_id', 'ไม่พบรายการสินค้าใน NCR นี้');
                }

                if (! empty($item['product_id'])) {
                    $product = DB::table('products')->where('id', $item['product_id'])->first();
                    if ($product && ! $product->is_active && (! $existingItem || (int) $existingItem->product_id !== (int) $product->id)) {
                        $validator->errors()->add('items.0.product_id', 'กรุณาเลือกสินค้าที่เปิดใช้งาน');
                    }
                }

                if (isset($item['defect_quantity'], $item['total_quantity']) && (float) $item['defect_quantity'] > (float) $item['total_quantity']) {
                    $validator->errors()->add('items.0.defect_quantity', 'จำนวนที่พบต้องไม่เกินจำนวนทั้งหมด');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'กรุณาเลือกลูกค้า',
            'customer_id.exists' => 'ไม่พบลูกค้าที่เลือก กรุณาเลือกลูกค้าจากทะเบียน',
            'items.required' => 'กรุณากรอกข้อมูลสินค้า',
            'items.size' => 'NCR หนึ่งรายการต้องมีสินค้าเพียง 1 รายการ',
            'items.0.product_name.required' => 'กรุณากรอกชื่อสินค้า',
            'items.0.defect_quantity.required' => 'กรุณากรอกจำนวนที่พบ',
            'items.0.defect_quantity.numeric' => 'จำนวนที่พบต้องเป็นตัวเลข',
            'items.0.defect_quantity.regex' => 'จำนวนที่พบรองรับทศนิยมไม่เกิน 4 ตำแหน่งและไม่เกิน 14 หลัก',
            'items.0.defect_quantity.min' => 'จำนวนที่พบต้องไม่น้อยกว่า 0',
            'items.0.defect_quantity.max' => 'จำนวนที่พบเกินขอบเขตที่รองรับ',
            'items.0.total_quantity.required' => 'กรุณากรอกจำนวนทั้งหมด',
            'items.0.total_quantity.numeric' => 'จำนวนทั้งหมดต้องเป็นตัวเลข',
            'items.0.total_quantity.regex' => 'จำนวนทั้งหมดรองรับทศนิยมไม่เกิน 4 ตำแหน่งและไม่เกิน 14 หลัก',
            'items.0.total_quantity.gt' => 'จำนวนทั้งหมดต้องมากกว่า 0',
            'items.0.total_quantity.max' => 'จำนวนทั้งหมดเกินขอบเขตที่รองรับ',
        ];
    }
}
