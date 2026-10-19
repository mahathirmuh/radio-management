<link href="{{ asset('assets/css/apps.min.css?1684106062') }}" rel="stylesheet" />  
<link href="{{ asset('assets/css/googleApis-publicSans.css') }}" rel="stylesheet" />  
<style>
    body {
        margin: 0;
        padding: 0;
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
        height: 100vh;
    }

    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url({{ asset('assets/img/branding/mdka-primary-bg.webp') }});
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
        filter: brightness(50%);
        z-index: -1;
    }

    .btn-login {
        color: #000;
        background-color: #fff;
        border-color: #4299e1;
    }

    .btn-login:hover {
        color: #000;
        background-color: #ecf5fc;
        border-color: #4299e1;
    }

    .gradient-custom-2 {
        background: #fccb90;
        background: -webkit-linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
        background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593);
    }

    @media (min-width: 768px) {
        .gradient-form {
            height: 100vh !important;
            background-attachment: fixed;
        }

        .gradient-custom-2 {
            border-top-right-radius: 0.3rem;
            border-bottom-right-radius: 0.3rem;
        }
    }

    .carousel-item img {
        width: 1100px;
        /* Set a fixed width */
        height: 550px;
        /* Set a fixed height */
    }
</style>
@stack('style')
