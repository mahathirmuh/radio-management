<div class="d-flex flex-nowrap">
    <button type="submit" class="btn btn-sm px-2 py-1 mdka-btn-brand-500 text-white rounded-3 border-0 me-2"
        data-bs-toggle="tooltip" data-bs-placement="bottom" title="Apply Filter" style="z-index: 1">
        <i class="ti ti-circle-check fs-1 me-1"></i>
        Apply
    </button>
    <button type="button" class="btn btn-sm px-2 py-1 mdka-btn-red-500 text-white rounded-3 border-0"
        onclick="resetFilter()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Reset Filter"
        style="z-index: 1">
        <i class="ti ti-restore fs-1 me-1"></i>
        Reset
    </button>
</div>
@push('javascript')
    <script>
        function resetFilter() {
            window.location.href = window.location.pathname;
        }
    </script>
@endpush
