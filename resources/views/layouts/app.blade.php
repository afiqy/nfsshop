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
                    @error('mfa')
                        <div class="alert alert-danger" role="alert">{{ $message }} <a href="{{ route('authenticator.show') }}">Verify now</a></div>
                    @enderror
                    @if (auth()->user()?->mfa_secret)
                        <div class="alert alert-info"><a href="{{ route('authenticator.show') }}">Verify your authenticator</a> before protected actions.</div>
                    @endif
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
