@extends('main.index')
@push('style')
    <style>
        #historyTable > tbody > tr > td {
            white-space: nowrap;
        }

        #historyTable > tbody > tr > td.history-text {
            min-width: 220px;
            max-width: 520px;
            white-space: normal;
        }

        #historyTable .history-by {
            color: var(--rm-gray-500);
        }
    </style>
@endpush

@include('RadioManagement::partials.scripts')

@section('page-title', __('RadioManagement::history.title'))

@section('content')
    <div class="rm-card">
        <div class="rm-filter-row">
            <div class="rm-field">
                <label for="historySearch" class="form-label">{{ __('RadioManagement::history.search') }}</label>
                <div class="rm-search">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input type="search" id="historySearch" autocomplete="off"
                        placeholder="{{ __('RadioManagement::history.search_hint') }}" />
                </div>
            </div>
            <div class="rm-field">
                <label for="historyAction" class="form-label">{{ __('RadioManagement::history.kind') }}</label>
                <select id="historyAction" class="form-select" multiple="multiple">
                    {{-- The kind kept (Indonesian) is what is sent; its name in the language of the page is what is shown. --}}
                    @foreach ($actions as $action)
                        <option value="{{ $action }}">{{ \Modules\RadioManagement\Services\RadioWords::of('history_actions', $action) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rm-field">
                <label for="historyFrom" class="form-label">{{ __('RadioManagement::history.from') }}</label>
                <input type="hidden" id="historyFrom">
            </div>
            <div class="rm-field">
                <label for="historyTo" class="form-label">{{ __('RadioManagement::history.to') }}</label>
                <input type="hidden" id="historyTo">
            </div>
            <button type="button" class="btn-rm secondary" onclick="historyTable.reset()">
                <i class="ti ti-restore" aria-hidden="true"></i> {{ __('RadioManagement::history.reset') }}
            </button>
        </div>

        <div class="rm-box">
            <div class="table-responsive">
                <table class="rm-table" id="historyTable">
                    <thead>
                        <tr>
                            <th class="text-center" width="30">{{ __('RadioManagement::history.columns.no') }}</th>
                            <th>{{ __('RadioManagement::history.columns.date') }}</th>
                            <th>{{ __('RadioManagement::history.columns.radio') }}</th>
                            <th>{{ __('RadioManagement::history.columns.kind') }}</th>
                            <th>{{ __('RadioManagement::history.columns.description') }}</th>
                            <th>{{ __('RadioManagement::history.columns.document') }}</th>
                            <th class="text-end">{{ __('RadioManagement::history.columns.by') }}</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container"></div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        /** The words of the list in the language of the page (RadioManagement::history.list). */
        const historyText = @json(__('RadioManagement::history.list'));

        // The colour of each kind of entry (the design's types: hand-over blue, service orange, program purple, verification
        // green, change of status yellow, deleted red; the others grey), by the kind kept.
        const historyColors = {
            'Serah Terima': 'blue',
            'Service': 'orange',
            'Program': 'purple',
            'Verifikasi': 'green',
            'Ubah Status': 'yellow',
            'Arsip': 'secondary',
            'Dipulihkan': 'teal',
            'Dihapus': 'red',
        };

        const historyTable = {
            url: "{{ route('radio-management.history.list') }}",
            page: 1,
            limit: 25,
            filters: {},
            columns: [
                'index',
                { key: 'event_date', render: r => RadioUi.esc(r.event_date) },
                {
                    key: 'radio',
                    render: r => {
                        const name = RadioUi.esc(r.radio || '-');
                        const link = r.detail_url ? `<a href="${RadioUi.esc(r.detail_url)}" class="fw-medium">${name}</a>` : name;
                        return `<div>${link}</div>${r.radio_user ? `<div class="rm-line2">${RadioUi.esc(r.radio_user)}</div>` : ''}`;
                    }
                },
                { key: 'action', render: r => RadioUi.badge(RadioUi.name('history_actions', r.action), historyColors[r.action] || 'secondary') },
                { key: 'description', className: 'history-text', render: r => RadioUi.dash(r.description) },
                { key: 'document_no', render: r => r.document_no ? `<span class="rm-code">${RadioUi.esc(r.document_no)}</span>` : RadioUi.dash(null) },
                { key: 'user', className: 'text-end history-by', render: r => RadioUi.esc(r.user) },
            ],

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#historyTable',
                    columns: historyTable.columns,
                    currentPage: historyTable.page,
                    limit: historyTable.limit,
                    emptyText: historyText.empty,
                    paginationLabels: { noun: historyText.noun },
                    onPageChange: page => {
                        historyTable.page = page;
                        historyTable.load();
                    },
                    onLimitChange: limit => {
                        historyTable.limit = limit;
                        historyTable.page = 1;
                        historyTable.load();
                    },
                    ...extra,
                });
            },

            load() {
                historyTable.render({ loading: true });
                $.getJSON(historyTable.url, {
                    page: historyTable.page,
                    paginated: historyTable.limit,
                    ...historyTable.filters,
                }).done(response => historyTable.render({ data: response.data, meta: response.meta }))
                    .fail(() => historyTable.render({ emptyText: historyText.load_failed }));
            },

            apply() {
                historyTable.filters = {
                    search: String($('#historySearch').val() || '').trim(),
                    action: $('#historyAction').val(),
                    date_from: $('#historyFrom').val(),
                    date_to: $('#historyTo').val(),
                };
                historyTable.page = 1;
                historyTable.load();
            },

            reset() {
                // The fields are emptied without a request each; one request follows.
                historyTable.quiet = true;
                $('#historySearch').val('');
                $('#historyFrom, #historyTo').val('').trigger('change');
                $('#historyAction').val([]).trigger('change');
                historyTable.quiet = false;
                historyTable.apply();
            },
        };

        $(function() {
            rmPickers.multi('#historyAction', { all: historyText.all_kinds });
            rmPickers.date('#historyFrom', { empty: historyText.all_dates, max: () => $('#historyTo').val() || rmPickers.iso(new Date()) });
            rmPickers.date('#historyTo', { empty: historyText.until_today, min: () => $('#historyFrom').val() || null });
            // The list follows the filters: what is typed a moment after the last key, the others at once.
            $('#historySearch').on('input', debounce(() => historyTable.apply(), 350));
            $('#historySearch').on('keydown', e => {
                if (e.key === 'Enter') historyTable.apply();
            });
            $('#historyAction, #historyFrom, #historyTo').on('change', () => {
                if (!historyTable.quiet) historyTable.apply();
            });
            historyTable.load();
        });
    </script>
@endpush
