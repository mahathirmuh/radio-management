@extends('main.index')

@section('page-title', 'Persetujuan Edit')

@push('style')
    <style>
        .ea-muted {
            color: var(--tblr-secondary);
            font-size: 0.7rem;
        }

        #eaTable td {
            vertical-align: top;
        }

        #eaTable td.ea-value {
            min-width: 14rem;
            max-width: 24rem;
            white-space: normal;
            word-break: break-word;
        }

        #eaTable .ea-old {
            color: var(--tblr-secondary);
            text-decoration: line-through;
        }

        #eaTable .ea-new {
            font-weight: 600;
        }

        #eaTable .ea-empty {
            font-style: italic;
        }

        #eaCards .ea-card {
            cursor: pointer;
            text-align: left;
            border: 1px solid var(--tblr-border-color);
        }

        #eaCards .ea-card:hover,
        #eaCards .ea-card.active {
            border-color: var(--tblr-primary);
        }

        #eaCards .ea-card.active {
            box-shadow: 0 0 0 1px var(--tblr-primary);
        }

        #eaCards .ea-card:focus-visible {
            outline: 2px solid var(--tblr-primary);
            outline-offset: 2px;
        }

        #eaCards .ea-card-label {
            font-size: 0.7rem;
            color: var(--tblr-secondary);
        }

        #eaCards .ea-card-count {
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.2;
        }
    </style>
@endpush

