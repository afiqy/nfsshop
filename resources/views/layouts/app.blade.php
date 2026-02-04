@include('layouts.header')

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        @include('layouts.sidebar') {{-- Include Sidebar if needed --}}
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Navbar -->
                @include('layouts.navbar')

                <!-- Page Content -->
                <div class="container-fluid">
                    @if (session()->has('impersonator_id'))
                        <div class="alert alert-warning d-flex align-items-center justify-content-between">
                            <div>
                                <strong>Impersonation active:</strong> You are browsing as another user.
                            </div>
                            <form action="{{ route('impersonation.leave') }}" method="POST" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-dark">Return to Super Admin</button>
                            </form>
                        </div>
                    @endif
                    @yield('content')
                </div>
            </div>
            <!-- End of Main Content -->
            
            @include('layouts.footer') {{-- Footer already exists --}}
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->
</body>

</html>
