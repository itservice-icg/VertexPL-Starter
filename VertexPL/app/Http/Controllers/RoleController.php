<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const PROTECTED_ROLE = 'Super Admin';

    private const SELF_REQUIRED_PERMISSIONS = [
        'roles.view',
        'roles.update',
    ];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderByRaw("CASE WHEN name = ? THEN 0 ELSE 1 END", [self::PROTECTED_ROLE])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('roles.index', [
            'roles' => $roles,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('roles.create', $this->permissionFormData());
    }

    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($validated['permissions'] ?? []);

            AuditLogger::log(
                'roles',
                'roles.created',
                "เพิ่ม Role {$role->name}",
                $role,
                [],
                [
                    'name' => $role->name,
                    'permissions' => $role->getPermissionNames()->all(),
                ]
            );
        });

        return redirect()
            ->route('roles.index')
            ->with('success', 'เพิ่ม Role เรียบร้อยแล้ว');
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        return view('roles.edit', array_merge(
            $this->permissionFormData($role),
            ['role' => $role]
        ));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $validated = $request->validated();

        if ($role->name === self::PROTECTED_ROLE && $validated['name'] !== self::PROTECTED_ROLE) {
            return back()
                ->withInput()
                ->withErrors(['name' => 'ไม่สามารถเปลี่ยนชื่อ Role Super Admin ได้']);
        }

        $permissionIds = $validated['permissions'] ?? [];
        $permissionNames = Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();

        if ($request->user()->hasRole($role)) {
            $missing = array_diff(self::SELF_REQUIRED_PERMISSIONS, $permissionNames);

            if ($missing) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'permissions' => 'ไม่สามารถถอนสิทธิ์ roles.view หรือ roles.update จาก Role ของตนเองได้',
                    ]);
            }
        }

        DB::transaction(function () use ($validated, $role, $permissionIds) {
            $oldValues = [
                'name' => $role->name,
                'permissions' => $role->getPermissionNames()->all(),
            ];

            $role->update(['name' => $validated['name']]);
            $role->syncPermissions($permissionIds);
            $role->refresh();

            AuditLogger::log(
                'roles',
                'roles.updated',
                "แก้ไข Role {$role->name}",
                $role,
                $oldValues,
                [
                    'name' => $role->name,
                    'permissions' => $role->getPermissionNames()->all(),
                ]
            );
        });

        return redirect()
            ->route('roles.index')
            ->with('success', 'แก้ไข Role และ Permission เรียบร้อยแล้ว');
    }

    public function destroy(Role $role)
    {
        if ($role->name === self::PROTECTED_ROLE) {
            return back()->with('error', 'ไม่สามารถลบ Role Super Admin ได้');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'ไม่สามารถลบ Role ที่ยังมีผู้ใช้งานได้');
        }

        $oldValues = [
            'name' => $role->name,
            'permissions' => $role->getPermissionNames()->all(),
        ];

        DB::transaction(function () use ($role, $oldValues) {
            AuditLogger::log(
                'roles',
                'roles.deleted',
                "ลบ Role {$role->name}",
                $role,
                $oldValues
            );

            $role->delete();
        });

        return redirect()
            ->route('roles.index')
            ->with('success', 'ลบ Role เรียบร้อยแล้ว');
    }

    private function permissionFormData(?Role $role = null): array
    {
        $permissions = Permission::query()->orderBy('name')->get();
        $labels = [
            'dashboard' => 'Dashboard',
            'users' => 'User Management',
            'roles' => 'Role Management',
            'permissions' => 'Permission Management',
            'imports' => 'Import',
            'exports' => 'Export',
            'system' => 'System',
        ];

        $groups = $permissions
            ->groupBy(fn ($permission) => explode('.', $permission->name)[0])
            ->map(function ($items, $key) use ($labels) {
                return [
                    'key' => $key,
                    'label' => $labels[$key] ?? ucfirst($key),
                    'permissions' => $items->values(),
                ];
            })
            ->values();

        return [
            'permissionGroups' => $groups,
            'selectedPermissionIds' => old(
                'permissions',
                $role ? $role->permissions->pluck('id')->all() : []
            ),
        ];
    }
}
