@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
    <style>
        #radioTable td {
            vertical-align: middle;
        }

        #radioTable .radio-user {
            max-width: 220px;
            white-space: normal;
        }

        /* The radios ticked (radio.bulk-bar). */
        .radio-bulk-bar {
            background: rgba(32, 107, 196, .07);
            border: 1px solid rgba(32, 107, 196, .25);
        }

        #radioTable .radio-pick-cell {
            width: 34px;
            padding-right: 0;
        }
    </style>
@endpush

@include('RadioManagement::partials.scripts')

@php
    // What the bar of the radios ticked offers: Update (verify, archive) and Delete on "Register Radio" (the server checks again).
    $bulkMay = [
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Delete'),
    ];
@endphp

@section('page-title', 'Register Radio')

@section('content')
    <div class="row g-2">
        <div class="col-12">
            @include('RadioManagement::radio.filter')
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <input type="text" id="radioSearch" class="form-control form-control-lg pb-1 rounded-3"
                                    autocomplete="off" placeholder="Cari SN, Radio ID, MDC, user…" style="width: 280px" />
                                <x-global.btn-detail label="" color="blue" size="sm" outline="true" id="radioSearchButton"
                                    data-bs-toggle="tooltip" data-bs-title="Cari">
                                    <x-slot:icon>
                                        <i class="ti ti-search"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <x-global.btn-detail label="" color="purple" size="sm" outline="true" id="radioFilterButton"
                                    data-bs-toggle="tooltip" data-bs-title="Filter">
                                    <x-slot:icon>
                                        <i class="ti ti-filter"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <x-global.btn-detail label="" color="red" size="sm" outline="true" id="radioResetButton"
                                    data-bs-toggle="tooltip" data-bs-title="Reset">
                                    <x-slot:icon>
                                        <i class="ti ti-restore"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <div class="btn-group" role="group" aria-label="Lingkup data">
                                    <input type="radio" class="btn-check" name="radioScope" id="scopeActive" value="active" checked>
                                    <label class="btn btn-sm" for="scopeActive">Aktif</label>
                                    <input type="radio" class="btn-check" name="radioScope" id="scopeArchived" value="archived">
                                    <label class="btn btn-sm" for="scopeArchived">Arsip</label>
                                    <input type="radio" class="btn-check" name="radioScope" id="scopeAll" value="all">
                                    <label class="btn btn-sm" for="scopeAll">Semua</label>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <x-global.btn-detail label="Export" color="green" size="sm" outline="true"
                                    onclick="radioTable.export()" data-bs-toggle="tooltip"
                                    data-bs-title="Unduh Excel sesuai filter">
                                    <x-slot:icon>
                                        <i class="ti ti-file-spreadsheet"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <x-global.btn-detail label="Tambah Radio" color="blue" size="sm"
                                    onclick="radioForm.open('add')">
                                    <x-slot:icon>
                                        <i class="ti ti-plus"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                            </div>
                        </div>

                        <div class="col-12 d-none" id="radioIssueChip">
                            <span class="badge bg-red-lt radio-badge py-2 px-3">
                                Hasil cek: <span id="radioIssueChipLabel"></span>
                                <a href="#" class="ms-2 text-reset" onclick="radioFilter.reset(); return false;"
                                    aria-label="Hapus filter"><i class="ti ti-x"></i></a>
                            </span>
                        </div>

                        @include('RadioManagement::radio.bulk-bar')

                        <div class="col-12">
                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 text-nowrap mb-0" id="radioTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50">
                                                <th class="py-3 radio-pick-cell"><input type="checkbox" class="form-check-input m-0"
                                                        id="radioPickPage" aria-label="Pilih semua radio di halaman ini"></th>
                                                <th class="text-capitalize fs-5 py-3 text-center" width="30">No</th>
                                                <th class="text-capitalize fs-5 py-3" data-key="dmr_id">Radio ID</th>
                                                <th class="text-capitalize fs-5 py-3" data-key="serial_number">SN / Tipe</th>
                                                <th class="text-capitalize fs-5 py-3" data-key="user_name">User / Department</th>
                                                <th class="text-capitalize fs-5 py-3">Company</th>
                                                <th class="text-capitalize fs-5 py-3">Owner</th>
                                                <th class="text-capitalize fs-5 py-3">Template</th>
                                                <th class="text-capitalize fs-5 py-3">Posisi</th>
                                                <th class="text-capitalize fs-5 py-3">Kondisi</th>
                                                <th class="text-capitalize fs-5 py-3" data-key="last_verified_at">Verifikasi</th>
                                                <th class="text-capitalize fs-5 py-3">Cek</th>
                                                <th class="text-capitalize fs-5 py-3 text-center">Aksi</th>
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

    @include('RadioManagement::radio.modal')
    @include('RadioManagement::radio.archive-modal')
    @include('RadioManagement::radio.bulk')
    @include('RadioManagement::radio.print-modal')
