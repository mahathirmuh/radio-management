<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('main.components.license.license')

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} | Merdeka Copper Gold</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    <link rel="shortcut icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    <!--  Main Style -->
    @include('main.components.style.style')
    <meta name="theme-color" content="#ffffff">
    <script>
        // The sidebar as the person left it (full width or the icon rail), before anything is drawn. main.sidebar.sidebar.
        try {
            if (window.localStorage.getItem('sidebarState') === 'collapsed') document.documentElement.classList.add('rm-rail');
        } catch (e) {
            // No storage (a private window): the full sidebar.
        }
    </script>
</head>

<body class="layout-fluid">

    <div id="loader">
        {{-- @include('main.loader.loader') --}}
    </div>
    <div class="page rm-page">
        <!--  Sidebar -->
        @include('main.sidebar.sidebar')
        <!--  Navbar -->
        @include('main.navbar.navbar')

        <div class="page-wrapper">
            <!-- Page header -->

            {{-- @include('main.header.header') --}}

            <!--  Main Page -->
            <main class="page-body rm-main">
                <div class="container-xl rm-container">
                    @yield('content')
                </div>
            </main>
            <!--  End OF Main Page -->
            @include('main.footer.footer')
        </div>
    </div>

    <!--  Main Javasript -->
    @include('sweetalert::alert')
    @include('main.components.js.js')

    <!-- Global Alert, Modal, and Dialog -->
    @include('main.modal.alert')
    @include('main.modal.modal')
</body>

</html>
