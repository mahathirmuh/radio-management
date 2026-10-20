<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('auth.components.license.license')
<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>{{ config('app.name') }} | Merdeka Copper Gold</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    <link rel="shortcut icon" href="{{ asset('assets/img/branding/favicon.ico') }}" /> 
    {{-- Main Style --}}
    @include('auth.components.style.style')  
    <meta name="theme-color" content="#0d6efd">
</head>
<body>
    @include('sweetalert::alert')
        {{-- Main Page --}}
        @yield('content')
        {{-- End OF Main Page --}}
    {{-- Main Javasript --}}
    @include('auth.components.js.js')
</body>

</html>
