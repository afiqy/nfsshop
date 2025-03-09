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

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 🔹 Authentication Routes (Public)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::post('/password-request', function () {
    return view('welcome');
})->name('password.request');

// 🔹 Protected Routes (Only Accessible if Logged In)
Route::middleware(['auth'])->group(function () {
    
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
