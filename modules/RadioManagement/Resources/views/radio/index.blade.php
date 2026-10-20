@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
    <style>
        #radioTable > tbody > tr > td {
            white-space: nowrap;
        }

        #radioTable > tbody > tr > td.radio-checks {
            min-width: 130px;
            white-space: normal;
        }

        #radioTable > tbody > tr > td.radio-user {
            min-width: 140px;
            max-width: 220px;
            white-space: normal;
        }
    </style>
@endpush

@include('RadioManagement::partials.scripts')

@php
    // What the role may do on "Register Radio" (the server checks again): add a radio; edit, archive, verify (Update);
    // delete (Delete). The bar of the radios ticked offers the same.
    $radioMay = [
        'create' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Create'),
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.radio', 'Delete'),
    ];
    $bulkMay = ['update' => $radioMay['update'], 'delete' => $radioMay['delete']];
    // The names of what can be chosen in the filter, for the chips of the filters that are on.
    $filterNames = [
        'company' => $companies->pluck('code', 'id'),
        'owner' => $companies->pluck('code', 'id'),
        'owner_group' => ['mti' => 'MTI', 'non_mti' => 'Non MTI'],
        'department' => $departments->pluck('name', 'id'),
        'type' => $types->pluck('name', 'id'),
        'template' => $templates->pluck('name', 'id'),
        'placement' => collect($placements)->pluck('label', 'value'),
        'condition' => collect($conditions)->pluck('label', 'value'),
        'issue' => collect($issues)->map(fn ($issue) => $issue['label']),
    ];
@endphp

