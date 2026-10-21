@extends('auth.main.app')
@push('style')
    <style>
        /* The card on the darkened photo (the photo is the body's, auth.components.style): the photos at the left, the
           sign-in at the right, as large as the card was before the design refresh (538.88 + 382.77 wide, 687.98 high);
           a narrower window shrinks both in that proportion. */
        .rm-login {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 40px;
        }

        .rm-login-card {
            display: grid;
            grid-template-columns: minmax(0, 538.88fr) minmax(0, 382.77fr);
            width: 921.65px;
            max-width: 100%;
            min-height: 687.98px;
            overflow: hidden;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .55);
        }

        /* Four photos that fade one into the next (24 s for all four). */
        .rm-login-photos {
            position: relative;
            min-height: 687.98px;
            overflow: hidden;
            background: var(--rm-gray-200);
        }

        .rm-login-photos img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            animation: rm-xfade 24s linear infinite;
        }

        .rm-login [data-xf="0"] {
            animation-delay: -0.72s;
        }

        .rm-login [data-xf="1"] {
            animation-delay: 6s;
        }

        .rm-login [data-xf="2"] {
            animation-delay: 12s;
        }

        .rm-login [data-xf="3"] {
            animation-delay: 18s;
        }

        @media (prefers-reduced-motion: reduce) {
            .rm-login [data-xf] {
                animation: none !important;
                opacity: 0 !important;
            }

            .rm-login [data-xf="0"] {
                opacity: 1 !important;
            }
        }

        .rm-login-side {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 20px;
            padding: 32px 44px 16px;
        }

        .rm-login-logos {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .rm-login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .rm-login-title {
            margin: 0;
            color: var(--rm-gray-600);
            font-size: 28px;
            line-height: 36px;
            font-weight: 600;
            text-align: center;
        }

        .rm-login-rule {
            height: 1px;
            background: var(--rm-gray-200);
        }

        .rm-login-hint {
            color: var(--rm-gray-500);
            font-size: 14px;
            text-align: center;
        }

        .rm-ms-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            height: 48px;
            padding: 0 20px;
            border-radius: 8px;
            background: #fff;
            box-shadow: inset 0 0 0 1px var(--rm-gray-300);
            color: var(--rm-gray-900);
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            transition: background-color .12s ease-out, box-shadow .12s ease-out;
        }

        .rm-ms-button:hover {
            background: #ecf5fc;
            box-shadow: inset 0 0 0 1px #4299e1;
            color: var(--rm-gray-900);
            text-decoration: none;
        }

        .rm-ms-button:active {
            background: #dbeafe;
        }

        /* The four squares of Microsoft (its colours, not tokens of the app). */
        .rm-ms-mark {
            display: inline-grid;
            grid-template-columns: 10px 10px;
            gap: 2px;
        }

        .rm-ms-mark > span {
            height: 10px;
        }

        .rm-ms-mark.lg {
            grid-template-columns: 16px 16px;
            gap: 3px;
        }

        .rm-ms-mark.lg > span {
            height: 16px;
        }

        .rm-ms-mark > span:nth-child(1) {
            background: #f25022;
        }

        .rm-ms-mark > span:nth-child(2) {
            background: #7fba00;
        }

        .rm-ms-mark > span:nth-child(3) {
            background: #00a4ef;
        }

        .rm-ms-mark > span:nth-child(4) {
            background: #ffb900;
        }

        .rm-login-or {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--rm-gray-400);
            font-size: 12px;
        }

        .rm-login-or::before,
        .rm-login-or::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--rm-gray-200);
        }

        .rm-login-submit {
            width: 100%;
            height: 44px;
        }

        .rm-login-guide {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--rm-gray-700);
            font-size: 13px;
        }

        .rm-login-guide button {
            padding: 4px 10px;
            border: 0;
            border-radius: 6px;
            background: var(--rm-sky-100);
            color: var(--rm-sky-700);
            font-weight: 500;
            transition: background-color .12s ease-out;
        }

        .rm-login-guide button:hover {
            background: #bae6fd;
        }

        .rm-login-foot {
            color: var(--rm-gray-500);
            font-size: 12px;
            text-align: center;
        }

        /* The guide: 440 wide, a cross at the top right, the logos and the words in a ringed box. */
        #accessLoginGuide .modal-dialog {
            max-width: 440px;
        }

        #accessLoginGuide .modal-content {
            position: relative;
            padding: 20px;
            border: 0;
            border-radius: 12px;
            box-shadow: var(--rm-shadow-xl);
        }

        #accessLoginGuide .rm-dialog-close {
            position: absolute;
            top: 12px;
            right: 12px;
        }

        .rm-guide-box {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
            padding: 40px 24px;
            border-radius: 8px;
            box-shadow: inset 0 0 0 1px var(--rm-gray-200);
            color: var(--rm-gray-900);
            font-size: 15px;
            line-height: 24px;
            text-align: center;
        }

        .rm-guide-logos {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
        }

        .rm-guide-domain {
            color: var(--rm-warning-700);
            font-size: 13px;
        }

        .rm-guide-sso {
            color: var(--rm-sky-700);
            font-weight: 700;
        }

        .rm-guide-logos > .rm-guide-pipe {
            color: var(--rm-gray-400);
            font-size: 32px;
            line-height: 1;
            font-weight: 300;
        }

        @media (max-width: 991.98px) {
            .rm-login {
                padding: 16px;
            }

            .rm-login-card {
                grid-template-columns: minmax(0, 1fr);
            }

            .rm-login-photos {
                display: none;
            }

            .rm-login-side {
                padding: 28px 20px 16px;
            }
        }
    </style>
