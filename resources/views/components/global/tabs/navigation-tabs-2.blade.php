<!-- ----------------------------------------------------------------------- -->
<!--                       Please change id (tabs2-id)                        -->
<!-- ----------------------------------------------------------------------- -->
<div class="col-12">
    <ul id="tabs2-id" class="mdka-nav nav gap-3">
        <li class="mdka-nav-item nav-item">
            <a class="mdka-nav-link nav-link active">
                Component Active
            </a>
        </li>
        <li class="mdka-nav-item nav-item">
            <a class="mdka-nav-link nav-link">
                Component
            </a>
        </li>
        <div class="move-spinner d-none spinner-border text-blue position-absolute top-0 end-0" role="status">
        </div>
    </ul>
</div>

@push('javascript')
    <script>
        $(document).ready(function() {
            $('#tabs2-id .mdka-nav-link').on('click', function() {
                $('#tabs2-id .mdka-nav-link').removeClass('active');
                $(this).addClass('active');
                $('.move-spinner').addClass('d-none');
                $('#tabs2-id .move-spinner').removeClass('d-none');
            })
        })
    </script>
@endpush
