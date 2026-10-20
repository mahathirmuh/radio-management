@extends('main.index')

@section('page-title', 'Persetujuan Edit')

@push('style')
    <style>
        #eaTable td {
            vertical-align: top;
        }

        #eaTable td.ea-value {
            min-width: 14rem;
            max-width: 24rem;
            white-space: normal;
            word-break: break-word;
        }

        /* What the cell holds and what it becomes, in the colours of a change (in the table and in the question). */
        #eaTable .ea-old,
        #eaTable .ea-new {
            display: inline-block;
            max-width: 100%;
            padding: 1px 6px;
            border-radius: 4px;
        }

        #eaTable .ea-new {
            margin-top: 4px;
        }

        .ea-old {
            background: var(--rm-error-50);
            color: var(--rm-error-700);
            text-decoration: line-through;
        }

        .ea-new {
            background: var(--rm-success-50);
            color: var(--rm-success-700);
            font-weight: 500;
        }

        .ea-old.ea-empty,
        .ea-new.ea-empty {
            font-style: italic;
            text-decoration: none;
        }

        /* The question before deciding: where the cell is, and its value now and after, side by side. */
        .ea-question {
            color: var(--rm-gray-700);
            font-size: 13px;
            line-height: 20px;
        }

        .ea-diff {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin: 10px 0;
            border: 1px solid var(--rm-gray-200);
            border-radius: 8px;
            overflow: hidden;
            line-height: 18px;
        }

        .ea-diff > span {
            padding: 9px 12px;
            overflow-wrap: anywhere;
        }

        .ea-diff > .ea-diff-head {
            padding: 8px 12px;
            background: var(--rm-gray-50);
            color: var(--rm-gray-500);
            font-size: 12px;
            font-weight: 600;
        }

        .ea-question .ea-by {
            color: var(--rm-gray-500);
            font-size: 12px;
        }
    </style>
@endpush

