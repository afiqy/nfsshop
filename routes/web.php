<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillplzSandboxController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\MyInvoisPreparationController;
use App\Http\Controllers\PaymentPreferenceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StripeSandboxController;
use App\Http\Controllers\SuperAdmin\BillingController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\OwnerController;
use App\Http\Controllers\SuperAdmin\WorkshopController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ToyyibPaySandboxController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WorkshopDashboardController;
use App\Http\Middleware\EnsureInventoryAccess;
use App\Http\Middleware\EnsureWorkshopContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
                : (auth()->user()->role === \App\Models\User::ROLE_CLIENT
                    ? redirect()->route('client.portal')
                    : redirect()->route('dashboard'))))
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
    Route::get('/dashboard', WorkshopDashboardController::class)
        ->middleware('subscription.active')->name('dashboard');

    Route::middleware('subscription.active')->group(function () {
        Route::middleware(['role:mechanic', EnsureWorkshopContext::class])
            ->prefix('my-jobs')->name('my-jobs.')->group(function () {
                Route::get('/', [JobCardController::class, 'index'])->name('index');
                Route::get('{job_card}', [JobCardController::class, 'show'])->name('show');
                Route::post('{job_card}/notes', [JobCardController::class, 'addNote'])->name('notes.store');
                Route::post('{job_card}/vehicle-updates', [JobCardController::class, 'addVehicleUpdate'])
                    ->name('vehicle-updates.store');
                Route::post('{job_card}/parts', [JobCardController::class, 'usePart'])
                    ->middleware(EnsureInventoryAccess::class)->name('parts.store');
                Route::post('{job_card}/parts/{part}/returns', [JobCardController::class, 'returnPart'])
                    ->middleware(EnsureInventoryAccess::class)->name('parts.return');
                Route::patch('{job_card}/status', [JobCardController::class, 'advance'])->name('advance');
            });

        Route::middleware(['role:admin', EnsureWorkshopContext::class])->group(function () {
            Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store', 'edit', 'update']);
            Route::resource('vehicles', VehicleController::class)->only(['index', 'create', 'store', 'edit', 'update']);
            Route::get('vehicles/{vehicle}/history', [VehicleController::class, 'history'])->name('vehicles.history');
            Route::get('client-accounts', [CustomerAccountController::class, 'index'])->name('client-accounts.index');
            Route::post('client-accounts/{user}/activate', [CustomerAccountController::class, 'activate'])->name('client-accounts.activate');
            Route::patch('client-vehicle-requests/{vehicleRequest}/approve', [CustomerAccountController::class, 'approveVehicleRequest'])
                ->name('client-vehicle-requests.approve');
            Route::patch('client-vehicle-requests/{vehicleRequest}/reject', [CustomerAccountController::class, 'rejectVehicleRequest'])
                ->name('client-vehicle-requests.reject');
            Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
            Route::patch('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
            Route::get('myinvois/workshop-profile', [MyInvoisPreparationController::class, 'editSeller'])->name('myinvois.seller.edit');
            Route::put('myinvois/workshop-profile', [MyInvoisPreparationController::class, 'updateSeller'])->name('myinvois.seller.update');
            Route::get('invoices/{invoice}/myinvois', [MyInvoisPreparationController::class, 'editBuyer'])->name('myinvois.buyer.edit');
            Route::put('invoices/{invoice}/myinvois', [MyInvoisPreparationController::class, 'updateBuyer'])->name('myinvois.buyer.update');
            Route::delete('invoices/{invoice}/myinvois', [MyInvoisPreparationController::class, 'clearBuyer'])->name('myinvois.buyer.clear');
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
            Route::get('invoices/{invoice}/payments/{payment}/receipt', [InvoiceController::class, 'receipt'])->name('invoices.payments.receipt');
            Route::get('payment-preferences', [PaymentPreferenceController::class, 'edit'])->name('payment-preferences.edit');
            Route::put('payment-preferences', [PaymentPreferenceController::class, 'update'])->name('payment-preferences.update');
            Route::post('payment-preferences/stripe-sandbox', [StripeSandboxController::class, 'start'])
                ->middleware('throttle:6,1')->name('stripe-sandbox.start');
            Route::get('payment-preferences/stripe-sandbox/result', [StripeSandboxController::class, 'result'])
                ->middleware('throttle:12,1')->name('stripe-sandbox.result');
            Route::post('payment-preferences/toyyibpay-sandbox', [ToyyibPaySandboxController::class, 'start'])
                ->middleware('throttle:6,1')->name('toyyibpay-sandbox.start');
            Route::get('payment-preferences/toyyibpay-sandbox/result', [ToyyibPaySandboxController::class, 'result'])
                ->middleware('throttle:12,1')->name('toyyibpay-sandbox.result');
            Route::post('payment-preferences/billplz-sandbox', [BillplzSandboxController::class, 'start'])
                ->middleware('throttle:6,1')->name('billplz-sandbox.start');
            Route::get('payment-preferences/billplz-sandbox/result', [BillplzSandboxController::class, 'result'])
                ->middleware('throttle:12,1')->name('billplz-sandbox.result');
            Route::resource('job-cards', JobCardController::class)->only(['index', 'create', 'store', 'show']);
            Route::post('job-cards/{job_card}/notes', [JobCardController::class, 'addNote'])->name('job-cards.notes.store');
            Route::post('job-cards/{job_card}/parts', [JobCardController::class, 'usePart'])
                ->middleware(EnsureInventoryAccess::class)->name('job-cards.parts.store');
            Route::post('job-cards/{job_card}/parts/{part}/returns', [JobCardController::class, 'returnPart'])
                ->middleware(EnsureInventoryAccess::class)->name('job-cards.parts.return');
            Route::patch('job-cards/{job_card}/status', [JobCardController::class, 'advance'])->name('job-cards.advance');
            Route::patch('job-cards/{job_card}/assignment', [JobCardController::class, 'assign'])->name('job-cards.assign');
            Route::middleware(EnsureInventoryAccess::class)->group(function () {
                Route::resource('inventory', InventoryController::class)->except(['destroy']);
                Route::post('inventory/{inventory}/movements', [InventoryController::class, 'move'])->name('inventory.move');
                Route::resource('suppliers', SupplierController::class)->except(['show', 'destroy']);
                Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show']);
                Route::post('purchase-orders/{purchase_order}/receipts', [PurchaseOrderController::class, 'receive'])
                    ->name('purchase-orders.receive');
                Route::patch('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])
                    ->name('purchase-orders.cancel');
            });
            Route::prefix('calendar')->name('calendar.')->group(function () {
                Route::get('/', [CalendarController::class, 'index'])->name('index');
                Route::get('create', [CalendarController::class, 'create'])->name('create');
                Route::post('appointments', [CalendarController::class, 'store'])->name('store');
                Route::get('appointments/{appointment}/edit', [CalendarController::class, 'edit'])->name('edit');
                Route::put('appointments/{appointment}', [CalendarController::class, 'update'])->name('update');
                Route::patch('appointments/{appointment}/cancel', [CalendarController::class, 'cancel'])->name('cancel');
            });
        });

        Route::middleware(['role:client', EnsureWorkshopContext::class])
            ->prefix('my-account')->name('client.')->group(function () {
                Route::get('/', [CustomerPortalController::class, 'index'])->name('portal');
                Route::get('vehicles/create', [CustomerPortalController::class, 'createVehicle'])->name('vehicle.create');
                Route::post('vehicles', [CustomerPortalController::class, 'storeVehicle'])->name('vehicles.store');
                Route::get('vehicles/{vehicle}', [CustomerPortalController::class, 'history'])->name('vehicles.history');
                Route::get('vehicle-requests/create', [CustomerPortalController::class, 'createVehicleRequest'])
                    ->name('vehicle-requests.create');
                Route::post('vehicle-requests', [CustomerPortalController::class, 'storeVehicleRequest'])
                    ->name('vehicle-requests.store');
            });

        Route::middleware(['role:admin', EnsureWorkshopContext::class])->group(function () {
            Route::resource('users', UserController::class);

            Route::prefix('admin')->name('admin.')->group(function () {
                Route::get('/', [AdminController::class, 'index'])->name('settings');
                Route::prefix('/roles')->name('roles.')->group(function () {
                    Route::get('/', [RoleController::class, 'index'])->name('index');
                });
            });
        });

    });
});
