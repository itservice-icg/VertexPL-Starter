<?php

namespace App\Http\Controllers;

use App\Models\NcrMasterData;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NcrMasterDataController extends Controller
{
    public function __construct() { $this->middleware('permission:ncr.master.manage'); }

    public function index(Request $request, string $type)
    {
        $definition = $this->definition($type);
        $filters = $request->validate(['search' => ['nullable','string','max:100'], 'status' => ['nullable','in:active,inactive']]);
        $model = $definition['model'];
        $records = $model::query()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('code','like',"%{$s}%")->orWhere('name_th','like',"%{$s}%")))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('code')->paginate(15)->withQueryString();
        return view('ncr-masters.index', compact('type','definition','records','filters'));
    }

    public function create(string $type) { $definition = $this->definition($type); return view('ncr-masters.form', compact('type','definition')); }

    public function store(Request $request, string $type)
    {
        $definition = $this->definition($type); $model = $definition['model'];
        $record = DB::transaction(function () use ($request, $definition, $model) {
            $record = $model::create($this->validated($request, $definition));
            AuditLogger::log('ncr-master', 'ncr-master.created', "เพิ่ม {$definition['title']} {$record->getAttribute('code')}", $record, [], $record->toArray());
            return $record;
        });
        return redirect()->route('ncr-masters.edit', [$type, $record])->with('success', 'เพิ่มข้อมูลเรียบร้อยแล้ว');
    }

    public function edit(string $type, int $record) { $definition = $this->definition($type); $record = $this->record($definition, $record); return view('ncr-masters.form', compact('type','definition','record')); }

    public function update(Request $request, string $type, int $record)
    {
        $definition = $this->definition($type); $record = $this->record($definition, $record); $old = $record->toArray();
        DB::transaction(function () use ($request, $definition, $record, $old) { $record->update($this->validated($request, $definition, $record)); AuditLogger::log('ncr-master','ncr-master.updated',"แก้ไข {$definition['title']} {$record->getAttribute('code')}",$record,$old,$record->fresh()->toArray()); });
        return redirect()->route('ncr-masters.index', $type)->with('success', 'บันทึกข้อมูลเรียบร้อยแล้ว');
    }

    public function destroy(string $type, int $record)
    {
        $definition = $this->definition($type); $record = $this->record($definition, $record);
        if ($record->isInUse()) {
            $code = $record->getAttribute('code');
            $count = $record->usageCount();
            $message = $type === 'problem-types'
                ? 'ไม่สามารถลบประเภทปัญหานี้ได้ เนื่องจากมี NCR ที่ใช้งานประเภทปัญหานี้อยู่'
                : "ไม่สามารถลบ {$definition['title']} “{$code}” ได้ เนื่องจากมีข้อมูล NCR อ้างอิงอยู่ {$count} รายการ กรุณาเปลี่ยนสถานะเป็น “ปิดใช้งาน” แทน";
            return back()->with('error', $message);
        }
        DB::transaction(function () use ($definition, $record) { AuditLogger::log('ncr-master','ncr-master.deleted',"ลบ {$definition['title']} {$record->getAttribute('code')}",$record,$record->toArray()); $record->delete(); });
        return redirect()->route('ncr-masters.index', $type)->with('success', 'ลบข้อมูลเรียบร้อยแล้ว');
    }

    private function definition(string $type): array { $definition = config("ncr.masters.{$type}"); abort_unless($definition, 404); return $definition; }
    private function record(array $definition, int $id): NcrMasterData { return $definition['model']::query()->findOrFail($id); }
    private function validated(Request $request, array $definition, ?NcrMasterData $record = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code', '')))]);
        $rules = ['code' => ['required','string','max:100','regex:/^[A-Z0-9_\-]+$/',Rule::unique($record ? $record->getTable() : (new $definition['model'])->getTable(),'code')->ignore(optional($record)->id)], 'name_th' => ['required','string','max:255'], 'is_active' => ['required','boolean']];
        foreach ($definition['fields'] as $field => $label) $rules[$field] = str_starts_with($field, 'require_') ? ['nullable','boolean'] : (in_array($field,['sla_days','sort_order'],true) ? ['nullable','integer','min:0'] : ['nullable','string','max:1000']);
        $data = $request->validate($rules, [
            'code.regex' => 'รูปแบบรหัสไม่ถูกต้อง ใช้ตัวอักษร A-Z ตัวเลข เครื่องหมาย - หรือ _ เช่น P-001',
        ]);
        foreach (array_keys($definition['fields']) as $field) if (str_starts_with($field,'require_')) $data[$field] = $request->boolean($field);
        return $data;
    }
}
