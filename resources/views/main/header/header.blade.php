@if (count($menuItems) > 1)
    <div class="page-header d-print-none">
        <div class="container-xl" style="max-width: 1440px">
            <div class="row g-2 align-items-center">
                {{-- <div class="col">
                <!-- Page pre-title -->
                <div class="page-pretitle">
                </div>
                <h2 class="page-title">
                </h2>
            </div>  --}}
                <!-- Page title actions -->
                <div class="col-auto d-print-none">
                    <div class="d-flex">
                        <div class="navigation-card pb-0">
                            <ol class="breadcrumb breadcrumb-arrows" aria-label="breadcrumbs">
                                @foreach ($menuItems as $key => $menuItem)
                                @if ($key === 0)
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="icon icon-tabler icon-tabler-home-move me-2" width="24"
                                            height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                            fill="none" stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2"></path>
                                            <path d="M19 12h2l-9 -9l-9 9h2v7a2 2 0 0 0 2 2h5.5"></path>
                                            <path d="M16 19h6"></path>
                                            <path d="M19 16l3 3l-3 3"></path>
                                        </svg>
                                    @endif
                                    <li
                                        class="breadcrumb-item {{ $menuItem['active'] ? 'active' : '' }} d-flex align-items-center">
                                        <b><a class="nav-link"
                                                href="{{ url($menuItem['url']) }}">{{ __($menuItem['label']) }}</a></b>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
