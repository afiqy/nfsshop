<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdmin\OwnerController;
use App\Http\Controllers\SuperAdmin\WorkshopController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 🔹 Authentication Routes (Public)
Route::get('/', function () {
    return auth()->check()
        ? (auth()->user()->isSuperAdmin()
            ? redirect()->route('super-admin.dashboard')
            : (auth()->user()->isAccountSuperAdmin()
                ? redirect()->route('account.workshops.index')
                : redirect()->route('dashboard')))
        : redirect()->route('login');
})->name('home');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1') // Limit to 5 attempts per minute
    ->name('login.post');

Route::get('/register', [RegistrationController::class, 'start'])->name('register');
Route::post('/register/account', [RegistrationController::class, 'submitAccount'])->name('register.account');
Route::get('/register/workshop', [RegistrationController::class, 'showWorkshop'])->name('register.workshop');
Route::post('/register/workshop', [RegistrationController::class, 'submitWorkshop'])->name('register.workshop.submit');
Route::get('/register/plan', [RegistrationController::class, 'showPlan'])->name('register.plan');
Route::post('/register/plan', [RegistrationController::class, 'submitPlan'])->name('register.plan.submit');
Route::get('/register/staff', [RegistrationController::class, 'showStaff'])->name('register.staff');
Route::post('/register/staff', [RegistrationController::class, 'submitStaff'])->name('register.staff.submit');
Route::get('/register/complete', [RegistrationController::class, 'complete'])->name('register.complete');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::post('/password-request', function () {
    return view('welcome');
})->name('password.request');

Route::middleware(['auth', 'check.lockout', 'session.timeout', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');

        Route::resource('owners', OwnerController::class)->except(['show']);
        Route::post('owners/{owner}/suspend', [OwnerController::class, 'suspend'])->name('owners.suspend');
        Route::post('owners/{owner}/activate', [OwnerController::class, 'activate'])->name('owners.activate');
        Route::post('owners/{owner}/reset-password', [OwnerController::class, 'resetPassword'])->name('owners.reset-password');
        Route::post('owners/{owner}/impersonate', [OwnerController::class, 'impersonate'])->name('owners.impersonate');

        Route::get('impersonation', [ImpersonationController::class, 'index'])->name('impersonation.index');
        Route::post('impersonation', [ImpersonationController::class, 'start'])->name('impersonation.start');

        Route::resource('workshops', WorkshopController::class)->only(['index', 'edit', 'update']);

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.logs.export');
    });

Route::middleware(['auth', 'check.lockout', 'session.timeout'])->group(function () {
    Route::post('impersonation/leave', [OwnerController::class, 'leaveImpersonation'])->name('impersonation.leave');
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/edit', [UserController::class, 'editProfile'])->name('edit');
        Route::put('/edit', [UserController::class, 'updateProfile'])->name('update');
        Route::patch('/password', [UserController::class, 'updatePassword'])->name('password.update');
    });
});

Route::middleware(['auth', 'check.lockout', 'session.timeout', 'role:admin'])
    ->get('/subscription', [BillingController::class, 'dashboard'])
    ->name('workshop.subscription');

Route::middleware(['auth', 'check.lockout', 'session.timeout', 'role:account_super_admin'])
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::middleware('subscription.active')->group(function () {
            Route::resource('workshops', WorkshopController::class)
                ->only(['index', 'create', 'store', 'edit', 'update']);
        });

        Route::get('/subscription', [BillingController::class, 'dashboard'])
            ->name('subscription');
    });

Route::get('/subscription-required', function (Request $request) {
    $tenant = \App\Models\Tenant::find($request->user()->tenant_id);

    return view('auth.subscription-required', [
        'ownerEmail' => data_get($tenant?->data ?? [], 'owner_email'),
    ]);
})
    ->middleware(['auth', 'check.lockout', 'session.timeout', 'role:mechanic'])
    ->name('subscription.required');

// 🔹 Protected Routes (Only Accessible if Logged In)
Route::middleware(['auth', 'check.lockout', 'session.timeout', 'tenant.context'])->group(function () {

    // 📌 Dashboard
    Route::get('/dashboard', function (Request $request, \App\Services\SubscriptionEntitlementService $entitlements) {
        $subscription = $entitlements->subscriptionFor($request->user());
        $subscriptionUsable = $subscription?->isUsable() ?? false;
        $request->attributes->set('subscription_usable', $subscriptionUsable);

        return view('dashboard', ['subscriptionUsable' => $subscriptionUsable]);
    })->middleware('subscription.active')->name('dashboard');

    Route::middleware('subscription.active')->group(function () {
        // 📌 Customer Management
        Route::resource('customers', CustomerController::class);

        // 📌 Vehicle Management
        Route::resource('vehicles', VehicleController::class);

        // 📌 Invoices
        Route::resource('invoices', InvoiceController::class);

        // 📌 Inventory Management
        Route::resource('inventory', InventoryController::class);

        // 📌 User Management
        Route::resource('users', UserController::class);

        // 📌 Tasks Management
        Route::resource('tasks', TaskController::class);

        // 📌 Admin & Role Settings
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [AdminController::class, 'index'])->name('settings');
            Route::prefix('/roles')->name('roles.')->group(function () {
                Route::get('/', [RoleController::class, 'index'])->name('index');
            });
        });

        // 📌 Calendar Module (Workshop Service Appointments)
        Route::prefix('calendar')->name('calendar.')->group(function () {
            Route::get('/', [CalendarController::class, 'index'])->name('index');
            Route::get('/appointments', [CalendarController::class, 'fetchAppointments'])->name('appointments');
            Route::post('/store', [CalendarController::class, 'store'])->name('store');
        });
    });
});
