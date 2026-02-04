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
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdmin\OwnerController;
use App\Http\Controllers\SuperAdmin\WorkshopController;
use App\Http\Controllers\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 🔹 Authentication Routes (Public)
Route::get('/', function(){
    return redirect('login');
})->name('home');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

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

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');

    Route::resource('owners', OwnerController::class)->except(['show']);
    Route::post('owners/{owner}/suspend', [OwnerController::class, 'suspend'])->name('owners.suspend');
    Route::post('owners/{owner}/activate', [OwnerController::class, 'activate'])->name('owners.activate');
    Route::post('owners/{owner}/reset-password', [OwnerController::class, 'resetPassword'])->name('owners.reset-password');
    Route::post('owners/{owner}/impersonate', [OwnerController::class, 'impersonate'])->name('owners.impersonate');

    Route::get('impersonation', [ImpersonationController::class, 'index'])->name('impersonation.index');
    Route::post('impersonation', [ImpersonationController::class, 'start'])->name('impersonation.start');

    Route::resource('workshops', WorkshopController::class)->only(['index', 'edit', 'update']);

    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'dashboard'])->name('dashboard');
        Route::patch('/subscriptions/{subscription}', [BillingController::class, 'update'])->name('subscriptions.update');
        Route::post('/subscriptions/{tenant}/sync', [BillingController::class, 'syncTenant'])->name('subscriptions.sync');

        Route::resource('plans', SubscriptionPlanController::class);
    });
});

Route::middleware(['auth'])->group(function () {
    Route::post('impersonation/leave', [OwnerController::class, 'leaveImpersonation'])->name('impersonation.leave');
});

// 🔹 Protected Routes (Only Accessible if Logged In)
Route::middleware(['auth', 'tenant.context'])->group(function () {
    
    // 📌 Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

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
        Route::prefix('/roles')->name('roles.')->group(function(){
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
