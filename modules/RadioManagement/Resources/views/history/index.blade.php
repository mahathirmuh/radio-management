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

@section('page-title', 'Riwayat Radio')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">Riwayat Radio</h1>
            <p class="rm-sub">Setiap kejadian pada radio, yang terbaru di atas</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-filter-row">
            <div class="rm-field">
                <label for="historySearch" class="form-label">Cari</label>
                <div class="rm-search">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input type="search" id="historySearch" autocomplete="off"
                        placeholder="Radio ID, SN, user, keterangan, no. dokumen" />
                </div>
            </div>
            <div class="rm-field">
                <label for="historyAction" class="form-label">Jenis</label>
                <select id="historyAction" class="form-select" multiple="multiple">
                    @foreach ($actions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rm-field">
                <label for="historyFrom" class="form-label">Dari tanggal</label>
                <input type="hidden" id="historyFrom">
            </div>
            <div class="rm-field">
                <label for="historyTo" class="form-label">Sampai tanggal</label>
                <input type="hidden" id="historyTo">
            </div>
            <button type="button" class="btn-rm secondary" onclick="historyTable.reset()">
                <i class="ti ti-restore" aria-hidden="true"></i> Reset
            </button>
        </div>

        <div class="rm-box">
            <div class="table-responsive">
                <table class="rm-table" id="historyTable">
                    <thead>
                        <tr>
                            <th class="text-center" width="30">No</th>
                            <th>Tanggal</th>
                            <th>Radio</th>
                            <th>Jenis</th>
                            <th>Keterangan</th>
                            <th>No. Dokumen</th>
                            <th class="text-end">Oleh</th>
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
        // The colour of each kind of entry (the design's types: hand-over blue, service orange, program purple, verification
        // green, change of status yellow, deleted red; the others grey).
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
                { key: 'action', render: r => RadioUi.badge(r.action, historyColors[r.action] || 'secondary') },
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
                    emptyText: 'Belum ada riwayat',
                    paginationLabels: { showing: 'Menampilkan', of: 'dari', rows: 'Baris per halaman', noun: 'kejadian', previous: 'Halaman sebelumnya', next: 'Halaman berikutnya' },
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
                    .fail(() => historyTable.render({ emptyText: 'Gagal memuat data.' }));
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
            rmPickers.multi('#historyAction', { all: 'Semua jenis' });
            rmPickers.date('#historyFrom', { empty: 'Semua tanggal', max: () => $('#historyTo').val() || rmPickers.iso(new Date()) });
            rmPickers.date('#historyTo', { empty: 'Sampai hari ini', min: () => $('#historyFrom').val() || null });
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
