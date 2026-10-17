 <footer class="footer footer-transparent d-print-none">
     <div class="container-xl">
         <div class="row text-center align-items-center flex-row-reverse">
             <div class="hr mb-3 mt-0"></div>

             <div class="col-lg-auto ms-lg-auto">
                 {{--  --}}
             </div>
             <div class="col-12 text-center d-flex justify-content-center align-items-center">
                 Copyright &copy; {{ date('Y') }}, Development by <a href="https://merdekacoppergold.com/" target="_blank"
                     class="fw-normal ms-1"> Merdeka Group</a>
                 @if (filled(config('app.version')))
                     <a href="/" class="badge mdka-bg-gray-700 rounded-2 ms-2" title="Application version">
                         v{{ config('app.version') }}
                     </a>
                 @endif
             </div>
         </div>
     </div>
 </footer>
