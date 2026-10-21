@extends('main.index')

@php
    $listName = fn (string $key) => __('SnipeIt::snipe.lists.' . $key);
    $allOf = ['category' => 'all_categories', 'status' => 'all_statuses', 'action' => 'all_actions'];
@endphp

@section('page-title', __('SnipeIt::snipe.title') . ' · ' . __('SnipeIt::snipe.groups.' . $group))

@push('style')
    <style>
        .snipe-toolbar .form-select {
            width: 220px;
            max-width: 100%;
        }

        /* Long texts (a note, the change of an entry of the activity log) wrap in a bounded width. */
        #snipeTable td {
            max-width: 360px;
            overflow-wrap: anywhere;
        }

        #snipeTable td.is-number,
        #snipeTable td.is-date,
        #snipeTable td.is-badge {
            white-space: nowrap;
        }

        .snipe-link {
            color: var(--rm-brand-700);
            font-weight: 600;
            text-decoration: none;
        }

        .snipe-link:hover {
            color: var(--rm-brand-600);
        }
    </style>
@endpush

@section('content')
    @if ($error)
        <div class="rm-alert warning mb-3" role="alert" id="snipeError">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    <div class="rm-card">
        {{-- A page with one list (Aset) has no tabs. --}}
        @if (count($tabs) > 1)
            <div class="rm-tabs-row">
                <nav class="rm-tabs" aria-label="{{ __('SnipeIt::snipe.tabs') }}">
                    @foreach ($tabs as $key => $total)
                        <a href="{{ route("snipe-it.{$group}.index", ['tab' => $key]) }}" @class(['is-on' => $key === $tab])
                            @if ($key === $tab) aria-current="page" @endif>{{ $listName($key) }}@if ($total !== null)
                                <span class="rm-tab-count">{{ \App\Services\Language::number($total) }}</span>
                            @endif</a>
                    @endforeach
                </nav>
            </div>
        @endif

        <div class="rm-toolbar rm-toolbar-tight snipe-toolbar">
            <label class="rm-search" for="snipeSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="snipeSearch" autocomplete="off" value="{{ $search }}"
                    placeholder="{{ __('SnipeIt::snipe.search', ['list' => mb_strtolower($listName($tab))]) }}"
                    aria-label="{{ __('SnipeIt::snipe.search_label') }}" />
            </label>
            @foreach ($filters as $filter => $choice)
                <div class="rm-field">
                    <select class="form-select" id="snipeFilter-{{ $filter }}" data-snipe-filter="{{ $filter }}"
                        aria-label="{{ __('SnipeIt::snipe.filters.' . $filter) }}">
                        <option value="">{{ __('SnipeIt::snipe.filters.' . $allOf[$filter]) }}</option>
                        @foreach ($choice['options'] as $value => $name)
                            <option value="{{ $value }}" @selected((string) $value === (string) $choice['chosen'])>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <span class="rm-end">
                <button type="button" class="btn-rm secondary is-icon" id="snipeRefresh" data-bs-toggle="tooltip"
                    data-bs-title="{{ __('SnipeIt::snipe.refresh') }}" aria-label="{{ __('SnipeIt::snipe.refresh') }}"><i
                        class="ti ti-refresh" aria-hidden="true"></i></button>
                @if ($web)
                    <a class="btn-rm secondary" href="{{ $web }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link"
                            aria-hidden="true"></i> {{ __('SnipeIt::snipe.open') }}</a>
                @endif
            </span>
        </div>

        <div class="rm-box" id="snipeBox">
            <div class="table-responsive">
                <table class="rm-table" id="snipeTable">
                    <thead>
                        <tr>
                            @foreach ($heads as $head)
                                <th scope="col" data-key="{{ $head['key'] }}" @class(['text-end' => $head['type'] === 'number'])>{{ $head['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container" id="snipePagination"></div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        /** The words of the page's script in the language of the page (SnipeIt::snipe.script). */
        const snipeText = @json(__('SnipeIt::snipe.script'));

        /**
         * A list of Snipe-IT, a page at a time: the server asks Snipe-IT (GET only) and sends the columns of the list as text,
         * which is escaped here where it is written. Search, filters, order and page go with each request.
         */
        const snipeList = {
            url: @json(route("snipe-it.{$group}.list")),
            tab: @json($tab),
            heads: @json($heads),
            page: 1,
            limit: 25,
            sort: @json($order[0] ?? null),
            order: @json($order[1] ?? 'desc'),
            search: @json($search),
            filters: @json((object) collect($filters)->map(fn ($choice) => $choice['chosen'] === null ? '' : (string) $choice['chosen'])->all()),
            rows: [],
            meta: null,
            state: 'loading',
            message: '',
            request: null,
            /** The colours a badge may have (anything else is grey). */
            tones: ['blue', 'green', 'yellow', 'orange', 'red', 'purple', 'gray'],

            esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[c]);
            },

            /** A cell: its text in the way of its column, a second line under it; a dash when there is no text. */
            cell(row, head) {
                const esc = snipeList.esc;
                const cell = (row.cells || {})[head.key] || {};
                if (cell.t === null || cell.t === undefined || cell.t === '') return '<span class="rm-none">—</span>';
                const text = esc(cell.t);
                let html;
                if (head.type === 'link' && row.link) {
                    html = `<a href="${esc(row.link)}" class="snipe-link" target="_blank" rel="noopener noreferrer" title="${esc(snipeText.open_row)}">${text}</a>`;
                } else if (head.type === 'link' || head.type === 'strong') {
                    html = `<span class="rm-strong">${text}</span>`;
                } else if (head.type === 'code') {
                    html = `<span class="rm-code">${text}</span>`;
                } else if (head.type === 'badge' || head.type === 'bool') {
                    const tone = snipeList.tones.includes(cell.tone) ? cell.tone : 'gray';
                    html = `<span class="rm-badge ${tone}">${text}</span>`;
                } else {
                    html = text;
                }

                return html + (cell.s ? `<div class="rm-line2">${esc(cell.s)}</div>` : '');
            },

            columns() {
                return snipeList.heads.map(head => ({
                    key: head.key,
                    sortable: head.sortable,
                    className: { number: 'text-end is-number', date: 'is-date', badge: 'is-badge', bool: 'is-badge' } [head.type] || '',
                    render: row => snipeList.cell(row, head),
                }));
            },

            render() {
                const message = {
                    loading: snipeText.loading,
                    failed: snipeList.message || snipeText.failed,
                } [snipeList.state] || (snipeList.search ? snipeText.none_found : snipeText.empty);
                const columns = snipeList.columns();

                renderTable({
                    container: '#snipeTable',
                    data: snipeList.state === 'done' ? snipeList.rows : [],
                    columns,
                    currentPage: snipeList.page,
                    limit: snipeList.limit,
                    emptyText: snipeList.esc(message),
                });
                initSorting({
                    container: '#snipeTable',
                    columns,
                    currentSortKey: snipeList.sort,
                    currentSortDir: snipeList.order,
                    onSortChange: (key, order) => {
                        snipeList.sort = key;
                        snipeList.order = order;
                        snipeList.page = 1;
                        snipeList.load();
                    },
                });

                if (snipeList.state === 'done' && snipeList.meta) {
                    renderPagination({
                        meta: snipeList.meta,
                        limit: snipeList.limit,
                        container: '#snipePagination',
                        onPageChange: page => {
                            snipeList.page = page;
                            snipeList.load();
                        },
                        onLimitChange: limit => {
                            snipeList.limit = limit;
                            snipeList.page = 1;
                            snipeList.load();
                        },
                    });
                } else {
                    $('#snipePagination').hide();
                }
            },

            /** Asks for the page; $fresh: Snipe-IT is asked again, not what was kept of its last answer. */
            load(fresh = false) {
                snipeList.request?.abort();
                snipeList.state = 'loading';
                snipeList.render();
                const query = {
                    tab: snipeList.tab,
                    page: snipeList.page,
                    paginated: snipeList.limit,
                    search: snipeList.search,
                    ...snipeList.filters,
                };
                if (snipeList.sort) {
                    query.sort = snipeList.sort;
                    query.order = snipeList.order;
                }
                if (fresh) query.fresh = 1;
                snipeList.request = $.getJSON(snipeList.url, query).done(response => {
                    snipeList.rows = response.data || [];
                    snipeList.meta = response.meta || null;
                    if (response.sort && response.sort.key) {
                        snipeList.sort = response.sort.key;
                        snipeList.order = response.sort.order;
                    }
                    snipeList.state = 'done';
                    snipeList.render();
                }).fail((xhr, status) => {
                    if (status === 'abort') return;
                    snipeList.message = xhr?.responseJSON?.message || '';
                    snipeList.state = 'failed';
                    snipeList.render();
                });
            },

            /** The address of the page says what is shown, so it can be passed on: ?tab=users&q=budi&category=15. */
            remember() {
                const params = new URLSearchParams();
                if (snipeList.tab !== @json(array_key_first($tabs))) params.set('tab', snipeList.tab);
                if (snipeList.search) params.set('q', snipeList.search);
                Object.entries(snipeList.filters).forEach(([key, value]) => {
                    if (value) params.set(key, value);
                });
                const query = params.toString();
                history.replaceState(null, '', location.pathname + (query ? '?' + query : ''));
            },

            apply() {
                snipeList.search = String($('#snipeSearch').val() || '').trim();
                Object.keys(snipeList.filters).forEach(key => {
                    snipeList.filters[key] = String($('#snipeFilter-' + key).val() || '');
                });
                snipeList.page = 1;
                snipeList.remember();
                snipeList.load();
            },
        };

        $(function() {
            const typed = debounce(() => snipeList.apply());
            $('#snipeSearch').on('input', typed).on('keydown', event => {
                if (event.key === 'Enter') snipeList.apply();
            });
            $('[data-snipe-filter]').on('change', () => snipeList.apply());
            $('#snipeRefresh').on('click', () => snipeList.load(true));
            snipeList.load();
        });
    </script>
@endpush
