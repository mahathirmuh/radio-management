@props(['value' => 'desc'])

@php
    $query = request()->query();
    $value = $query['sort'] ?? $value;
@endphp

<div class="d-flex flex-row justify-content-end align-items-center g-3 gap-3 dropdown-sort-date-wrapper">
    <p class="text-nowrap mb-0">Sort : </p>
    <div class="dropdown">
        <a id="dorpdown-sort" href="#" class="btn dropdown-toggle rounded-3"
            data-bs-toggle="dropdown">{{ $value == 'desc' ? 'Most recent' : 'Oldest' }}</a>
        <div class="dropdown-menu">
            <a class="dropdown-item" role="button" onclick="{{ $value == 'desc' ? '' : 'applyDataSorting(\'desc\')' }}">
                Most recent
            </a>
            <a class="dropdown-item" role="button" onclick="{{ $value == 'asc' ? '' : 'applyDataSorting(\'asc\')' }}">
                Oldest
            </a>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        function applyDataSorting($value) {
            let fullUrl = window.location.href;
            let url = new URL(fullUrl);
            url.searchParams.delete('sort');
            url.searchParams.set('sort', $value);
            $('#dorpdown-sort').html('<div class="spinner-border spinner-border-sm text-facebook" role="status">');
            window.location.href = url.toString();
        }
    </script>
@endpush