@section('content')
    <div class="row g-2" id="eaPage">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label for="eaSearch" class="form-label">Cari</label>
                            <input type="text" id="eaSearch" class="form-control" autocomplete="off" maxlength="100"
                                placeholder="Sel, kolom, nomor dokumen, nilai, nama pengaju, …">
                        </div>
                        <div class="col-md-7 d-flex gap-2 flex-wrap">
                            <x-global.btn-detail label="" color="red" size="sm" outline="true" onclick="eaPage.reset()"
                                data-bs-toggle="tooltip" data-bs-title="Reset pencarian dan status">
                                <x-slot:icon>
                                    <i class="ti ti-restore"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                            <x-global.btn-detail label="Muat ulang" color="blue" size="sm" id="eaReload"
                                onclick="eaPage.load()">
                                <x-slot:icon>
                                    <i class="ti ti-refresh"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>

                        <div class="col-12">
                            <div class="ea-muted">
                                Perubahan sel workbook SharePoint yang diajukan dari halaman ICT Masterlist. Isi workbook
                                <b>baru berubah setelah disetujui</b> di sini oleh orang lain, bukan oleh pengajunya. Saat disetujui,
                                aplikasi membaca workbook yang sekarang dan menulis hanya jika sel dan barisnya masih seperti saat
                                diajukan; kalau sudah berubah, statusnya <b>Konflik</b> dan tidak ada yang ditulis.
                            </div>
                            <div id="eaNotice"></div>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="row g-2" id="eaCards" role="group" aria-label="Status pengajuan"></div>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 mb-0" id="eaTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50">
                                                <th class="fs-5 py-3" data-key="id" width="60">No</th>
                                                <th class="fs-5 py-3" data-key="requested">Diajukan</th>
                                                <th class="fs-5 py-3" data-key="cell">Sel</th>
                                                <th class="fs-5 py-3" data-key="value">Nilai</th>
                                                <th class="fs-5 py-3" data-key="status">Status</th>
                                                <th class="fs-5 py-3" data-key="actions">Aksi</th>
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
    <script>
        /** Everything shown comes from the database: it is escaped, and only an address of this site becomes a link. */
        const eaUi = {
            esc(value) {
                if (value === null || value === undefined) return '';
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            },
            isPath(url) {
                return typeof url === 'string' && /^\/(?![\/\\])[^\s\\]*$/.test(url);
            },
            tones: {
                pending: 'blue',
                processing: 'azure',
                applied: 'green',
                rejected: 'secondary',
                conflict: 'orange',
                failed: 'red',
            },
            badge(row) {
                const tone = Object.prototype.hasOwnProperty.call(eaUi.tones, row.status) ? eaUi.tones[row.status] : 'secondary';
                return `<span class="badge bg-${tone}-lt">${eaUi.esc(row.status_label)}</span>`;
            },
            /** What is kept of the value: nothing typed is shown as markup; an empty cell is said in words. */
            value(text, kind) {
                const empty = text === '(kosong)' || text === '(dikosongkan)';
                return `<span class="${kind}${empty ? ' ea-empty' : ''}">${eaUi.esc(text)}</span>`;
            },
        };

        const eaPage = {
            url: "{{ route('documents.edit-approvals.list') }}",
            approve: "{{ route('documents.edit-approvals.approve', ':id') }}",
            reject: "{{ route('documents.edit-approvals.reject', ':id') }}",
            page: 1,
            limit: 25,
            search: '',
            /** The status shown ('' : all). */
            status: 'pending',
            request: 0,
            /** The rows that are on screen, by id: the buttons ask about them. */
            rows: {},
            labels: @json($statuses),

            columns: [{
                    key: 'id',
                    className: 'text-muted',
                    render: r => eaUi.esc(r.id)
                },
                {
                    key: 'requested',
                    className: '',
                    render: r => `<div>${eaUi.esc(r.requested_at)}</div><div class="ea-muted">${eaUi.esc(r.requested_by)}${r.mine ? ' (Anda)' : ''}</div>`
                },
                {
                    key: 'cell',
                    className: '',
                    render: r => {
                        const name = `<b>${eaUi.esc(r.cell)}</b> <span class="ea-muted">${eaUi.esc(r.sheet)}</span>`;
                        const link = eaUi.isPath(r.open) ? `<a href="${eaUi.esc(r.open)}" title="Lihat baris ini di ${eaUi.esc(r.workbook)}">${name}</a>` : name;
                        return `<div>${link}</div><div class="ea-muted">${eaUi.esc(r.column)}${r.row_key ? ' · baris ' + eaUi.esc(r.row_key) : ''}</div>`;
                    }
                },
                {
                    key: 'value',
                    className: 'ea-value',
                    render: r => `<div>${eaUi.value(r.old, 'ea-old')}</div><div>${eaUi.value(r.new, 'ea-new')}</div>`
                },
                {
                    key: 'status',
                    className: '',
                    render: r => {
                        let more = '';
                        if (r.status === 'applied' && r.decided_by) more = `Disetujui ${eaUi.esc(r.decided_by)}, ${eaUi.esc(r.applied_at || r.decided_at)}`;
                        else if (r.status === 'rejected' && r.decided_by) more = `Ditolak ${eaUi.esc(r.decided_by)}, ${eaUi.esc(r.decided_at)}`;
                        if (r.note) more += `${more ? '<br>' : ''}Alasan: ${eaUi.esc(r.note)}`;
                        if (r.failure) more += `${more ? '<br>' : ''}${eaUi.esc(r.failure)}`;
                        return `${eaUi.badge(r)}${more ? `<div class="ea-muted mt-1">${more}</div>` : ''}`;
                    }
                },
                {
                    key: 'actions',
                    className: 'text-nowrap',
                    render: r => {
                        const buttons = [];
                        if (r.can_approve) {
                            buttons.push(`<button type="button" class="btn btn-sm btn-success ea-approve" data-id="${Number(r.id)}"><i class="ti ti-check"></i> Setujui</button>`);
                        }
                        if (r.can_reject) {
                            buttons.push(`<button type="button" class="btn btn-sm btn-outline-danger ea-reject" data-id="${Number(r.id)}"><i class="ti ti-x"></i> Tolak</button>`);
                        }
                        if (!buttons.length && r.mine && r.status === 'pending') {
                            return '<span class="ea-muted">Menunggu orang lain</span>';
                        }
                        return buttons.join(' ');
                    }
                },
            ],

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#eaTable',
                    columns: eaPage.columns,
                    currentPage: eaPage.page,
                    limit: eaPage.limit,
                    emptyText: 'Tidak ada pengajuan',
                    onPageChange: page => {
                        eaPage.page = page;
                        eaPage.load();
                    },
                    onLimitChange: limit => {
                        eaPage.limit = limit;
                        eaPage.page = 1;
                        eaPage.load();
                    },
                    ...extra,
                });
            },

            /** Only the answer to the latest request is shown. */
            load() {
                const request = ++eaPage.request;
                eaPage.render({ loading: true });
                $('#eaReload').prop('disabled', true);

                $.getJSON(eaPage.url, {
                    page: eaPage.page,
                    paginated: eaPage.limit,
                    search: eaPage.search,
                    status: eaPage.status,
                }).done(response => {
                    if (request !== eaPage.request) return;
                    eaPage.page = response.meta.current_page;
                    eaPage.rows = {};
                    response.data.forEach(row => {
                        eaPage.rows[row.id] = row;
                    });
                    eaPage.cards(response.counts);
                    $('#eaNotice').html(response.may_decide ? '' :
                        '<div class="alert alert-info py-2 small mt-2 mb-0">Role Anda hanya bisa melihat pengajuan. Menyetujui atau menolak perlu hak Update di menu ini.</div>');
                    eaPage.render({
                        data: response.data,
                        meta: response.meta,
                        emptyText: eaPage.empty(),
                    });
                }).fail(xhr => {
                    if (request !== eaPage.request) return;
                    eaPage.render({ emptyText: 'Daftar pengajuan tidak bisa dimuat.' });
                    $('#eaPage .pagination-container').hide();
                    $('#eaNotice').html(`<div class="alert alert-danger py-2 small mt-2 mb-0">${eaUi.esc(eaPage.failure(xhr))}</div>`);
                }).always(() => {
                    if (request === eaPage.request) $('#eaReload').prop('disabled', false);
                });
            },

            empty() {
                const label = eaPage.status !== '' && eaPage.labels[eaPage.status] ? ` dengan status "${eaUi.esc(eaPage.labels[eaPage.status])}"` : '';
                return `Tidak ada pengajuan${label}${eaPage.search ? ' yang cocok dengan pencarian' : ''}.`;
            },

            failure(xhr) {
                if (xhr.status === 401 || xhr.status === 419) return 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.';
                const body = xhr.responseJSON || {};
                const said = typeof body.message === 'string' ? body.message : '';
                // The menu refuses in English: said in Indonesian. This module's own refusals (one's own request, say) are Indonesian and are shown as they are.
                if (xhr.status === 403 && (said === '' || /^You /.test(said))) return 'Role Anda tidak punya izin untuk menu ini.';
                return said !== '' ? said : 'Terjadi kesalahan. Coba lagi beberapa saat lagi.';
            },

            /** One card per status with how many requests have it; the card that is open shows them, and a second click shows all. */
            cards(counts) {
                const focused = document.activeElement;
                const had = focused && focused.matches && focused.matches('#eaCards button.ea-card') ? focused.getAttribute('data-status') : null;
                const total = Object.values(counts).reduce((sum, n) => sum + Number(n || 0), 0);
                const tones = { '': 'blue', ...eaUi.tones };
                const cards = [{ status: '', label: 'Semua', count: total }].concat(
                    Object.keys(eaPage.labels).filter(status => status !== 'processing' || Number(counts[status]) > 0)
                    .map(status => ({ status, label: eaPage.labels[status], count: counts[status] || 0 })));
                $('#eaCards').html(cards.map(card => {
                    const active = eaPage.status === card.status;
                    const tone = Object.prototype.hasOwnProperty.call(tones, card.status) ? tones[card.status] : 'secondary';
                    return `<div class="col-6 col-md"><button type="button" class="card card-sm w-100 ea-card ${active ? 'active' : ''}" data-status="${eaUi.esc(card.status)}" aria-pressed="${active}">` +
                        `<span class="card-body p-2 d-block"><span class="d-flex align-items-center"><span class="badge bg-${tone} me-2">&nbsp;</span>` +
                        `<span class="ea-card-label">${eaUi.esc(card.label)}</span></span><span class="ea-card-count d-block">${Number(card.count) || 0}</span></span></button></div>`;
                }).join(''));
                if (had !== null) {
                    const again = document.querySelector(`#eaCards button.ea-card[data-status="${had}"]`);
                    if (again) again.focus();
                }
            },

            apply() {
                eaPage.search = $('#eaSearch').val().trim();
                eaPage.page = 1;
                eaPage.load();
            },

            reset() {
                $('#eaSearch').val('');
                eaPage.status = 'pending';
                eaPage.apply();
            },

            /** The value as the question shows it: where it was, and what it becomes. */
            question(row) {
                return `<div class="text-start small">` +
                    `<div><b>${eaUi.esc(row.workbook)}</b> · sheet ${eaUi.esc(row.sheet)} · sel <b>${eaUi.esc(row.cell)}</b>` +
                    `${row.column ? ' (' + eaUi.esc(row.column) + ')' : ''}</div>` +
                    `<div class="mt-2">Sekarang: <span class="text-muted">${eaUi.esc(row.old)}</span></div>` +
                    `<div>Menjadi: <b>${eaUi.esc(row.new)}</b></div>` +
                    `<div class="mt-2 text-muted">Diajukan oleh ${eaUi.esc(row.requested_by)}, ${eaUi.esc(row.requested_at)}.</div></div>`;
            },

            decide(id) {
                const row = eaPage.rows[id];
                if (!row) return;
                Swal.fire({
                    title: 'Setujui perubahan ini?',
                    html: eaPage.question(row) + '<div class="alert alert-warning py-2 small mt-3 mb-0 text-start">Workbook di SharePoint akan langsung diubah.</div>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-check"></i> Setujui dan tulis',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
                    showLoaderOnConfirm: true,
                    allowOutsideClick: () => !Swal.isLoading(),
                    // Kept open while SharePoint is written to, which can take a few seconds.
                    preConfirm: () => $.ajax({
                        url: eaPage.approve.replace(':id', row.id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'PATCH' },
                    }).then(response => ({ ok: true, response }), xhr => ({ ok: false, xhr })),
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const done = result.value;
                    if (done.ok) {
                        Swal.fire('Berhasil', eaUi.esc(done.response.message), 'success');
                    } else {
                        // A conflict or a failure is an answer of the page too: the row says why, and the list is read again.
                        const body = done.xhr.responseJSON || {};
                        Swal.fire(done.xhr.status === 409 ? 'Tidak ditulis' : 'Gagal', eaUi.esc(eaPage.failure(done.xhr)), done.xhr.status === 409 || body.status ? 'warning' : 'error');
                    }
                    eaPage.load();
                });
            },

            turnDown(id) {
                const row = eaPage.rows[id];
                if (!row) return;
                Swal.fire({
                    title: 'Tolak perubahan ini?',
                    html: eaPage.question(row),
                    icon: 'warning',
                    input: 'textarea',
                    inputLabel: 'Alasan (boleh dikosongkan)',
                    inputAttributes: { maxlength: 500 },
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-x"></i> Tolak',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    confirmButtonColor: '#d63939',
                    showLoaderOnConfirm: true,
                    preConfirm: note => $.ajax({
                        url: eaPage.reject.replace(':id', row.id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'PATCH', note: note || '' },
                    }).then(response => ({ ok: true, response }), xhr => ({ ok: false, xhr })),
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const done = result.value;
                    if (done.ok) {
                        Swal.fire('Ditolak', eaUi.esc(done.response.message), 'success');
                    } else {
                        Swal.fire('Gagal', eaUi.esc(eaPage.failure(done.xhr)), 'error');
                    }
                    eaPage.load();
                });
            },
        };

        $(function() {
            $('#eaSearch').on('input', debounce(() => {
                if ($('#eaSearch').val().trim() !== eaPage.search) eaPage.apply();
            })).on('keydown', e => {
                if (e.key === 'Enter') eaPage.apply();
            });
            $('#eaCards').on('click', 'button.ea-card', function() {
                const status = $(this).attr('data-status');
                eaPage.status = eaPage.status === status && status !== '' ? '' : status;
                eaPage.page = 1;
                eaPage.load();
            });
            $('#eaTable').on('click', 'button.ea-approve', function() {
                eaPage.decide(Number($(this).attr('data-id')));
            }).on('click', 'button.ea-reject', function() {
                eaPage.turnDown(Number($(this).attr('data-id')));
            });
            // A link may say what to show first (a notification does): ?status=pending&search=ICT/BAK/002 F4
            const asked = new URLSearchParams(window.location.search);
            if (asked.has('status') && (asked.get('status') === '' || Object.prototype.hasOwnProperty.call(eaPage.labels, asked.get('status')))) {
                eaPage.status = asked.get('status');
            }
            if (asked.get('search')) {
                eaPage.search = asked.get('search').trim().slice(0, 100);
                $('#eaSearch').val(eaPage.search);
            }
            eaPage.load();
        });
    </script>
@endpush
