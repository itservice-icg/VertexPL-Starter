<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'role' => ['nullable', 'integer', 'exists:roles,id'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $users = User::query()
            ->with(['roles', 'department', 'departmentRole'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['role'] ?? null, function ($query, $roleId) {
                $query->whereHas('roles', fn ($query) => $query->where('roles.id', $roleId));
            })
            ->when($filters['department'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('code')->get(),
            'departmentRoles' => DepartmentRole::query()->orderBy('name_th')->get(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('code')->get(),
            'departmentRoles' => DepartmentRole::query()->orderBy('name_th')->get(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department_id' => $validated['department_id'] ?? null,
                'department_role' => $validated['department_role'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'],
            ]);

            $user->syncRoles([$validated['role_id']]);

            AuditLogger::log(
                'users',
                'users.created',
                "เพิ่มผู้ใช้งาน {$user->email}",
                $user,
                [],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'department_id' => $user->department_id,
                    'department_role' => $user->department_role,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames()->all(),
                ]
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'department']);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'departments' => Department::query()
                ->where(function ($query) use ($user) {
                    $query->where('is_active', true)
                        ->orWhere('id', $user->department_id);
                })
                ->orderBy('code')
                ->get(),
            'departmentRoles' => DepartmentRole::query()->orderBy('name_th')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        if ($request->user()->is($user) && ! $validated['is_active']) {
            return back()
                ->withInput()
                ->withErrors(['is_active' => 'ไม่สามารถปิดใช้งานบัญชีของตนเองได้']);
        }

        DB::transaction(function () use ($validated, $user) {
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'department_id' => $user->department_id,
                'department_role' => $user->department_role,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames()->all(),
            ];

            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department_id' => $validated['department_id'] ?? null,
                'department_role' => $validated['department_role'],
                'is_active' => $validated['is_active'],
            ];

            if (! empty($validated['password'])) {
                $attributes['password'] = Hash::make($validated['password']);
            }

            $user->update($attributes);
            $user->syncRoles([$validated['role_id']]);
            $user->refresh()->load('roles');

            AuditLogger::log(
                'users',
                'users.updated',
                "แก้ไขผู้ใช้งาน {$user->email}",
                $user,
                $oldValues,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'department_id' => $user->department_id,
                    'department_role' => $user->department_role,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames()->all(),
                    'password_changed' => ! empty($validated['password']),
                ]
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'แก้ไขผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'ไม่สามารถลบบัญชีของตนเองได้');
        }

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'department_id' => $user->department_id,
            'department_role' => $user->department_role,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames()->all(),
        ];

        DB::transaction(function () use ($user, $oldValues) {
            $user->delete();

            AuditLogger::log(
                'users',
                'users.deleted',
                "ลบผู้ใช้งาน {$user->email}",
                $user,
                $oldValues
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
    }
}
