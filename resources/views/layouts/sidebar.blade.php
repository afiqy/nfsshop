<!-- Sidebar -->
@php($user = auth()->user())
@php($isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route($isSuperAdmin ? 'super-admin.dashboard' : 'dashboard') }}">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-tools"></i>
        </div>
        <div class="sidebar-brand-text mx-3">Workshop Invoicing</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <li class="nav-item">
        <a class="nav-link" href="{{ route($isSuperAdmin ? 'super-admin.dashboard' : 'dashboard') }}">
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
        <li class="nav-item">
            <a class="nav-link" href="{{ route('super-admin.billing.dashboard') }}">
                <i class="fas fa-credit-card"></i>
                <span>Billing & Plans</span>
            </a>
        </li>
    @else
        <!-- Divider -->
        <hr class="sidebar-divider">

        <div class="sidebar-heading">
            Modules
        </div>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('invoices.index') }}">
                <i class="fas fa-file-invoice"></i>
                <span>Invoices</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('customers.index') }}">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('vehicles.index') }}">
                <i class="fas fa-car"></i>
                <span>Vehicles</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('inventory.index') }}">
                <i class="fas fa-warehouse"></i>
                <span>Inventory</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('users.index') }}">
                <i class="fas fa-user-cog"></i>
                <span>Users</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('calendar.index') }}">
                <i class="fas fa-calendar-alt"></i>
                <span>Calendar</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="{{ route('tasks.index') }}">
                <i class="fas fa-tasks"></i>
                <span>Tasks</span>
            </a>
        </li>

        <!-- Divider -->
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
