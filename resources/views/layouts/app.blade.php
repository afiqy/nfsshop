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