@section('page-title', 'Register Radio')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">Register Radio</h1>
            <p class="rm-sub" id="radioSummary">Memuat daftar radio…</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-toolbar rm-toolbar-tight">
            <label class="rm-search" for="radioSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="radioSearch" autocomplete="off" placeholder="Cari SN, Radio ID, MDC, user…"
                    aria-label="Cari radio" />
            </label>
            <button type="button" class="btn-rm secondary" id="radioFilterButton" aria-expanded="false"
                aria-controls="radioFilterCard">
                Filter <i class="ti ti-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn-rm secondary" id="radioResetButton">Reset</button>
            <div class="rm-seg sm ms-auto" role="radiogroup" aria-label="Lingkup data">
                <input type="radio" class="btn-check" name="radioScope" id="scopeActive" value="active" checked>
                <label for="scopeActive">Aktif</label>
                <input type="radio" class="btn-check" name="radioScope" id="scopeArchived" value="archived">
                <label for="scopeArchived">Arsip</label>
                <input type="radio" class="btn-check" name="radioScope" id="scopeAll" value="all">
                <label for="scopeAll">Semua</label>
            </div>
            <span class="d-flex gap-2">
                <button type="button" class="btn-rm secondary" onclick="radioTable.export()">
                    <i class="ti ti-file-spreadsheet" aria-hidden="true"></i> Export Excel
                </button>
                @if ($radioMay['create'])
                    <button type="button" class="btn-rm primary" id="radioAddButton" onclick="radioForm.open('add')">
                        <i class="ti ti-plus" aria-hidden="true"></i> Tambah radio
                    </button>
                @endif
            </span>
        </div>

        @include('RadioManagement::radio.filter')

        <div class="rm-applied d-none" id="radioApplied">
            Diterapkan:
            <span class="d-inline-flex flex-wrap gap-2" id="radioAppliedChips"></span>
        </div>

        @include('RadioManagement::radio.bulk-bar')

        <div class="rm-box">
            <div class="table-responsive">
                <table class="rm-table" id="radioTable">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container"></div>
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
        const radioMay = @json($radioMay);
        const radioFilterNames = @json($filterNames);

        const radioTable = {
            listUrl: "{{ route('radio-management.radio.list') }}",
            exportUrl: "{{ route('radio-management.radio.export') }}",
            page: 1,
            limit: 25,
            search: '',
            orderBy: 'dmr_id',
            orderDir: 'asc',
            scope: 'active',

            /** The titles of the columns: the archived radios have their own (where they went, when, why). */
            heads: {
                active: [
                    ['', 'rm-pick', 'pick'], ['No', 'text-center'], ['Radio ID', '', 'dmr_id'], ['SN / Tipe', '', 'serial_number'],
                    ['User / Department', '', 'user_name'], ['Company'], ['Owner'], ['Template'], ['Posisi'], ['Kondisi'],
                    ['Verifikasi', '', 'last_verified_at'], ['Cek'], ['Aksi', 'text-end rm-sticky-end'],
                ],
                archived: [
                    ['', 'rm-pick', 'pick'], ['No', 'text-center'], ['Radio ID', '', 'dmr_id'], ['SN / Tipe', '', 'serial_number'],
                    ['User terakhir', '', 'user_name'], ['Diarsipkan'], ['Alasan'], ['Aksi', 'text-end rm-sticky-end'],
                ],
            },

            columns: {
                // The box that ticks the radio for the bar of radio.bulk.
                pick: { key: 'pick', className: 'rm-pick', render: r => radioBulk.cell(r) },
                index: 'index',
                dmr_id: {
                    key: 'dmr_id',
                    sortable: true,
                    render: r => {
                        if (!r.dmr_id) {
                            return r.previous_dmr_id ?
                                `<div class="rm-strong" title="ID terakhir diketahui">ex ${RadioUi.esc(r.previous_dmr_id)}</div>` :
                                RadioUi.dash();
                        }
                        return `<div class="rm-strong">${RadioUi.esc(r.dmr_id)}</div><div class="rm-line2 radio-mono">MDC ${RadioUi.esc(r.mdc)}</div>`;
                    }
                },
                serial_number: {
                    key: 'serial_number',
                    sortable: true,
                    render: r => {
                        const sn = r.serial_number ? `<div class="radio-mono">${RadioUi.esc(r.serial_number)}</div>` : RadioUi.dash();
                        const type = r.type ?
                            `<div class="rm-line2">${RadioUi.esc(r.type.replace('MotoTrbo XiR ', ''))}${r.category ? ' · ' + RadioUi.esc(r.category) : ''}</div>` :
                            '';
                        return sn + type;
                    }
                },
                user_name: {
                    key: 'user_name',
                    sortable: true,
                    className: 'radio-user',
                    render: r => `<div>${RadioUi.dash(r.user_name)}</div>` +
                        (r.department ? `<div class="rm-line2">${RadioUi.esc(r.department)}</div>` : '')
                },
                company: {
                    key: 'company',
                    render: r => {
                        const owner = r.owner && r.owner !== r.company ?
                            `<div class="rm-line2">aset ${RadioUi.esc(r.owner)}</div>` : '';
                        return `<div>${RadioUi.dash(r.company)}</div>` + owner;
                    }
                },
                // MTI or Non MTI, by the company that owns the radio (Pemilik Aset); "-" while that is not filled in.
                owner_group: {
                    key: 'owner_group',
                    render: r => r.owner_group ? RadioUi.badge(r.owner_group, r.owner_group === 'MTI' ? 'blue' : 'orange') : RadioUi.dash(null)
                },
                template: { key: 'template', render: r => RadioUi.dash(r.template) },
                placement: {
                    key: 'placement',
                    render: r => r.archived ?
                        `${RadioUi.badge('Arsip', 'secondary')}<div class="rm-line2">${RadioUi.esc(r.archive_reason || '')}</div>` :
                        RadioUi.badge(r.placement.label, r.placement.color)
                },
                condition: { key: 'condition', render: r => RadioUi.badge(r.condition.label, r.condition.color) },
                last_verified_at: { key: 'last_verified_at', sortable: true, render: r => RadioUi.dash(r.last_verified_at) },
                issues: { key: 'issues', className: 'radio-checks', render: r => RadioUi.issues(r.issues, 1) },
                archived_at: { key: 'archived_at', render: r => RadioUi.dash(r.archived_at) },
                archive_reason: { key: 'archive_reason', render: r => r.archive_reason ? RadioUi.badge(r.archive_reason, RadioUi.reasonColor(r.archive_reason)) : RadioUi.dash(null) },
                actions: {
                    key: 'actions',
                    className: 'text-end rm-sticky-end',
                    render: r => {
                        // Values travel in data-* attributes (read with .data()), never inside inline JS. An archived radio is
                        // only looked at or brought back; a role that may not change radios looks and prints.
                        const label = RadioUi.esc(`${r.dmr_id ? 'ID ' + r.dmr_id + ' · ' : ''}${r.serial_number || ''}`);
                        return `<div class="rm-acts">
                            <a href="${RadioUi.esc(r.detail_url)}" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Detail & Riwayat" aria-label="Detail & Riwayat"><i class="ti ti-eye"></i></a>
                            ${r.archived ? '' : `<button type="button" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Cetak form (IT Equipment Checkout Form)" aria-label="Cetak form" data-action="print" data-url="${RadioUi.esc(r.print_url)}" data-label="${label}"><i class="ti ti-printer"></i></button>`}
                            ${radioMay.update && !r.archived ? `<button type="button" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Edit" aria-label="Edit" data-action="edit" data-id="${r.id}"><i class="ti ti-edit"></i></button>` : ''}
                            ${!radioMay.update ? '' : (r.archived ?
                                `<button type="button" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Pulihkan" aria-label="Pulihkan" data-action="restore" data-id="${r.id}" data-label="${label}"><i class="ti ti-archive-off"></i></button>` :
                                `<button type="button" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Arsipkan" aria-label="Arsipkan" data-action="archive" data-id="${r.id}" data-label="${label}"><i class="ti ti-archive"></i></button>`)}
                        </div>`;
                    }
                },
            },

            /** The columns of a scope, in the order of its titles. */
            columnsOf(scope) {
                const keys = scope === 'archived' ?
                    ['pick', 'index', 'dmr_id', 'serial_number', 'user_name', 'archived_at', 'archive_reason', 'actions'] :
                    ['pick', 'index', 'dmr_id', 'serial_number', 'user_name', 'company', 'owner_group', 'template', 'placement', 'condition', 'last_verified_at', 'issues', 'actions'];
                return keys.map(key => radioTable.columns[key]);
            },

            /** The header row of a scope; the box that ticks the page is in it, and gets its handler again. */
            head(scope) {
                const titles = radioTable.heads[scope === 'archived' ? 'archived' : 'active'];
                const cells = titles.map(([title, className = '', key = '']) => {
                    if (key === 'pick') {
                        return '<th class="rm-pick"><input type="checkbox" class="form-check-input" id="radioPickPage" aria-label="Pilih semua radio di halaman ini"></th>';
                    }
                    const attrs = `${className ? ` class="${className}"` : ''}${key ? ` data-key="${key}"` : ''}`;
                    return className.includes('text-end') ? `<th${attrs}><span class="visually-hidden">${title}</span></th>` : `<th${attrs}>${title}</th>`;
                }).join('');
                $('#radioTable thead').html(`<tr>${cells}</tr>`);
                radioBulk.bindPage();
            },

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
                    columns: radioTable.columnsOf(radioTable.scope),
                    currentPage: radioTable.page,
                    limit: radioTable.limit,
                    emptyText: 'Tidak ada data radio',
                    currentSortKey: radioTable.orderBy,
                    currentSortDir: radioTable.orderDir,
                    paginationLabels: { showing: 'Menampilkan', of: 'dari', rows: 'Baris per halaman', noun: 'radio', previous: 'Halaman sebelumnya', next: 'Halaman berikutnya' },
                    // A click on a row opens the radio (its buttons, links and box do what they do).
                    rowAttrs: r => `data-href="${RadioUi.esc(r.detail_url)}"`,
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

            /** "Menampilkan 25 dari 247 radio aktif" above the card. */
            summary(meta) {
                const what = { active: 'radio aktif', archived: 'radio diarsipkan', all: 'radio (aktif dan arsip)' }[radioTable.scope] || 'radio';
                const shown = meta && meta.total ? Math.max(0, (meta.to || 0) - (meta.from || 0) + 1) : 0;
                $('#radioSummary').text(!meta ? '' : (meta.total ? `Menampilkan ${shown} dari ${meta.total} ${what}` : `Tidak ada ${what}`));
            },

            load() {
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                radioBulk.loading();
                radioTable.render({ loading: true });
                radioFilter.chips();

                $.getJSON(radioTable.listUrl, radioTable.params())
                    .done(response => {
                        const page = RadioUi.pageAfter(response, radioTable.page);
                        if (page) {
                            radioTable.page = page;
                            return radioTable.load();
                        }
                        radioTable.last = response;
                        radioTable.render({ data: response.data, meta: response.meta });
                        radioTable.summary(response.meta);
                        RadioUi.tooltips('#radioTable');
                        radioBulk.shown(response.data, response.meta);
                    })
                    .fail(() => radioTable.render({ emptyText: 'Gagal memuat data. Coba lagi.' }))
                    .always(() => radioFilter.busy(false));
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
            radioTable.head(radioTable.scope);
            radioTable.load();

            // The list follows what is typed (a moment after the last key), and Enter at once.
            const search = () => {
                const value = String($('#radioSearch').val() || '').trim();
                if (value === radioTable.search) return;
                radioTable.search = value;
                radioTable.page = 1;
                radioTable.load();
            };
            const typed = debounce(search, 350);
            $('#radioSearch').on('input', typed);
            $('#radioSearch').on('keydown', e => {
                if (e.key === 'Enter') search();
            });
            $('#radioFilterButton').on('click', () => radioFilter.toggle());
            $('#radioResetButton').on('click', () => {
                $('#radioSearch').val('');
                radioTable.search = '';
                radioTable.orderBy = 'dmr_id';
                radioTable.orderDir = 'asc';
                radioFilter.toggle(false);
                radioFilter.reset();
            });
            $('input[name="radioScope"]').on('change', function() {
                radioTable.scope = $(this).val();
                radioTable.page = 1;
                radioTable.head(radioTable.scope);
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
            $('#radioTable').on('click', 'tbody tr[data-href]', function(event) {
                if (event.target.closest('a, button, input, label, .rm-pick')) return;
                window.location.href = $(this).data('href');
            });

            radioForm.onSaved = () => radioTable.load();
            radioArchive.onDone = () => radioTable.load();
        });
    </script>
@endpush
