<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', [
            'user' => request()->user()->load('roles'),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
        ];

        $user->fill($request->safe()->only(['name', 'email']));

        $user->save();

        AuditLogger::log(
            'profile',
            'profile.updated',
            'แก้ไขข้อมูลโปรไฟล์',
            $user,
            $oldValues,
            [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            ]
        );

        return redirect()
            ->route('profile.edit')
            ->with('success', 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        $oldChangedAt = optional($user->password_changed_at)->toIso8601String();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        Auth::logoutOtherDevices($validated['password']);
        $request->session()->put('auth.password_confirmed_at', time());

        AuditLogger::log(
            'profile',
            'profile.password_changed',
            'เปลี่ยนรหัสผ่านและออกจากระบบในอุปกรณ์อื่น',
            $user,
            ['password_changed_at' => $oldChangedAt],
            ['password_changed_at' => optional($user->password_changed_at)->toIso8601String()]
        );

        return redirect()
            ->route('profile.edit')
            ->with('success', 'เปลี่ยนรหัสผ่านและออกจากระบบในอุปกรณ์อื่นเรียบร้อยแล้ว');
    }
}
