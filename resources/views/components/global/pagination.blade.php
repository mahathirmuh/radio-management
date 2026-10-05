@props([
    'paginator' => null,
    'label' => '',
    'withLimit' => true,
])

@push('style')
    <style type="text/css">
        .btn-pagination {
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 12px;
            color: #334155;
            font-size: 12px;
        }

        .btn-pagination.btn-primary {
            color: #fff;
            background: #3b82f6;

        }

        .btn-pagination:hover {
            color: #fff;
            background: #3b82f6;
        }

        .btn-previous,
        .btn-next {
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 6px;
            color: #000;
            background: #fff;
            font-weight: 500;
            border: 0.5px solid #E2E8F0;
        }

        .btn-previous.disabled,
        .btn-next.disabled {
            background: #E2E8F0;
            border-color: #E2E8F0
        }
    </style>
@endpush

@php
    $queryParams = request()->query();
    $limit = $queryParams['limit'] ?? 10;
@endphp


<div class="row justify-content-between align-items-center">
    <div class="col-auto d-flex flex-row justify-content-start align-items-center g-3 gap-3">
        @if ($paginator->count() > 0)
            @if ($paginator->hasPages())

                {{-- Previous Page Link --}}
                @php
                    $url =
                        request()->url() .
                        '?' .
                        http_build_query(array_merge($queryParams, ['page' => $paginator->currentPage() - 1]));
                @endphp
                <a class="btn-previous text-decoration-none {{ $paginator->currentPage() <= 1 ? 'pe-none disabled' : 'cursor-pointer' }}"
                    {{ $paginator->currentPage() <= 1 ? 'disabled' : 'href=' . $url }}>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M15 6l-6 6l6 6" />
                    </svg>
                </a>

                {{-- Pagination Elements --}}
                {{-- Show the first page --}}
                @php
                    $url = request()->url() . '?' . http_build_query(array_merge($queryParams, ['page' => 1]));
                @endphp
                <a href="{{ $url }}"
                    class="btn-pagination text-decoration-none {{ $paginator->currentPage() == 1 ? 'btn-primary ' : '' }} "><span>1</span></a>

                {{-- Show dots after first page --}}
                @if ($paginator->currentPage() > 4)
                    <span class="">...</span>
                @endif

                {{-- Show pages around the current page --}}
                @if ($paginator->lastPage() > 2)
                    @foreach (range(max($paginator->currentPage() - 2, 2), min($paginator->currentPage() + 2, $paginator->lastPage() - 1)) as $page)
                        @php
                            $url =
                                request()->url() . '?' . http_build_query(array_merge($queryParams, ['page' => $page]));
                        @endphp
                        <a href="{{ $url }}"
                            class="btn-pagination text-decoration-none {{ $paginator->currentPage() == $page ? 'btn-primary ' : '' }} ">
                            {{ $page }}
                        </a>
                    @endforeach
                @endif

                {{-- Show dots before last page --}}
                @if ($paginator->currentPage() < $paginator->lastPage() - 3)
                    <span class="">...</span>
                @endif

                {{-- Show the last page --}}
                @php
                    $url =
                        request()->url() .
                        '?' .
                        http_build_query(array_merge($queryParams, ['page' => $paginator->lastPage()]));
                @endphp
                <a href="{{ $url }}"
                    class="btn-pagination text-decoration-none {{ $paginator->currentPage() == $paginator->lastPage() ? 'btn-primary' : '' }}"><span>{{ $paginator->lastPage() }}</span></a>

                @php
                    $url =
                        request()->url() .
                        '?' .
                        http_build_query(array_merge($queryParams, ['page' => $paginator->currentPage() + 1]));
                @endphp
                <a class="btn-next {{ $paginator->currentPage() >= $paginator->lastPage() ? 'pe-none disabled' : 'cursor-pointer' }}"
                    href="{{ $url }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M9 6l6 6l-6 6" />
                    </svg>
                </a>
            @endif
        @endif
    </div>
    <div class="col-md-auto d-flex flex-row justify-content-end align-items-center g-3 gap-3">
        @if ($label != '')
            <p class="text-nowrap mb-0">
                Showing {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} of
                {{ $paginator->total() }}
                {{ $label }}{{ $paginator->total() > 1 ? 's' : '' }}
            </p>
        @endif
        @if ($withLimit == true)
            <p class="text-nowrap mb-0">Show : </p>
            <select class="form-select " id="limit" name="limit">
                <option value="10" {{ $limit == 10 ? 'selected' : '' }}>10 Rows</option>
                <option value="25" {{ $limit == 25 ? 'selected' : '' }}>25 Rows</option>
                <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50 Rows</option>
                <option value="100" {{ $limit == 100 ? 'selected' : '' }}>100 Rows</option>
            </select>
        @endif
    </div>
</div>


@push('javascript')
    <script>
        $('#limit').on('change', function() {
            let limit = $(this).val();
            let url = new URL(window.location.href);
            url.searchParams.delete('page');
            url.searchParams.delete('limit');
            url.searchParams.set('limit', limit);
            window.location.href = url.toString();
        })
    </script>
@endpush
