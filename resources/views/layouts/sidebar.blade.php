<!-- Sidebar -->
@php
    $user = auth()->user();
    $isSuperAdmin = $user?->isSuperAdmin() ?? false;
    $homeRoute = $isSuperAdmin
        ? 'super-admin.dashboard'
        : ($user?->role === \App\Models\User::ROLE_CLIENT ? 'client.portal' : 'dashboard');
    $accountSubscription = request()->attributes->get('account_subscription');
    $subscriptionUsable = request()->attributes->get('subscription_usable');
    if ($subscriptionUsable === null && $user && in_array($user->role, [
        \App\Models\User::ROLE_ADMIN,
        \App\Models\User::ROLE_ACCOUNT_SUPER_ADMIN,
        \App\Models\User::ROLE_MECHANIC,
    ], true)) {
        $accountSubscription = app(\App\Services\SubscriptionEntitlementService::class)->subscriptionFor($user);
        $subscriptionUsable = $accountSubscription?->isUsable() ?? false;
    }
    $subscriptionUsable ??= true;
@endphp
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route($homeRoute) }}">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-tools"></i>
        </div>
        <div class="sidebar-brand-text mx-3">Workshop Invoicing</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <li class="nav-item">
        <a class="nav-link" href="{{ route($homeRoute) }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    @if ($isSuperAdmin)
        <!-- Divider -->
        <hr class="sidebar-divider">

        <div class="sidebar-heading">
            Platform Management
    </div>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('super-admin.owners.index') }}">
                <i class="fas fa-users-cog"></i>
                <span>Workshop Owners</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('super-admin.impersonation.index') }}">
                <i class="fas fa-user-secret"></i>
                <span>Switch to Workshop</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('super-admin.workshops.index') }}">
                <i class="fas fa-warehouse"></i>
                <span>Workshops</span>
            </a>
        </li>
    @elseif ($user && $user->isAccountSuperAdmin())
        <hr class="sidebar-divider">
        <div class="sidebar-heading">
            Account Management
        </div>
        @if ($subscriptionUsable)
            <li class="nav-item">
                <a class="nav-link" href="{{ route('account.workshops.index') }}">
                    <i class="fas fa-warehouse"></i>
                    <span>My Workshops</span>
                </a>
            </li>
        @endif
        <li class="nav-item">
            <a class="nav-link" href="{{ route('account.subscription') }}">
                <i class="fas fa-credit-card"></i>
                <span>My Subscription</span>
            </a>
        </li>
    @elseif ($subscriptionUsable)
        <!-- Divider -->
        <hr class="sidebar-divider">

        <div class="sidebar-heading">
            Modules
        </div>

        @if ($user?->role === \App\Models\User::ROLE_ADMIN)
            <li class="nav-item">
                <a class="nav-link" href="{{ route('invoices.index') }}">
                    <i class="fas fa-file-invoice"></i>
                    <span>Invoices</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('payment-preferences.edit') }}">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('customers.index') }}">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('client-accounts.index') }}">
                    <i class="fas fa-user-check"></i>
                    <span>Client accounts</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('staff.index') }}">
                    <i class="fas fa-user-cog"></i>
                    <span>Staff access</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('vehicles.index') }}">
                    <i class="fas fa-car"></i>
                    <span>Vehicles</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('calendar.index') }}">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('job-cards.index') }}">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Job cards</span>
                </a>
            </li>
            @if ($accountSubscription?->canManageInventory())
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('inventory.index') }}">
                        <i class="fas fa-warehouse"></i>
                        <span>Inventory</span>
                    </a>
                </li>
            @endif
        @elseif ($user?->role === \App\Models\User::ROLE_MECHANIC && $user->status === \App\Models\User::STATUS_ACTIVE)
            <li class="nav-item">
                <a class="nav-link" href="{{ route('my-jobs.index') }}">
                    <i class="fas fa-clipboard-list"></i>
                    <span>My jobs</span>
                </a>
            </li>
            @if ($user->can_view_customers)
                <li class="nav-item"><a class="nav-link" href="{{ route('customers.index') }}"><i class="fas fa-users"></i> <span>Customers</span></a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('vehicles.index') }}"><i class="fas fa-car"></i> <span>Vehicles</span></a></li>
            @endif
        @elseif ($user?->role === \App\Models\User::ROLE_CLIENT)
            <li class="nav-item">
                <a class="nav-link" href="{{ route('client.portal') }}">
                    <i class="fas fa-car"></i>
                    <span>My vehicles &amp; history</span>
                </a>
            </li>
        @endif

        @if ($user?->role === \App\Models\User::ROLE_ADMIN)
            <li class="nav-item">
                <a class="nav-link" href="{{ route('users.index') }}">
                    <i class="fas fa-user-cog"></i>
                    <span>Users</span>
                </a>
            </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Admin Settings
            </div>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.settings') }}">
                    <i class="fas fa-cogs"></i>
                    <span>Admin Settings</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.roles.index') }}">
                    <i class="fas fa-user-shield"></i>
                    <span>Role Management</span>
                </a>
            </li>
        @endif

        @if ($user && $user->role === \App\Models\User::ROLE_ADMIN)
            <li class="nav-item">
                <a class="nav-link" href="{{ route('workshop.subscription') }}">
                    <i class="fas fa-credit-card"></i>
                    <span>My Subscription</span>
                </a>
            </li>
        @endif
    @elseif ($user && $user->role === \App\Models\User::ROLE_ADMIN)
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Subscription</div>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('workshop.subscription') }}">
                <i class="fas fa-credit-card"></i>
                <span>My Subscription</span>
            </a>
        </li>
    @endif

    <!-- Divider -->
    <hr class="sidebar-divider">

    <li class="nav-item">
        <a class="nav-link" href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->
