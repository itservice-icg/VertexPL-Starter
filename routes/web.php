<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentRoleController;
use App\Http\Controllers\NcrMasterDataController;
use App\Http\Controllers\NcrReportController;
use App\Http\Controllers\NcrDashboardController;
use App\Http\Controllers\NcrTaskController;
use App\Http\Controllers\NotificationController;
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

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [NcrDashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/my-tasks', [NcrTaskController::class, 'index'])->middleware('permission:ncr.view')->name('ncr.tasks');
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('permission:ncr.view')->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('permission:ncr.view')->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('permission:ncr.view')->name('notifications.read');

    Route::get('/ncr', [NcrReportController::class, 'index'])->name('ncr.index');
    Route::get('/ncr/create', [NcrReportController::class, 'create'])->name('ncr.create');
    Route::post('/ncr', [NcrReportController::class, 'store'])->name('ncr.store');
    Route::get('/ncr/{ncr}/pdf', [NcrReportController::class, 'downloadPdf'])->where('ncr', '[0-9]+')->name('ncr.pdf');
    Route::get('/ncr/{ncr}', [NcrReportController::class, 'show'])->where('ncr', '[0-9]+')->name('ncr.show');
    Route::get('/ncr/{ncr}/edit', [NcrReportController::class, 'edit'])->where('ncr', '[0-9]+')->name('ncr.edit');
    Route::put('/ncr/{ncr}', [NcrReportController::class, 'update'])->where('ncr', '[0-9]+')->name('ncr.update');
    Route::post('/ncr/{ncr}/submit', [NcrReportController::class, 'submit'])->where('ncr', '[0-9]+')->name('ncr.submit');
    Route::post('/ncr/{ncr}/initial-review', [NcrReportController::class, 'initialReview'])->where('ncr', '[0-9]+')->name('ncr.initial-review');
    Route::post('/ncr/{ncr}/disposition/revise', [NcrReportController::class, 'reviseDisposition'])->where('ncr', '[0-9]+')->name('ncr.disposition.revise');
    Route::post('/ncr/{ncr}/disposition/submit', [NcrReportController::class, 'submitDisposition'])->where('ncr', '[0-9]+')->name('ncr.disposition.submit');
    Route::post('/ncr/{ncr}/manager-decision', [NcrReportController::class, 'managerDecision'])->where('ncr', '[0-9]+')->name('ncr.manager-decision');
    Route::post('/ncr/{ncr}/destruction-decision', [NcrReportController::class, 'destructionDecision'])->where('ncr', '[0-9]+')->name('ncr.destruction-decision');
    Route::post('/ncr/{ncr}/corrective-actions', [NcrReportController::class, 'storeCorrectiveAction'])->where('ncr', '[0-9]+')->name('ncr.corrective-actions.store');
    Route::put('/ncr/{ncr}/corrective-actions/{action}', [NcrReportController::class, 'updateCorrectiveAction'])->where(['ncr'=>'[0-9]+','action'=>'[0-9]+'])->name('ncr.corrective-actions.update');
    Route::post('/ncr/{ncr}/corrective-actions/submit-for-approval', [NcrReportController::class, 'submitCorrectiveActionsForApproval'])->where('ncr', '[0-9]+')->name('ncr.corrective-actions.submit-approval');
    Route::post('/ncr/{ncr}/corrective-actions/decision', [NcrReportController::class, 'correctiveActionDecision'])->where('ncr', '[0-9]+')->name('ncr.corrective-actions.decision');
    Route::post('/ncr/{ncr}/corrective-actions/submit-for-verification', [NcrReportController::class, 'submitActionsForVerification'])->where('ncr', '[0-9]+')->name('ncr.corrective-actions.submit');
    Route::post('/ncr/{ncr}/corrective-actions/verify', [NcrReportController::class, 'verifyActions'])->where('ncr', '[0-9]+')->name('ncr.corrective-actions.verify');
Route::post('/ncr/{ncr}/corrective-actions/start-rework', [NcrReportController::class, 'startRework'])->where('ncr', '[0-9]+')->name('ncr.corrective-actions.start-rework');
Route::post('/ncr/{ncr}/qa-followup', [NcrReportController::class, 'saveQaFollowup'])->where('ncr', '[0-9]+')->name('ncr.qa-followup.store');
Route::post('/ncr/{ncr}/qa-followup/return-for-rework', [NcrReportController::class, 'returnForReworkFromQa'])->where('ncr', '[0-9]+')->name('ncr.qa-followup.return');
    Route::get('/ncr/{ncr}/corrective-action-attachments/{attachment}', [NcrReportController::class, 'downloadActionAttachment'])->where(['ncr'=>'[0-9]+','attachment'=>'[0-9]+'])->name('ncr.corrective-action-attachments.download');
    Route::get('/ncr/{ncr}/attachments/{attachment}', [NcrReportController::class, 'download'])->where(['ncr'=>'[0-9]+','attachment'=>'[0-9]+'])->name('ncr.attachments.download');

    Route::prefix('ncr/masters')->name('ncr-masters.')->group(function () {
        Route::get('{type}', [NcrMasterDataController::class, 'index'])->name('index');
        Route::get('{type}/create', [NcrMasterDataController::class, 'create'])->name('create');
        Route::post('{type}', [NcrMasterDataController::class, 'store'])->name('store');
        Route::get('{type}/{record}/edit', [NcrMasterDataController::class, 'edit'])->name('edit');
        Route::put('{type}/{record}', [NcrMasterDataController::class, 'update'])->name('update');
        Route::delete('{type}/{record}', [NcrMasterDataController::class, 'destroy'])->name('destroy');
    });

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

    Route::get('/department-roles', [DepartmentRoleController::class, 'index'])
        ->middleware('permission:department-roles.manage')
        ->name('department-roles.index');
    Route::post('/department-roles', [DepartmentRoleController::class, 'store'])
        ->middleware('permission:department-roles.manage')
        ->name('department-roles.store');
    Route::put('/department-roles/{departmentRole}', [DepartmentRoleController::class, 'update'])
        ->middleware('permission:department-roles.manage')
        ->name('department-roles.update');
    Route::delete('/department-roles/{departmentRole}', [DepartmentRoleController::class, 'destroy'])
        ->middleware('permission:department-roles.manage')
        ->name('department-roles.destroy');

    Route::resource('departments', DepartmentController::class)
        ->except('show');

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

    Route::resource('settings', SettingController::class)
        ->except('show')
        ->middleware('permission:system.settings');

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
