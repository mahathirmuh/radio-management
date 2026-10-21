<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} | {{ __('errors.forbidden.title') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/branding/favicon.ico') }}" />
    <link href="{{ asset('assets/css/apps.min.css?1684106062') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/fonts/fonts.css') }}" rel="stylesheet" />
</head>

<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container-tight py-4">
            <div class="empty">
                <div class="empty-header">403</div>
                <p class="empty-title">{{ __('errors.forbidden.heading') }}</p>
                <p class="empty-subtitle text-secondary">
                    {{ $exception->getMessage() ?: __('errors.forbidden.reason') }}
                    {{ __('errors.forbidden.ask') }}
                </p>
                <div class="empty-action d-flex justify-content-center gap-2">
                    <a href="{{ url('/') }}" class="btn">{{ __('errors.forbidden.home') }}</a>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ __('errors.forbidden.sign_out') }}</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</body>

</html>
