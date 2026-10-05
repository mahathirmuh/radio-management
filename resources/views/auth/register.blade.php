@extends('auth.main.app')
@section('content')
    <section class="h-100 gradient-form">
        <div class="container py-5 h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-xl-12">
                    <div class="card rounded-4 text-black"
                        style="box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.55); border-radius: 20px; overflow: hidden;">
                        <div class="row g-0">
                            <div class="col-sm-12 d-flex flex-column justify-content-between">
                                <div class="py-3 px-4 d-flex justify-content-between align-items-center">
                                    <img src="{{ asset('assets/img/branding/mcg-logo.png') }}" height="38px" alt="">
                                    <div class="d-flex align-items-end">
                                        <img src="{{ asset('assets/img/branding/mbm-logo.jpg') }}" height="56px"
                                            alt="">
                                    </div>
                                </div>
                                <div class="card-body mx-md-4 py-0">
                                    <div class="text-center mx-3">
                                        <h1 class="text-secondary mt-5">Merdeka Apps</h1>
                                        <h3 class="text-primary">Register Merdeka Account</h3>
                                        <hr class="m-0 mb-4">
                                        <form id="formAuthentication" class="mb-3" action="{{ route('register') }}"
                                            method="POST">
                                            @csrf
                                            <div class="mb-3">
                                                <label for="name" class="form-label text-start d-block">Name</label>
                                                <input type="text" class="form-control" id="username" name="name"
                                                    placeholder="Enter your Name" autofocus required/>
                                            </div>
                                            <div class="mb-3">
                                                <label for="email" class="form-label text-start d-block">Email</label>
                                                <input type="email" class="form-control" id="email" name="email"
                                                    placeholder="Enter your email" required/>
                                            </div>
                                            <div class="mb-3">
                                                <label for="nik" class="form-label text-start d-block">National Identity Card</label>
                                                <input type="text" class="form-control" id="nik" name="nik"
                                                    placeholder="Enter your national identity number (KTP/Passport)" required/>
                                            </div>
                                            <div class="mb-3 form-password-toggle">
                                                <label class="form-label text-start d-block" for="password">Password</label>
                                                <div class="input-group input-group-merge">
                                                    <input type="password" id="password" class="form-control"
                                                        name="password"
                                                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                                        aria-describedby="password" required/>
                                                    <span class="input-group-text cursor-pointer"><i
                                                            class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div> 
                                            <div class="mb-3"> 
                                                <label for="email" class="form-label  text-center">I'm a</label>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" id="visitor" name="role" value="visitor" required />
                                                    <label class="form-check-label" for="visitor">Visitor</label>
                                                </div>  
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" id="contractor" name="role" value="contractor" required />
                                                    <label class="form-check-label" for="contractor">Contractor</label>
                                                </div> 
                                            </div> 
                                            <div class="mb-3" id="nipField">
                                                <label for="nip" class="form-label text-start d-block">NIP</label>
                                                <input type="text" class="form-control" id="NIP" name="NIP"
                                                    placeholder="Enter your employee number"/>
                                            </div> 
                                            
                                            <div class="mb-3" id="departmentField">
                                                <label for="department" class="form-label text-start d-block">Department</label>
                                                <input type="text" class="form-control" id="department" name="department"
                                                    placeholder="Enter your Department"/>
                                            </div>
                                            
                                            <div class="mb-3" id="positionField">
                                                <label for="position" class="form-label text-start d-block">Position</label>
                                                <input type="text" class="form-control" id="position" name="position"
                                                    placeholder="Enter your position"/>
                                            </div>   
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="terms-conditions"
                                                        name="terms" required />
                                                    <label class="form-check-label text-start d-block" for="terms-conditions">
                                                        I agree to
                                                        <a href="javascript:void(0);">privacy policy & terms in company</a>
                                                    </label>
                                                </div>
                                            </div>
                                            <button class="btn btn-primary px-2 py-1 d-grid w-100">Sign up</button>
                                        </form> 
                                        <p class="text-center">
                                            <span>Already have an account?</span>
                                            <a href="{{ route('login') }}">
                                                <span>Sign in instead</span>
                                            </a>
                                        </p> 
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center pb-2">
                                    <small class="text-muted">© Merdeka Group 2024</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> 
    </section>
@endsection
@push('javascript') 
<script>
     $(document).ready(function () {
        // Sembunyikan field NIP, Department, dan Position saat halaman dimuat
        $('#nipField, #departmentField, #positionField').hide();

        // Event listener ketika radio button berubah
        $('input[name="role"]').change(function () {
            if ($('#contractor').is(':checked')) {
                // Tampilkan field & jadikan required
                $('#nipField, #departmentField, #positionField').show();
                $('#NIP, #department, #position').prop('required', true);
            } else {
                // Sembunyikan field & hapus required
                $('#nipField, #departmentField, #positionField').hide();
                $('#NIP, #department, #position').prop('required', false);
            }
        });
    });
</script>
@endpush
