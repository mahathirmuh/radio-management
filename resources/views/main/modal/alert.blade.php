{{-- Default alert (success, error, warning) --}}
@if (Session::has('success') || Session::has('error') || Session::has('warning'))
    @if (config('sweetalert.alwaysLoadJS') === false && config('sweetalert.neverLoadJS') === false)
        <script>
            @if (Session::has('success'))
                Swal.fire({
                    icon: 'success',
                    title: @json(__('ui.flash.success')),
                    text: @json(Session::pull('success')),
                    showConfirmButton: false,
                    timerProgressBar: true,
                    timer: 3000
                });
            @endif

            @if (Session::has('error'))
                Swal.fire({
                    icon: 'error',
                    title: @json(__('ui.flash.error')),
                    text: @json(Session::pull('error')),
                    showConfirmButton: false,
                    timerProgressBar: true,
                    timer: 3000
                });
            @endif

            @if (Session::has('warning'))
                Swal.fire({
                    icon: 'warning',
                    title: @json(__('ui.flash.warning')),
                    text: @json(Session::pull('warning')),
                    showConfirmButton: false,
                    timerProgressBar: true,
                    timer: 3000
                });
            @endif
        </script>
    @else
        @if (Session::has('success'))
            <script>
                Swal.fire({
                    icon: 'success',
                    title: @json(__('ui.flash.success')),
                    text: @json(Session::pull('success')),
                    showConfirmButton: false,
                    timerProgressBar: true,
                    timer: 3000
                });
            </script>
        @endif

        @if (Session::has('error'))
            <script>
                Swal.fire({
                    icon: 'error',
                    title: @json(__('ui.flash.error')),
                    text: @json(Session::pull('error')),
                    showConfirmButton: false,
                    timerProgressBar: true,
                    timer: 3000
                });
            </script>
        @endif

        @if (Session::has('warning'))
            <script>
                Swal.fire({
                    icon: 'warning',
                    title: @json(__('ui.flash.warning')),
                    text: @json(Session::pull('warning')),
                    showConfirmButton: false,
                    timerProgressBar: true,
                    timer: 3000
                });
            </script>
        @endif
    @endif
@endif
