<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} | Akses ditolak</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    <link href="{{ asset('assets/css/apps.min.css?1684106062') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/fonts/fonts.css') }}" rel="stylesheet" />
</head>

<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="empty">
                <div class="empty-header">403</div>
                <p class="empty-title">Anda tidak punya akses ke halaman ini</p>
                <p class="empty-subtitle text-secondary">
                    {{ $exception->getMessage() ?: 'Role Anda belum diberi akses ke menu ini.' }}
                    Hubungi admin aplikasi bila akses ini memang diperlukan.
                </p>
                <div class="empty-action d-flex justify-content-center gap-2">
                    <a href="{{ url('/') }}" class="btn">Ke halaman utama</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Logout</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</body>

</html>
