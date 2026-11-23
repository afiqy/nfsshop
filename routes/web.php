<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentRequestController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthenticatorController;
use App\Http\Controllers\BillplzSandboxController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientEngagementController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DeviceSessionController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceAdjustmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceEmailDeliveryController;
use App\Http\Controllers\InvoicePaymentSubmissionController;
use App\Http\Controllers\JobCardController;
use App\Http\Controllers\MyInvoisPreparationController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\PaymentPreferenceController;
use App\Http\Controllers\PaymentReminderController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\StaffInvoiceController;
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
use App\Http\Controllers\WorkshopExportController;
use App\Http\Controllers\WorkshopPaymentQrController;
use App\Http\Controllers\WorkshopReportController;
use App\Http\Middleware\EnsureAuthenticatorVerified;
use App\Http\Middleware\EnsureClientEngagementAccess;
use App\Http\Middleware\EnsureCustomerReadAccess;
use App\Http\Middleware\EnsureInventoryAccess;
use App\Http\Middleware\EnsureReportsAccess;
use App\Http\Middleware\EnsureWorkshopContext;
use App\Http\Middleware\EnsureWorkshopStaffPermission;
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
Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('/staff-invitations/{token}', [StaffInvitationController::class, 'show'])->name('staff-invitations.show');
    Route::post('/staff-invitations/{token}', [StaffInvitationController::class, 'accept'])->name('staff-invitations.accept');
});
Route::get('/register/complete', [RegistrationController::class, 'complete'])->name('register.complete');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'send'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])->name('password.update');
});

Route::middleware(['auth', 'check.lockout', 'session.timeout', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');

        Route::resource('owners', OwnerController::class)->except(['show'])->middleware(EnsureAuthenticatorVerified::class);
        Route::post('owners/{owner}/suspend', [OwnerController::class, 'suspend'])->middleware(EnsureAuthenticatorVerified::class)->name('owners.suspend');
        Route::post('owners/{owner}/activate', [OwnerController::class, 'activate'])->middleware(EnsureAuthenticatorVerified::class)->name('owners.activate');
        Route::post('owners/{owner}/reset-password', [OwnerController::class, 'resetPassword'])->middleware(EnsureAuthenticatorVerified::class)->name('owners.reset-password');
        Route::post('owners/{owner}/impersonate', [OwnerController::class, 'impersonate'])->middleware(EnsureAuthenticatorVerified::class)->name('owners.impersonate');

        Route::get('impersonation', [ImpersonationController::class, 'index'])->name('impersonation.index');
        Route::post('impersonation', [ImpersonationController::class, 'start'])->middleware(EnsureAuthenticatorVerified::class)->name('impersonation.start');

        Route::resource('workshops', WorkshopController::class)->only(['index', 'edit', 'update'])->middleware(EnsureAuthenticatorVerified::class);

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit.logs.export');
    });

