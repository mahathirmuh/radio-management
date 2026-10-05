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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />
    <link rel="shortcut icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    <!--  Main Style -->
    @include('main.components.style.style')
    <meta name="theme-color" content="#0d6efd">
</head>
    
<body class="layout-fluid">

    <div id="loader">
        {{-- @include('main.loader.loader') --}}
    </div>
    <div class="page">
        <!--  Sidebar -->
        @include('main.sidebar.sidebar')
        <!--  Navbar -->
        @include('main.navbar.navbar')

        <div class="page-wrapper">
            <!-- Page header -->

            {{-- @include('main.header.header') --}}

            <!--  Main Page -->
            <div class="page-body mt-1">
                <div class="container-xl" style="max-width: 1440px">
                    @yield('content')
                </div>
            </div>
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
