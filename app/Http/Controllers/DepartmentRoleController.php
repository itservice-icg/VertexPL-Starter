<?php

namespace App\Http\Controllers;

use App\Models\DepartmentRole;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentRoleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $departmentRoles = DepartmentRole::query()
            ->withCount('users')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name_th', 'like', "%{$search}%");
                });
            })
            ->orderBy('name_th')
            ->paginate(15)
            ->withQueryString();

        return view('department-roles.index', compact('departmentRoles', 'filters'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:department_roles,code'],
            'name_th' => ['required', 'string', 'max:100', 'unique:department_roles,name_th'],
        ], [
            'code.regex' => 'รหัสต้องขึ้นต้นด้วย A-Z และใช้ได้เฉพาะ A-Z, 0-9 หรือ _',
        ]);

        $role = DB::transaction(function () use ($data) {
            $role = DepartmentRole::create($data);

            AuditLogger::log(
                'department_roles',
                'department_roles.created',
                "เพิ่มบทบาทในหน่วยงาน {$role->name_th}",
                $role,
                [],
                $role->only(['code', 'name_th'])
            );

            return $role;
        });

        return redirect()->route('department-roles.index')->with('success', "เพิ่มบทบาท {$role->name_th} เรียบร้อยแล้ว");
    }

    public function update(Request $request, DepartmentRole $departmentRole)
    {
        $data = $request->validate([
            'name_th' => ['required', 'string', 'max:100', Rule::unique('department_roles', 'name_th')->ignore($departmentRole->id)],
        ]);

        DB::transaction(function () use ($departmentRole, $data) {
            $lockedRole = DepartmentRole::query()->whereKey($departmentRole->id)->lockForUpdate()->firstOrFail();
            $oldValues = $lockedRole->only(['name_th']);

            $lockedRole->update($data);

            AuditLogger::log(
                'department_roles',
                'department_roles.updated',
                "แก้ไขบทบาทในหน่วยงาน {$lockedRole->name_th}",
                $lockedRole,
                $oldValues,
                $lockedRole->only(['name_th'])
            );
        });

        return redirect()->route('department-roles.index')->with('success', 'บันทึกบทบาทในหน่วยงานเรียบร้อยแล้ว');
    }

    public function destroy(DepartmentRole $departmentRole)
    {
        $result = DB::transaction(function () use ($departmentRole) {
            $lockedRole = DepartmentRole::query()->whereKey($departmentRole->id)->lockForUpdate()->firstOrFail();

            if ($lockedRole->users()->exists()) {
                return 'assigned';
            }

            if ($lockedRole->code === 'DEPARTMENT_HEAD') {
                return 'protected';
            }

            if (DepartmentRole::query()->count() <= 1) {
                return 'last_role';
            }

            AuditLogger::log(
                'department_roles',
                'department_roles.deleted',
                "ลบบทบาทในหน่วยงาน {$lockedRole->name_th}",
                $lockedRole,
                $lockedRole->only(['code', 'name_th'])
            );

            $lockedRole->delete();

            return 'deleted';
        });

        if ($result === 'assigned') {
            return back()->with('error', 'ลบบทบาทนี้ไม่ได้ เพราะยังมีผู้ใช้งานอยู่ กรุณาเปลี่ยนบทบาทของผู้ใช้ก่อน');
        }

        if ($result === 'protected') {
            return back()->with('error', 'ไม่สามารถลบบทบาทหัวหน้าหน่วยงานได้ เพราะใช้กำหนดผู้อนุมัติแผน Corrective Action');
        }

        if ($result === 'last_role') {
            return back()->with('error', 'ต้องมีบทบาทในหน่วยงานอย่างน้อยหนึ่งรายการ');
        }

        return redirect()->route('department-roles.index')->with('success', 'ลบบทบาทในหน่วยงานเรียบร้อยแล้ว');
    }
}
