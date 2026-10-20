<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.login');
    } 

    public function createDev()
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $users = $this->devLoginUsers()->with(['roles', 'department', 'departmentRole'])
            ->orderBy('name')
            ->get();
        $usersByDepartment = $users
            ->groupBy(fn (User $user) => optional($user->department)->name_th ?: 'ไม่มีหน่วยงาน')
            ->sortKeys()
            ->map(fn ($departmentUsers) => $departmentUsers
                ->sortByDesc(fn (User $user) => optional($user->departmentRole)->name_th === 'หัวหน้าหน่วยงาน')
                ->values());

        return view('auth.dev', [
            'usersByDepartment' => $usersByDepartment,
            'userCount' => $users->count(),
        ]);
    }

    public function storeDev(Request $request)
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $request->validate(['user_id' => ['required', 'integer']]);

        $user = $this->devLoginUsers()
            ->findOrFail($request->input('user_id'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLogger::log('auth', 'auth.login.dev', "เข้าสู่ระบบเพื่อทดสอบ {$user->email}", $user);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    private function devLoginUsers()
    {
        return User::where('is_active', true)->where(function ($query) {
            $query->where('email', 'like', '%@mock.vertexpl.local')
                ->orWhereHas('roles', function ($roles) {
                    $roles->whereIn('name', ['Super Admin', 'Administrator'])
                        ->where('guard_name', 'web');
                });
        });
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        $request->user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLogger::log(
            'auth',
            'auth.login.success',
            "เข้าสู่ระบบสำเร็จ {$request->user()->email}",
            $request->user(),
            [],
            ['remember' => $request->boolean('remember')]
        );

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        AuditLogger::log(
            'auth',
            'auth.logout',
            "ออกจากระบบ {$user->email}",
            $user
        );

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