@section('content')
    <div class="rm-docs" id="eaPage">
        <div class="rm-head">
            <div>
                <h1 class="rm-title">Persetujuan Edit</h1>
                <p class="rm-sub">Pengajuan perubahan sel ICT Masterlist, yang menunggu disetujui oleh orang lain.</p>
            </div>
        </div>
        <div class="rm-card">
            <div>
                <div class="rm-tabs" id="eaTabs" role="group" aria-label="Status pengajuan"></div>
            </div>

            <div class="rm-toolbar">
                <label class="rm-search rm-search-wide" for="eaSearch">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input type="search" id="eaSearch" autocomplete="off" maxlength="100" aria-label="Cari pengajuan"
                        placeholder="Sel, kolom, nomor dokumen, nilai, nama pengaju, …">
                </label>
                <button type="button" class="btn-rm secondary" onclick="eaPage.reset()" data-bs-toggle="tooltip"
                    data-bs-title="Reset pencarian dan status"><i class="ti ti-restore" aria-hidden="true"></i> Reset</button>
                <button type="button" class="btn-rm secondary" id="eaReload" onclick="eaPage.load()">
                    <i class="ti ti-refresh" aria-hidden="true"></i> Muat ulang
                </button>
            </div>

            <div class="rm-docs-help">
                Perubahan sel workbook SharePoint yang diajukan dari halaman ICT Masterlist. Isi workbook <b>baru berubah setelah
                disetujui</b> di sini oleh orang lain, bukan oleh pengajunya. Saat disetujui, aplikasi membaca workbook yang
                sekarang dan menulis hanya jika sel dan barisnya masih seperti saat diajukan; kalau sudah berubah, statusnya
                <b>Konflik</b> dan tidak ada yang ditulis.
            </div>
            <div id="eaNotice"></div>

            <div class="rm-box">
                <div class="table-responsive">
                    <table class="rm-table" id="eaTable">
                        <thead>
                            <tr>
                                <th data-key="id" width="60">No</th>
                                <th data-key="requested">Diajukan</th>
                                <th data-key="cell">Sel</th>
                                <th data-key="value">Nilai</th>
                                <th data-key="status">Status</th>
                                <th data-key="actions">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="pagination-container"></div>
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
                pending: 'yellow',
                processing: 'blue',
                applied: 'green',
                rejected: 'red',
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
                    render: r => `<div class="rm-strong">${eaUi.esc(r.requested_by)}${r.mine ? ' (Anda)' : ''}</div><div class="rm-line2">${eaUi.esc(r.requested_at)}</div>`
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
                        if (r.status === 'applied' && r.decided_by) {
                            // Written straight away by who may approve (K13h): nobody else approved it.
                            more = r.direct ? `Ditulis langsung oleh ${eaUi.esc(r.decided_by)}, ${eaUi.esc(r.applied_at || r.decided_at)}` :
                                `Disetujui ${eaUi.esc(r.decided_by)}, ${eaUi.esc(r.applied_at || r.decided_at)}`;
                        }
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
                            buttons.push(`<button type="button" class="btn-rm sm success ea-approve" data-id="${Number(r.id)}"><i class="ti ti-check"></i> Setujui</button>`);
                        }
                        if (r.can_reject) {
                            buttons.push(`<button type="button" class="btn-rm sm secondary ea-reject" data-id="${Number(r.id)}"><i class="ti ti-x"></i> Tolak</button>`);
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
                    eaPage.tabs(response.counts);
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

            /** The statuses that have a tab only while some requests have them (or while their tab is open). */
            seldom: ['processing', 'conflict', 'failed'],

            /** One tab per status with how many requests have it; the tab that is open shows them, and a second click shows all. */
            tabs(counts) {
                const focused = document.activeElement;
                const had = focused && focused.matches && focused.matches('#eaTabs button.ea-tab') ? focused.getAttribute('data-status') : null;
                const total = Object.values(counts).reduce((sum, n) => sum + Number(n || 0), 0);
                const tabs = [{ status: '', label: 'Semua', count: total }].concat(
                    Object.keys(eaPage.labels).filter(status => !eaPage.seldom.includes(status) || Number(counts[status]) > 0 || eaPage.status === status)
                    .map(status => ({ status, label: eaPage.labels[status], count: counts[status] || 0 })));
                $('#eaTabs').html(tabs.map(tab => {
                    const active = eaPage.status === tab.status;
                    return `<button type="button" class="ea-tab${active ? ' is-on' : ''}" data-status="${eaUi.esc(tab.status)}" aria-pressed="${active}">` +
                        `${eaUi.esc(tab.label)}<span class="rm-tab-count">${Number(tab.count) || 0}</span></button>`;
                }).join(''));
                if (had !== null) {
                    const again = document.querySelector(`#eaTabs button.ea-tab[data-status="${had}"]`);
                    if (again) again.focus();
                }
                rmMotion.ink('#eaTabs');
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
                return `<div class="ea-question">` +
                    `<div><b>${eaUi.esc(row.workbook)}</b> · sheet ${eaUi.esc(row.sheet)} · sel <b>${eaUi.esc(row.cell)}</b>` +
                    `${row.column ? ' (' + eaUi.esc(row.column) + ')' : ''}</div>` +
                    `<div class="ea-diff"><span class="ea-diff-head">Sekarang</span><span class="ea-diff-head">Menjadi</span>` +
                    `<span class="ea-old">${eaUi.esc(row.old)}</span><span class="ea-new">${eaUi.esc(row.new)}</span></div>` +
                    `<div class="ea-by">Diajukan oleh ${eaUi.esc(row.requested_by)}, ${eaUi.esc(row.requested_at)}.</div></div>`;
            },

            decide(id) {
                const row = eaPage.rows[id];
                if (!row) return;
                rmDialog.confirm({
                    tone: 'green',
                    icon: 'check',
                    title: 'Setujui perubahan ini?',
                    html: eaPage.question(row) +
                        '<div class="rm-alert warning mt-3"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span>Workbook di SharePoint akan langsung diubah.</span></div>',
                    confirmText: '<i class="ti ti-check"></i> Setujui dan tulis',
                    confirmKind: 'success',
                    // Kept open while SharePoint is written to, which can take a few seconds.
                    loader: true,
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
                        rmFeedback.toast(done.response.message);
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
                rmDialog.confirm({
                    tone: 'red',
                    icon: 'x',
                    title: 'Tolak perubahan ini?',
                    html: eaPage.question(row),
                    input: 'textarea',
                    inputLabel: 'Alasan (boleh dikosongkan)',
                    inputAttributes: { maxlength: 500 },
                    confirmText: '<i class="ti ti-x"></i> Tolak',
                    confirmKind: 'destructive',
                    loader: true,
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
                        rmFeedback.toast(done.response.message);
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
            $('#eaTabs').on('click', 'button.ea-tab', function() {
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
