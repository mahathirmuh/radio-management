@extends('auth.main.app')
@section('content')
    <section class="h-100 gradient-form">
        <div class="container py-5 h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-xl-10">
                    <div class="card rounded-4 text-black"
                        style="box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.55); border-radius: 20px; overflow: hidden;">
                        <div class="row g-0">
                            {{-- An oil painting of a Merdeka Battery Materials processing plant, made from the company's own
                                 aerial photograph; the page behind it is set in auth.components.style.style. --}}
                            <div class="col-lg-7 login-picture"
                                style="background-image: url({{ asset('assets/img/bg-auth/mbma-operations.webp') }})"></div>
                            <div class="col-lg-5 d-flex flex-column justify-content-between">
                                <div class="p-md-5 mx-md-3 mb-0 d-flex justify-content-between align-items-center">
                                    <img src="{{ asset('assets/img/branding/mcg-logo.png') }}" height="38px"
                                        alt="">

                                    <img src="{{ asset('assets/img/branding/mbm-logo.jpg') }}" height="56px"
                                        alt="">
                                </div>
                                <div class="card-body px-md-5 pt-0 mx-md-3">
                                    <div class="text-center mt-4">
                                        <h1 class=" text-secondary">{{ config('app.name') }}
                                        </h1>
                                    </div>
                                    <hr class="my-3">
                                    <div class="text-center mb-3">
                                        <span class="mb-2 text-muted w-100" style="font-size: 15px">
                                            Sign In with SSO</span>
                                    </div>

                                    <form id="formAuthentication" class="mt-3" action="{{ route('login') }}"
                                        method="POST">
                                        @csrf
                                        <div class="d-flex flex-column flex-lg-row justify-content-center gap-3 mb-2">
                                            <a href="{{ route('sso-login-mirosoft') }}"
                                                class="btn btn-login btn-lg p-3 d-flex flex-row gap-2"
                                                style="width: 120px;">
                                                <img src="{{ asset('assets/img/logo/microsoft.png') }}" class="rounded-top"
                                                    width="24" alt="">
                                                <span class="h4 mb-0">Microsoft</span>
                                            </a>
                                        </div>
                                    </form>
                                    <div class="hr-text my-4"> <span class="h6 mb-0"> Or </span> </div>
                                    <div class="text-center">
                                        <span class="text-muted w-100" style="font-size: 15px">
                                            Sign in with Merdeka Account</span>
                                    </div> 
                                    <form id="loginForm" class="p-3 rounded" method="POST" action="{{ route('login') }}">
                                        @csrf
                                        <div class="row g-3">
                                            <div class="col-12">
                                                {{-- Not type="email": an account that signs in with its Active Directory
                                                     password may be named by its directory username, which has no "@". --}}
                                                <label for="employeeId" class="form-label  ">Email or username</label>
                                                <input type="text" class="form-control" id="employeeId"
                                                    name="emailusername" placeholder="Email or username"
                                                    autocomplete="username" autocapitalize="none" spellcheck="false"
                                                    required>
                                            </div>
                                            <div class="col-12">
                                                {{-- No "Forgot Password": the password of a directory account is the one of
                                                     Active Directory, and this application resets none. --}}
                                                <label for="password" class="form-label  ">Password</label>
                                                <input type="password" class="form-control" id="password"
                                                    name="password" placeholder="Password" required>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary w-100">Login</button>
                                            </div>
                                        </div>
                                    </form>
                                    <br>
                                    <div class="text-center">
                                        Access Login Guide :
                                         <button
                                            class="btn btn-sm btn-info rounded" data-bs-toggle="modal"
                                            data-bs-target="#accessLoginGuide">
                                            Click
                                            Here</button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-center pb-2">
                                    <small class="text-muted">© Merdeka Group {{ date('Y') }}</small>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Modal -->
    <div class="modal fade" id="accessLoginGuide" tabindex="-1" aria-labelledby="accessLoginGuide" aria-hidden="true">
        {{-- Microsoft only: this application has no Google sign-in (the card for Merdeka Copper Gold Group was removed). --}}
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-body p-3">
                    <div class="row row-deck">
                        <div class="col ">
                            <div class="px-3 py-5 w-100  border rounded d-flex flex-column gap-3">
                                <div class="d-flex justify-content-center align-items-center gap-2">
                                    <div>
                                        <img src="{{ asset('assets/img/branding/mbm-logo.png') }}" height="42px"
                                            alt="">
                                    </div>
                                    <div><span class="display-5 text-secondary fw-light">|</span></div>
                                    <div>
                                        <img src="{{ asset('assets/img/logo/microsoft.png') }}" class="rounded-top"
                                            height="36px" alt="">
                                    </div>
                                </div>
                                <div class="text-center">
                                    Merdeka Battery Materials Group
                                    <br><span class="text-warning small">@merdekabattery.com</span>
                                    <br>access via
                                    <br> <span class="text-azure fw-bold">Microsoft SSO</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
