@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
@endpush

@include('RadioManagement::partials.scripts')

@section('page-title', 'Riwayat Radio')

@section('content')
    <div class="row g-2">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="historySearch" class="form-label">Cari</label>
                            <input type="text" id="historySearch" class="form-control" autocomplete="off"
                                placeholder="SN, Radio ID, user, keterangan, no. dokumen…">
                        </div>
                        <div class="col-md-3">
                            <label for="historyAction" class="form-label">Jenis</label>
                            <select id="historyAction" class="form-select" multiple="multiple">
                                @foreach ($actions as $action)
                                    <option value="{{ $action }}">{{ $action }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="historyFrom" class="form-label">Dari tanggal</label>
                            <input type="date" id="historyFrom" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label for="historyTo" class="form-label">Sampai tanggal</label>
                            <input type="date" id="historyTo" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <x-global.btn-detail label="Tampilkan" color="blue" size="sm" onclick="historyTable.apply()">
                                <x-slot:icon>
                                    <i class="ti ti-search"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                            <x-global.btn-detail label="" color="red" size="sm" outline="true"
                                onclick="historyTable.reset()" data-bs-toggle="tooltip" data-bs-title="Reset">
                                <x-slot:icon>
                                    <i class="ti ti-restore"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 mb-0" id="historyTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50">
                                                <th class="text-capitalize fs-5 py-3 text-center" width="30">No</th>
                                                <th class="text-capitalize fs-5 py-3">Tanggal</th>
                                                <th class="text-capitalize fs-5 py-3">Radio</th>
                                                <th class="text-capitalize fs-5 py-3">Jenis</th>
                                                <th class="text-capitalize fs-5 py-3">Keterangan</th>
                                                <th class="text-capitalize fs-5 py-3">No. Dokumen</th>
                                                <th class="text-capitalize fs-5 py-3">Oleh</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="pagination-container mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script>
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
                        const link = r.detail_url ? `<a href="${RadioUi.esc(r.detail_url)}">${name}</a>` : name;
                        return `${link}<div class="radio-muted">${RadioUi.esc(r.radio_user || '')}</div>`;
                    }
                },
                { key: 'action', render: r => RadioUi.badge(r.action, historyColors[r.action] || 'azure') },
                {
                    key: 'description',
                    render: r => `<div class="small text-wrap" style="max-width: 520px">${RadioUi.dash(r.description)}</div>`
                },
                { key: 'document_no', render: r => RadioUi.dash(r.document_no) },
                { key: 'user', render: r => RadioUi.esc(r.user) },
            ],

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#historyTable',
                    columns: historyTable.columns,
                    currentPage: historyTable.page,
                    limit: historyTable.limit,
                    emptyText: 'Belum ada riwayat',
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
                    search: $('#historySearch').val().trim(),
                    action: $('#historyAction').val(),
                    date_from: $('#historyFrom').val(),
                    date_to: $('#historyTo').val(),
                };
                historyTable.page = 1;
                historyTable.load();
            },

            reset() {
                $('#historySearch, #historyFrom, #historyTo').val('');
                $('#historyAction').val(null).trigger('change');
                historyTable.apply();
            },
        };

        $(function() {
            $('#historyAction').select2({
                theme: 'bootstrap-5',
                placeholder: 'Semua jenis',
                allowClear: true,
                width: '100%',
                closeOnSelect: false
            });
            $('#historySearch').on('keydown', e => {
                if (e.key === 'Enter') historyTable.apply();
            });
            historyTable.load();
        });
    </script>
@endpush