Route::middleware(['auth', 'check.lockout', 'session.timeout'])->group(function () {
    Route::get('/staff-invitations/existing/{token}', [StaffInvitationController::class, 'showExisting'])->middleware('throttle:10,1')->name('staff-invitations.existing.show');
    Route::post('/staff-invitations/existing/{token}', [StaffInvitationController::class, 'acceptExisting'])->middleware('throttle:10,1')->name('staff-invitations.existing.accept');
    Route::get('devices', [DeviceSessionController::class, 'index'])->name('devices.index');
    Route::delete('devices/{device}', [DeviceSessionController::class, 'revoke'])->name('devices.revoke');
    Route::get('authenticator', [AuthenticatorController::class, 'show'])->name('authenticator.show');
    Route::post('authenticator/start', [AuthenticatorController::class, 'start'])->middleware('throttle:10,1')->name('authenticator.start');
    Route::post('authenticator/confirm', [AuthenticatorController::class, 'confirm'])->middleware('throttle:10,1')->name('authenticator.confirm');
    Route::post('authenticator/verify', [AuthenticatorController::class, 'verify'])->middleware('throttle:10,1')->name('authenticator.verify');
    Route::delete('authenticator', [AuthenticatorController::class, 'disable'])->middleware('throttle:10,1')->name('authenticator.disable');
    Route::post('impersonation/leave', [OwnerController::class, 'leaveImpersonation'])->name('impersonation.leave');
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/edit', [UserController::class, 'editProfile'])->name('edit');
        Route::put('/edit', [UserController::class, 'updateProfile'])->name('update');
        Route::get('/avatar', [UserController::class, 'avatar'])->name('avatar');
        Route::post('/email', [EmailChangeController::class, 'requestChange'])->middleware('throttle:5,1')->name('email.request');
        Route::get('/email/confirm/{token}', [EmailChangeController::class, 'show'])->name('email.confirm');
        Route::post('/email/confirm', [EmailChangeController::class, 'confirm'])->middleware('throttle:10,1')->name('email.confirm.submit');
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
                ->only(['index', 'create', 'store', 'edit', 'update'])->middleware(EnsureAuthenticatorVerified::class);
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
        Route::middleware([EnsureWorkshopContext::class, EnsureCustomerReadAccess::class])->group(function () {
            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        });
        Route::middleware([EnsureWorkshopContext::class, EnsureWorkshopStaffPermission::class.':can_create_customer_records'])->group(function () {
            Route::resource('customers', CustomerController::class)->only(['create', 'store']);
            Route::resource('vehicles', VehicleController::class)->only(['create', 'store']);
        });
        Route::get('vehicles/{vehicle}/history', [VehicleController::class, 'history'])
            ->middleware([EnsureWorkshopContext::class, EnsureWorkshopStaffPermission::class.':can_view_vehicle_history'])->name('vehicles.history');
        Route::middleware([EnsureWorkshopContext::class, EnsureWorkshopStaffPermission::class.':can_review_appointments'])->group(function () {
            Route::get('appointment-requests', [AppointmentRequestController::class, 'index'])->name('appointment-requests.index');
            Route::patch('appointment-requests/{appointmentRequest}/confirm', [AppointmentRequestController::class, 'confirm'])->name('appointment-requests.confirm');
            Route::patch('appointment-requests/{appointmentRequest}/reject', [AppointmentRequestController::class, 'reject'])->name('appointment-requests.reject');
            Route::post('appointment-notifications/{notification}/retry', [AppointmentRequestController::class, 'retryNotification'])
                ->middleware('throttle:10,1')->name('appointment-notifications.retry');
            Route::patch('appointment-notifications/{notification}/resolve', [AppointmentRequestController::class, 'resolveNotification'])
                ->middleware('throttle:10,1')->name('appointment-notifications.resolve');
        });
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
        Route::middleware(['role:mechanic', EnsureWorkshopContext::class])->prefix('staff-invoices')->name('staff-invoices.')->group(function () {
            Route::get('/', [StaffInvoiceController::class, 'index'])->name('index');
            Route::get('jobs/{job}/create', [StaffInvoiceController::class, 'create'])->name('create');
            Route::post('jobs/{job}', [StaffInvoiceController::class, 'store'])->name('store');
            Route::get('{invoice}', [StaffInvoiceController::class, 'show'])->name('show');
            Route::post('{invoice}/payments', [StaffInvoiceController::class, 'recordPayment'])
                ->middleware(EnsureAuthenticatorVerified::class)->name('payments.store');
        });

        Route::middleware(['role:admin', EnsureWorkshopContext::class])->group(function () {
            Route::middleware(EnsureClientEngagementAccess::class)->prefix('engagement')->name('engagement.')->group(function () {
                Route::get('/', [ClientEngagementController::class, 'index'])->name('index');
                Route::get('customers/{customer}', [ClientEngagementController::class, 'customer'])->name('customers.show');
                Route::post('customers/{customer}/points', [ClientEngagementController::class, 'points'])->name('points');
                Route::put('customers/{customer}/consent', [ClientEngagementController::class, 'consent'])->name('consent');
                Route::post('promotions', [ClientEngagementController::class, 'promotion'])->name('promotions.store');
                Route::put('promotions/{promotion}', [ClientEngagementController::class, 'promotion'])->name('promotions.update');
                Route::post('reminders', [ClientEngagementController::class, 'reminder'])->name('reminders.store');
                Route::patch('reminders/{reminder}/complete', [ClientEngagementController::class, 'complete'])->name('reminders.complete');
                Route::post('reminders/{reminder}/send', [ClientEngagementController::class, 'send'])->middleware('throttle:10,1')->name('reminders.send');
                Route::patch('reminders/{reminder}/attempts/{attempt}/resolve', [ClientEngagementController::class, 'resolve'])->name('reminders.resolve');
            });
            Route::get('reports', [WorkshopReportController::class, 'index'])
                ->middleware(EnsureReportsAccess::class)->name('reports.index');
            Route::get('reports/export/{format}', [WorkshopReportController::class, 'index'])
                ->whereIn('format', ['csv', 'pdf'])->middleware(EnsureReportsAccess::class)->name('reports.export');
            Route::get('exports/{type}/{format}', [WorkshopExportController::class, 'index'])
                ->whereIn('type', ['invoices', 'payments', 'jobs'])->whereIn('format', ['csv', 'pdf'])->name('exports.index');
            Route::get('services', [ServiceCatalogController::class, 'index'])->name('services.index');
            Route::get('services/create', [ServiceCatalogController::class, 'serviceForm'])->name('services.create');
            Route::post('services', [ServiceCatalogController::class, 'saveService'])->name('services.store');
            Route::get('services/{service}/edit', [ServiceCatalogController::class, 'serviceForm'])->name('services.edit');
            Route::put('services/{service}', [ServiceCatalogController::class, 'saveService'])->name('services.update');
            Route::put('service-tax', [ServiceCatalogController::class, 'updateTax'])->name('services.tax');
            Route::get('service-packages/create', [ServiceCatalogController::class, 'packageForm'])->name('service-packages.create');
            Route::post('service-packages', [ServiceCatalogController::class, 'savePackage'])->name('service-packages.store');
            Route::get('service-packages/{package}/edit', [ServiceCatalogController::class, 'packageForm'])->name('service-packages.edit');
            Route::put('service-packages/{package}', [ServiceCatalogController::class, 'savePackage'])->name('service-packages.update');
            Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
            Route::post('staff/invitations', [StaffController::class, 'invite'])->middleware(['throttle:10,1', EnsureAuthenticatorVerified::class])->name('staff.invite');
            Route::delete('staff/invitations/{invitation}', [StaffController::class, 'revoke'])->middleware(EnsureAuthenticatorVerified::class)->name('staff.invitations.revoke');
            Route::patch('staff/{staff}', [StaffController::class, 'update'])->middleware(EnsureAuthenticatorVerified::class)->name('staff.update');
            Route::patch('staff-requests/{staff}', [StaffController::class, 'decideRequest'])->middleware(EnsureAuthenticatorVerified::class)->name('staff.requests.decide');
            Route::resource('customers', CustomerController::class)->only(['edit', 'update']);
            Route::resource('vehicles', VehicleController::class)->only(['edit', 'update']);
            Route::get('client-accounts', [CustomerAccountController::class, 'index'])->name('client-accounts.index');
            Route::post('client-accounts/{user}/activate', [CustomerAccountController::class, 'activate'])->name('client-accounts.activate');
            Route::patch('client-vehicle-requests/{vehicleRequest}/approve', [CustomerAccountController::class, 'approveVehicleRequest'])
                ->name('client-vehicle-requests.approve');
            Route::patch('client-vehicle-requests/{vehicleRequest}/reject', [CustomerAccountController::class, 'rejectVehicleRequest'])
                ->name('client-vehicle-requests.reject');
            Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
            Route::put('payment-reminders/settings', [PaymentReminderController::class, 'update'])
                ->name('payment-reminders.settings');
            Route::post('invoices/{invoice}/payment-reminders/{reminder}/retry', [PaymentReminderController::class, 'retry'])
                ->name('invoices.payment-reminders.retry');
            Route::patch('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
            Route::post('invoices/{invoice}/email-deliveries', [InvoiceEmailDeliveryController::class, 'store'])
                ->name('invoices.email-deliveries.store');
            Route::post('invoices/{invoice}/email-deliveries/{delivery}/retry', [InvoiceEmailDeliveryController::class, 'retry'])
                ->name('invoices.email-deliveries.retry');
            Route::get('myinvois/workshop-profile', [MyInvoisPreparationController::class, 'editSeller'])->name('myinvois.seller.edit');
            Route::put('myinvois/workshop-profile', [MyInvoisPreparationController::class, 'updateSeller'])->name('myinvois.seller.update');
            Route::get('invoices/{invoice}/myinvois', [MyInvoisPreparationController::class, 'editBuyer'])->name('myinvois.buyer.edit');
            Route::put('invoices/{invoice}/myinvois', [MyInvoisPreparationController::class, 'updateBuyer'])->name('myinvois.buyer.update');
            Route::delete('invoices/{invoice}/myinvois', [MyInvoisPreparationController::class, 'clearBuyer'])->name('myinvois.buyer.clear');
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->middleware(EnsureAuthenticatorVerified::class)->name('invoices.payments.store');
            Route::get('invoices/{invoice}/payments/{payment}/receipt', [InvoiceController::class, 'receipt'])->name('invoices.payments.receipt');
            Route::post('invoices/{invoice}/payments/{payment}/refunds', [InvoiceAdjustmentController::class, 'storeRefund'])
                ->middleware(EnsureAuthenticatorVerified::class)->name('invoices.payments.refunds.store');
            Route::get('invoices/{invoice}/refunds/{refund}', [InvoiceAdjustmentController::class, 'showRefund'])
                ->name('invoices.refunds.show');
            Route::post('invoices/{invoice}/credit-notes', [InvoiceAdjustmentController::class, 'storeCreditNote'])
                ->middleware(EnsureAuthenticatorVerified::class)->name('invoices.credit-notes.store');
            Route::get('invoices/{invoice}/credit-notes/{creditNote}', [InvoiceAdjustmentController::class, 'showCreditNote'])
                ->name('invoices.credit-notes.show');
            Route::post('invoices/{invoice}/payment-submissions', [InvoicePaymentSubmissionController::class, 'adminRecord'])
                ->middleware(EnsureAuthenticatorVerified::class)->name('invoices.payment-submissions.store');
            Route::post('invoices/{invoice}/payment-submissions/{submission}/approve', [InvoicePaymentSubmissionController::class, 'approve'])
                ->middleware(EnsureAuthenticatorVerified::class)->name('invoices.payment-submissions.approve');
            Route::post('invoices/{invoice}/payment-submissions/{submission}/reject', [InvoicePaymentSubmissionController::class, 'reject'])
                ->middleware(EnsureAuthenticatorVerified::class)->name('invoices.payment-submissions.reject');
            Route::get('invoices/{invoice}/payment-submissions/{submission}/proof', [InvoicePaymentSubmissionController::class, 'adminProof'])
                ->name('invoices.payment-submissions.proof');
            Route::get('payment-submissions', [InvoicePaymentSubmissionController::class, 'adminIndex'])
                ->name('payment-submissions.index');
            Route::get('payment-preferences', [PaymentPreferenceController::class, 'edit'])->name('payment-preferences.edit');
            Route::put('payment-preferences', [PaymentPreferenceController::class, 'update'])->middleware(EnsureAuthenticatorVerified::class)->name('payment-preferences.update');
            Route::get('payment-qrs', [WorkshopPaymentQrController::class, 'index'])->name('payment-qrs.index');
            Route::post('payment-qrs', [WorkshopPaymentQrController::class, 'store'])->middleware(EnsureAuthenticatorVerified::class)->name('payment-qrs.store');
            Route::patch('payment-qrs/{qr}', [WorkshopPaymentQrController::class, 'setActive'])->middleware(EnsureAuthenticatorVerified::class)->name('payment-qrs.active');
            Route::get('payment-qrs/{qr}/image', [WorkshopPaymentQrController::class, 'adminImage'])
                ->name('payment-qrs.image');
            Route::post('payment-preferences/stripe-sandbox', [StripeSandboxController::class, 'start'])
                ->middleware(['throttle:6,1', EnsureAuthenticatorVerified::class])->name('stripe-sandbox.start');
            Route::get('payment-preferences/stripe-sandbox/result', [StripeSandboxController::class, 'result'])
                ->middleware('throttle:12,1')->name('stripe-sandbox.result');
            Route::post('payment-preferences/toyyibpay-sandbox', [ToyyibPaySandboxController::class, 'start'])
                ->middleware(['throttle:6,1', EnsureAuthenticatorVerified::class])->name('toyyibpay-sandbox.start');
            Route::get('payment-preferences/toyyibpay-sandbox/result', [ToyyibPaySandboxController::class, 'result'])
                ->middleware('throttle:12,1')->name('toyyibpay-sandbox.result');
            Route::post('payment-preferences/billplz-sandbox', [BillplzSandboxController::class, 'start'])
                ->middleware(['throttle:6,1', EnsureAuthenticatorVerified::class])->name('billplz-sandbox.start');
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
                Route::get('engagement', [ClientEngagementController::class, 'portal'])
                    ->middleware(EnsureClientEngagementAccess::class)->name('engagement');
                Route::put('reminder-preference', [ClientEngagementController::class, 'portalConsent'])->name('reminder-preference');
                Route::get('appointment-requests', [AppointmentRequestController::class, 'customerIndex'])->name('appointment-requests.index');
                Route::post('appointment-requests', [AppointmentRequestController::class, 'store'])->middleware('throttle:10,1')->name('appointment-requests.store');
                Route::put('appointment-requests/{appointmentRequest}', [AppointmentRequestController::class, 'customerUpdate'])->middleware('throttle:10,1')->name('appointment-requests.update');
                Route::patch('appointment-requests/{appointmentRequest}/cancel', [AppointmentRequestController::class, 'customerCancel'])->middleware('throttle:10,1')->name('appointment-requests.cancel');
                Route::put('appointments/{appointment}', [AppointmentRequestController::class, 'updateAppointment'])->middleware('throttle:10,1')->name('appointments.update');
                Route::patch('appointments/{appointment}/cancel', [AppointmentRequestController::class, 'cancelAppointment'])->middleware('throttle:10,1')->name('appointments.cancel');
                Route::get('invoices', [InvoicePaymentSubmissionController::class, 'customerIndex'])->name('invoices.index');
                Route::get('invoices/{invoice}', [InvoicePaymentSubmissionController::class, 'customerShow'])
                    ->name('invoices.show');
                Route::post('invoices/{invoice}/payment-submissions', [InvoicePaymentSubmissionController::class, 'customerSubmit'])
                    ->middleware('throttle:10,1')->name('invoices.payment-submissions.store');
                Route::get('invoices/{invoice}/payment-submissions/{submission}/proof', [InvoicePaymentSubmissionController::class, 'customerProof'])
                    ->name('invoices.payment-submissions.proof');
                Route::get('payment-qrs/{qr}/image', [WorkshopPaymentQrController::class, 'customerImage'])
                    ->name('payment-qrs.image');
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
