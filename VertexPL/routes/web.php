<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:users.update')
        ->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])
        ->middleware(['permission:roles.create', 'permission:permissions.view'])
        ->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware(['permission:roles.create', 'permission:permissions.manage'])
        ->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware(['permission:roles.update', 'permission:permissions.view'])
        ->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware(['permission:roles.update', 'permission:permissions.manage'])
        ->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('roles.destroy');

    Route::view('/imports', 'pages.placeholder', [
        'title' => 'Import / Export',
        'description' => 'พื้นที่สำหรับนำเข้าและส่งออกข้อมูลของระบบ',
    ])->middleware('permission:imports.view')->name('imports.index');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:system.audit.view')
        ->name('audit-logs.index');

    Route::view('/settings', 'pages.placeholder', [
        'title' => 'System Settings',
        'description' => 'จัดการค่าพื้นฐานและการตั้งค่าของระบบ',
    ])->middleware('permission:system.settings')->name('settings.index');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('password.confirm')
        ->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware(['password.confirm', 'throttle:profile-password'])
        ->name('profile.password.update');
});

require __DIR__.'/auth.php';
