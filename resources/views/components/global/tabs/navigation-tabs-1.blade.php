<!-- ----------------------------------------------------------------------- -->
<!--                       Please change id (tabs-id)                        -->
<!-- ----------------------------------------------------------------------- -->
<div class="col-12">
    <ul id="tabs-id" class="mdka-nav-btn">
        <li class="mdka-nav-btn-item">
            <a class="mdka-nav-btn-link active">
                Monitoring Compliance</a>
        </li>
        <li class="mdka-nav-btn-item">
            <a class="mdka-nav-btn-link">
                Model List</a>
        </li>
        <div class="move-spinner d-none spinner-border text-blue position-absolute top-0 end-0" role="status">
        </div>
    </ul>
</div>

@push('javascript')
    <script>
        $(document).ready(function() {
            $('#tabs-id .mdka-nav-btn-link').on('click', function() {
                $('#tabs-id .mdka-nav-btn-link').removeClass('active');
                $(this).addClass('active');
                $('.move-spinner').addClass('d-none');
                $('#tabs-id .move-spinner').removeClass('d-none');
            })
        })
    </script>
@endpush