@endsection

@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script>
        const radioIssues = @json($issues);

        const radioTable = {
            listUrl: "{{ route('radio-management.radio.list') }}",
            exportUrl: "{{ route('radio-management.radio.export') }}",
            page: 1,
            limit: 25,
            search: '',
            orderBy: 'dmr_id',
            orderDir: 'asc',
            scope: 'active',

            columns: [
                // The box that ticks the radio for the bar of radio.bulk.
                { key: 'pick', className: 'radio-pick-cell', render: r => radioBulk.cell(r) },
                'index',
                {
                    key: 'dmr_id',
                    sortable: true,
                    render: r => {
                        if (!r.dmr_id) {
                            return r.previous_dmr_id ?
                                `<span class="radio-muted" title="ID terakhir diketahui">ex ${r.previous_dmr_id}</span>` :
                                RadioUi.dash();
                        }
                        return `<span class="fw-bold">${r.dmr_id}</span><div class="radio-muted radio-mono">MDC ${RadioUi.esc(r.mdc)}</div>`;
                    }
                },
                {
                    key: 'serial_number',
                    sortable: true,
                    render: r => {
                        const sn = r.serial_number ? `<span class="radio-mono">${RadioUi.esc(r.serial_number)}</span>` : RadioUi.dash();
                        const type = r.type ?
                            `<div class="radio-muted">${RadioUi.esc(r.type.replace('MotoTrbo XiR ', ''))} · ${RadioUi.esc(r.category || '')}</div>` :
                            '';
                        return sn + type;
                    }
                },
                {
                    key: 'user_name',
                    sortable: true,
                    className: 'radio-user',
                    render: r => RadioUi.dash(r.user_name) +
                        (r.department ? `<div class="radio-muted">${RadioUi.esc(r.department)}</div>` : '')
                },
                {
                    key: 'company',
                    render: r => {
                        const owner = r.owner && r.owner !== r.company ?
                            `<div class="radio-muted">aset ${RadioUi.esc(r.owner)}</div>` : '';
                        return RadioUi.dash(r.company) + owner;
                    }
                },
                {
                    // MTI or Non MTI, by the company that owns the radio (Pemilik Aset); "-" while that is not filled in.
                    key: 'owner_group',
                    render: r => r.owner_group ? RadioUi.badge(r.owner_group, r.owner_group === 'MTI' ? 'blue' : 'orange') : RadioUi.dash(null)
                },
                { key: 'template', render: r => RadioUi.dash(r.template) },
                {
                    key: 'placement',
                    render: r => r.archived ?
                        `${RadioUi.badge('Arsip', 'secondary')}<div class="radio-muted">${RadioUi.esc(r.archive_reason || '')}</div>` :
                        RadioUi.badge(r.placement.label, r.placement.color)
                },
                { key: 'condition', render: r => RadioUi.badge(r.condition.label, r.condition.color) },
                { key: 'last_verified_at', sortable: true, render: r => RadioUi.dash(r.last_verified_at) },
                { key: 'issues', render: r => RadioUi.issues(r.issues, 1) },
                {
                    key: 'actions',
                    className: 'text-center',
                    render: r => {
                        // Values travel in data-* attributes (read with .data()), never inside inline JS.
                        const label = RadioUi.esc(`${r.dmr_id ? 'ID ' + r.dmr_id + ' · ' : ''}${r.serial_number || ''}`);
                        const archiveButton = r.archived ?
                            `<button type="button" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-green" data-bs-toggle="tooltip" data-bs-title="Pulihkan" data-action="restore" data-id="${r.id}" data-label="${label}"><i class="ti ti-archive-off"></i></button>` :
                            `<button type="button" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-orange" data-bs-toggle="tooltip" data-bs-title="Arsipkan" data-action="archive" data-id="${r.id}" data-label="${label}"><i class="ti ti-archive"></i></button>`;
                        return `<div class="d-inline-flex gap-1">
                            <a href="${RadioUi.esc(r.detail_url)}" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-blue" data-bs-toggle="tooltip" data-bs-title="Detail & Riwayat"><i class="ti ti-eye"></i></a>
                            <button type="button" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-secondary" data-bs-toggle="tooltip" data-bs-title="Cetak form (IT Equipment Checkout Form)" aria-label="Cetak form" data-action="print" data-url="${RadioUi.esc(r.print_url)}" data-label="${label}"><i class="ti ti-printer"></i></button>
                            <button type="button" class="btn btn-sm px-2 py-1 rounded-2 mdka-btn-orange-500 text-white border-0" data-bs-toggle="tooltip" data-bs-title="Edit" data-action="edit" data-id="${r.id}"><i class="ti ti-edit"></i></button>
                            ${archiveButton}
                        </div>`;
                    }
                },
            ],

            params() {
                return {
                    page: radioTable.page,
                    paginated: radioTable.limit,
                    search: radioTable.search,
                    orderBy: radioTable.orderBy,
                    orderDir: radioTable.orderDir,
                    scope: radioTable.scope,
                    ...radioFilter.values,
                };
            },

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#radioTable',
                    columns: radioTable.columns,
                    currentPage: radioTable.page,
                    limit: radioTable.limit,
                    emptyText: 'Tidak ada data radio',
                    currentSortKey: radioTable.orderBy,
                    currentSortDir: radioTable.orderDir,
                    onPageChange: page => {
                        radioTable.page = page;
                        radioTable.load();
                    },
                    onLimitChange: limit => {
                        radioTable.limit = limit;
                        radioTable.page = 1;
                        radioTable.load();
                    },
                    onSortChange: (key, dir) => {
                        radioTable.orderBy = key;
                        radioTable.orderDir = dir;
                        radioTable.load();
                    },
                    ...extra,
                });
            },

            load() {
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                radioBulk.loading();
                radioTable.render({ loading: true });
                const issue = radioFilter.values.issue;
                $('#radioIssueChip').toggleClass('d-none', !issue);
                $('#radioIssueChipLabel').text(issue && radioIssues[issue] ? radioIssues[issue].label : '');

                $.getJSON(radioTable.listUrl, radioTable.params())
                    .done(response => {
                        const page = RadioUi.pageAfter(response, radioTable.page);
                        if (page) {
                            radioTable.page = page;
                            return radioTable.load();
                        }
                        radioTable.last = response;
                        radioTable.render({ data: response.data, meta: response.meta });
                        RadioUi.tooltips('#radioTable');
                        radioBulk.shown(response.data, response.meta);
                    })
                    .fail(() => radioTable.render({ emptyText: 'Gagal memuat data. Coba lagi.' }));
            },

            /** The page that is on screen drawn again (its boxes ticked or not), without asking the server. */
            redraw() {
                if (!radioTable.last) return;
                radioTable.render({ data: radioTable.last.data, meta: radioTable.last.meta });
                RadioUi.tooltips('#radioTable');
            },

            export() {
                const params = radioTable.params();
                delete params.page;
                delete params.paginated;
                window.location = radioTable.exportUrl + '?' + $.param(params);
            },
        };

        $(function() {
            // Deep link from the dashboard, e.g. ?issue=sn_duplicate or ?placement=hilang
            const query = new URLSearchParams(window.location.search);
            if (query.get('issue')) {
                $('#filterIssue').val(query.get('issue'));
            }
            if (query.get('placement')) {
                $('#filterPlacement').val([query.get('placement')]).trigger('change');
            }
            if (query.get('condition')) {
                $('#filterCondition').val([query.get('condition')]).trigger('change');
            }
            if (query.get('scope')) {
                $(`input[name="radioScope"][value="${query.get('scope')}"]`).prop('checked', true);
                radioTable.scope = query.get('scope');
            }
            radioFilter.values = radioFilter.read();
            radioTable.load();

            $('#radioSearchButton').on('click', () => {
                radioTable.search = $('#radioSearch').val().trim();
                radioTable.page = 1;
                radioTable.load();
            });
            $('#radioSearch').on('keydown', e => {
                if (e.key === 'Enter') $('#radioSearchButton').trigger('click');
            });
            $('#radioFilterButton').on('click', () => $('#radioFilterCard').slideToggle(300));
            $('#radioResetButton').on('click', () => {
                $('#radioSearch').val('');
                radioTable.search = '';
                radioTable.orderBy = 'dmr_id';
                radioTable.orderDir = 'asc';
                $('#radioFilterCard').slideUp(300);
                radioFilter.reset();
            });
            $('input[name="radioScope"]').on('change', function() {
                radioTable.scope = $(this).val();
                radioTable.page = 1;
                radioTable.load();
            });

            $('#radioTable').on('click', 'button[data-action]', function() {
                const button = $(this);
                button.tooltip('hide');
                const id = button.data('id');
                switch (button.data('action')) {
                    case 'edit':
                        radioForm.open('edit', id);
                        break;
                    case 'archive':
                        radioArchive.open(id, button.data('label'));
                        break;
                    case 'restore':
                        radioArchive.restore(id, button.data('label'));
                        break;
                    case 'print':
                        radioPrint.open(button.data('url'), button.data('label'));
                        break;
                }
            });

            radioForm.onSaved = () => radioTable.load();
            radioArchive.onDone = () => radioTable.load();
        });
    </script>
@endpush