@endpush
@section('content')
    <section class="rm-login">
        <div class="rm-login-card">
            <div class="rm-login-photos" aria-hidden="true">
                <img data-xf="0" src="{{ asset('assets/img/bg-auth/mdka-bg.jpg') }}" alt="">
                <img data-xf="1" src="{{ asset('assets/img/bg-auth/mdka-bg-1.jpg') }}" alt="">
                <img data-xf="2" src="{{ asset('assets/img/bg-auth/mdka-bg-2.jpg') }}" alt="">
                <img data-xf="3" src="{{ asset('assets/img/bg-auth/mdka-bg-3.jpg') }}" alt="">
            </div>

            <div class="rm-login-side">
                <div class="rm-login-logos">
                    <img src="{{ asset('assets/img/branding/mcg-logo.png') }}" height="36" alt="Merdeka Copper Gold">
                    <img src="{{ asset('assets/img/branding/mbm-logo.png') }}" height="40" alt="Merdeka Battery Materials">
                </div>

                <div class="rm-login-form">
                    <h1 class="rm-login-title">Radio Management System</h1>
                    <div class="rm-login-rule"></div>
                    <div class="rm-login-hint">Sign in with SSO</div>
                    <div class="d-flex justify-content-center">
                        <a href="{{ route('sso-login-mirosoft') }}" class="rm-ms-button">
                            <span class="rm-ms-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                            Microsoft
                        </a>
                    </div>
                    <div class="rm-login-or">Or</div>
                    <div class="rm-login-hint">Sign in with Merdeka Account</div>

                    <form id="loginForm" class="rm-login-form" method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="rm-field">
                            {{-- Not type="email": an account that signs in with its Active Directory password may be named
                                 by its directory username, which has no "@". --}}
                            <label for="employeeId" class="form-label">Email or username</label>
                            <input type="text" class="form-control" id="employeeId" name="emailusername"
                                placeholder="Email or username" autocomplete="username" autocapitalize="none"
                                spellcheck="false" required>
                        </div>
                        <div class="rm-field">
                            {{-- No "Forgot Password": the password of a directory account is the one of Active Directory,
                                 and this application resets none. --}}
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password"
                                autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn-rm primary rm-login-submit" id="loginSubmit">Login</button>
                    </form>

                    <div class="rm-login-guide">
                        Access Login Guide:
                        <button type="button" data-bs-toggle="modal" data-bs-target="#accessLoginGuide">Click here</button>
                    </div>
                </div>

                <div class="rm-login-foot">© Merdeka Group {{ date('Y') }}</div>
            </div>
        </div>
    </section>

    {{-- Microsoft only: this application has no Google sign-in (the card for Merdeka Copper Gold Group was removed). --}}
    <div class="modal fade" id="accessLoginGuide" tabindex="-1" aria-labelledby="accessLoginGuideTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Close"><i
                        class="ti ti-x"></i></button>
                <div class="rm-guide-box">
                    <div class="rm-guide-logos">
                        <img src="{{ asset('assets/img/branding/mbm-logo.png') }}" height="42" alt="Merdeka Battery Materials">
                        <span class="rm-guide-pipe" aria-hidden="true">|</span>
                        <span class="rm-ms-mark lg" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                    </div>
                    <div id="accessLoginGuideTitle">
                        Merdeka Battery Materials Group
                        <br><span class="rm-guide-domain">@merdekabattery.com</span>
                        <br>access via
                        <br><span class="rm-guide-sso">Microsoft SSO</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        // While the sign-in is sent: a spinner in the button, and no second click.
        $(function() {
            $('#loginForm').on('submit', function() {
                $('#loginSubmit').addClass('is-busy').prop('disabled', true).attr('aria-busy', 'true').text('Signing in…');
            });
        });
    </script>
@endpush
