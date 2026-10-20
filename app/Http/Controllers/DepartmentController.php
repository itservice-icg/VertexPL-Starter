<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:departments.view')->only('index');
        $this->middleware('permission:departments.create')->only(['create', 'store']);
        $this->middleware('permission:departments.update')->only(['edit', 'update']);
        $this->middleware('permission:departments.delete')->only('destroy');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $departments = Department::query()
            ->withCount('users')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name_th', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%");
                });
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('departments.index', compact('departments', 'filters'));
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = DB::transaction(function () use ($request) {
            $department = Department::create($request->validated());
            AuditLogger::log('departments', 'departments.created', "เพิ่มหน่วยงาน {$department->code}", $department, [], $this->snapshot($department));

            return $department;
        });

        return redirect()->route('departments.index')->with('success', 'เพิ่มหน่วยงานเรียบร้อยแล้ว');
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        DB::transaction(function () use ($request, $department) {
            $oldValues = $this->snapshot($department);
            $department->update($request->validated());
            AuditLogger::log('departments', 'departments.updated', "แก้ไขหน่วยงาน {$department->code}", $department, $oldValues, $this->snapshot($department->fresh()));
        });

        return redirect()->route('departments.index')->with('success', 'บันทึกหน่วยงานเรียบร้อยแล้ว');
    }

    public function destroy(Department $department)
    {
        if ($department->users()->exists()) {
            return back()->with('error', 'ไม่สามารถลบหน่วยงานที่มีผู้ใช้งานอยู่ได้ กรุณาย้ายผู้ใช้งานก่อน');
        }

        DB::transaction(function () use ($department) {
            AuditLogger::log('departments', 'departments.deleted', "ลบหน่วยงาน {$department->code}", $department, $this->snapshot($department));
            $department->delete();
        });

        return redirect()->route('departments.index')->with('success', 'ลบหน่วยงานเรียบร้อยแล้ว');
    }

    private function snapshot(Department $department): array
    {
        return $department->only(['code', 'name_th', 'name_en', 'is_active']);
    }
}
