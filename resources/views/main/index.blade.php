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
    @php
        // The heading of the page: its own (@section('heading')), else its title; in the top bar, and above the content on
        // a phone, where the top bar is hidden. (A section is kept escaped.)
        $heading = trim($__env->yieldContent('heading') ?: $__env->yieldContent('page-title'));
        // The path to the page, under the top bar: what the page calls itself (@section('crumb'), else its title) comes last
        // when it is deeper than its entry of the sidebar.
        $pageName = html_entity_decode(trim($__env->yieldContent('crumb') ?: $__env->yieldContent('page-title')), ENT_QUOTES | ENT_HTML5);
        $crumbs = \App\Services\Breadcrumbs::for(request(), Auth::user(), $pageName);
    @endphp

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
                    @hasSection('welcome')
                        {{-- A page that greets (the dashboard, whose path would be itself alone): the greeting where the path is. --}}
                        <p class="rm-title rm-welcome">@yield('welcome')</p>
                    @else
                        <nav class="rm-crumbbar" aria-label="{{ __('ui.topbar.breadcrumb') }}">
                            <ol>
                                @foreach ($crumbs as $crumb)
                                    <li>
                                        @unless ($loop->first)
                                            <i class="ti ti-chevron-right" aria-hidden="true"></i>
                                        @endunless
                                        @if ($crumb['url'] !== null)
                                            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                                        @else
                                            <span @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                        @if ($heading !== '')
                            <div class="rm-title-phone d-lg-none"><h1 class="rm-title">{!! $heading !!}</h1></div>
                        @endif
                    @endif
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
