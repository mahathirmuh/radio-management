@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'E-Sign Master')

@push('style')
    <style>
        /* A signature is dark ink on nothing: it is shown on white, whatever the theme. */
        .esign-preview {
            display: inline-block;
            width: 150px;
            height: 48px;
            border-radius: 6px;
            background: #fff;
            box-shadow: inset 0 0 0 1px var(--rm-gray-200);
        }

        .esign-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        #esignPadHost iframe {
            display: block;
            width: 100%;
            height: 480px;
            border: 0;
            border-radius: 8px;
            background: #fff;
            box-shadow: inset 0 0 0 1px var(--rm-gray-200);
        }
    </style>
@endpush

@php
    // What the role may do on "E-Sign Master" (the server checks again).
    $esignMay = [
        'create' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.e-sign', 'Create'),
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.e-sign', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.e-sign', 'Delete'),
    ];
@endphp

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">E-Sign Master</h1>
            <p class="rm-sub">Tanda tangan tim ICT untuk kotak PROCESSED BY di IT Equipment Checkout Form.</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-toolbar rm-toolbar-tight">
            <label class="rm-search" for="esignSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="esignSearch" autocomplete="off" maxlength="100" placeholder="Cari nama atau e-mail…"
                    aria-label="Cari nama atau e-mail" />
            </label>
            <button type="button" class="rm-icon-btn is-ringed rm-icon-btn-lg" id="esignSearchButton" data-bs-toggle="tooltip"
                data-bs-title="Cari" aria-label="Cari"><i class="ti ti-search"></i></button>
            <div class="rm-seg ms-auto" role="radiogroup" aria-label="Tampilan">
                <input type="radio" class="btn-check" name="esignView" id="esignViewGrid" value="grid" checked>
                <label for="esignViewGrid"><i class="ti ti-layout-grid" aria-hidden="true"></i> Grid</label>
                <input type="radio" class="btn-check" name="esignView" id="esignViewList" value="list">
                <label for="esignViewList"><i class="ti ti-list" aria-hidden="true"></i> List</label>
            </div>
            @if ($esignMay['create'])
                <button type="button" class="btn-rm primary" id="esignAdd" onclick="esignForm.open()">
                    <i class="ti ti-plus" aria-hidden="true"></i> Daftarkan tanda tangan
                </button>
            @endif
        </div>

        <div class="rm-banner">
            <i class="ti ti-lock" aria-hidden="true"></i>
            <span>Di form, yang muncul adalah tanda tangan <b>akun yang sedang membuka form itu</b>: tidak ada yang bisa
                menempelkan tanda tangan orang lain. Setiap pemakaian dicatat (tombol riwayat). Gambar disimpan terenkripsi.</span>
        </div>
        <div id="esignNotice"></div>

        <div class="rm-esign-grid" id="esignGrid" aria-live="polite"></div>

        <div class="rm-box d-none" id="esignListBox">
            <div class="table-responsive">
                <table class="rm-table" id="esignTable">
                    <thead>
                        <tr>
                            <th class="text-center" width="30">No</th>
                            <th>Akun</th>
                            <th>Nama tercetak</th>
                            <th>Tanda tangan</th>
                            <th class="text-center">Status</th>
                            <th>Dipakai</th>
                            <th class="text-end"><span class="visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container"></div>
        </div>
    </div>

    {{-- Registers a signature, or changes one. Static: what was signed is lost when the dialog closes. --}}
    <div class="modal fade rm-modal wide" data-bs-backdrop="static" data-bs-keyboard="false" id="esignModal" aria-hidden="true"
        aria-labelledby="esignTitle">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="rm-dialog-head is-ruled">
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="esignTitle">Daftarkan tanda tangan</h3>
                        <p class="rm-dialog-sub">Tanda tangan ini hanya muncul di form yang dibuka dengan akun pemiliknya.</p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body is-form">
                    <div class="row g-3">
                        <div class="col-md-6 rm-field">
                            <label for="esign_user" class="form-label required">Akun pemilik</label>
                            <select id="esign_user" class="form-select"></select>
                            <div class="rm-hint" id="esignUserHint">Tanda tangan ini hanya muncul di form yang dibuka dengan akun ini.</div>
                        </div>
                        <div class="col-md-6 rm-field">
                            <label for="esign_name" class="form-label">Nama tercetak di bawah tanda tangan</label>
                            <input type="text" id="esign_name" class="form-control" maxlength="150" autocomplete="off"
                                placeholder="Kosongkan untuk memakai nama akun">
                        </div>
                        <div class="col-12 d-none" id="esignActiveWrap">
                            <label class="form-check form-switch mt-1 mb-0">
                                <input class="form-check-input" type="checkbox" id="esign_active" checked>
                                <span class="form-check-label">Aktif (nonaktif: tidak muncul di form, kotaknya ditandatangani di kertas)</span>
                            </label>
                        </div>
                        <div class="col-12 rm-field d-none" id="esignCurrentWrap">
                            <span class="form-label">Tanda tangan sekarang</span>
                            <span class="esign-preview" id="esignCurrent"></span>
                        </div>
                        <div class="col-12 rm-field">
                            <span class="form-label" id="esignPadLabel">Tanda tangan</span>
                            <div id="esignPadHost"></div>
                        </div>
                        <div class="col-12" role="alert">
                            <div class="rm-alert danger d-none" id="esignError"></div>
                        </div>
                    </div>
                </div>
                <div class="rm-dialog-foot">
                    <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn-rm primary" id="esignSave" onclick="esignForm.submit()">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Where a signature was put on a form. --}}
    <div class="modal fade rm-modal" id="esignUsesModal" aria-hidden="true" aria-labelledby="esignUsesTitle">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="rm-dialog-head">
                    <span class="rm-dialog-icon"><i class="ti ti-history" aria-hidden="true"></i></span>
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title" id="esignUsesTitle">Riwayat pemakaian</h3>
                        <p class="rm-dialog-sub" id="esignUsesLabel"></p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body">
                    <div id="esignUsesList"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const esignMay = @json($esignMay);
        const esignUrls = {
            list: "{{ route('radio-management.e-sign.list') }}",
            pad: "{{ route('radio-management.e-sign.pad') }}",
            uses: "{{ route('radio-management.e-sign.uses', ':id') }}",
            store: "{{ route('radio-management.e-sign.store') }}",
            update: "{{ route('radio-management.e-sign.update', ':id') }}",
            destroy: "{{ route('radio-management.e-sign.destroy', ':id') }}",
        };

        const esignUi = {
            /** A picture is shown only when it is what the server keeps: a PNG inside the page, never an address somewhere else. */
            isPicture(url) {
                return typeof url === 'string' && /^data:image\/png;base64,[A-Za-z0-9+\/=]+$/.test(url);
            },
            preview(url, name) {
                return esignUi.isPicture(url) ?
                    `<span class="esign-preview"><img src="${url}" alt="${RadioUi.esc('Tanda tangan ' + (name || ''))}"></span>` :
                    '<span class="radio-muted">-</span>';
            },
        };

        const esignTable = {
            page: 1,
            limit: 25,
            search: '',
            request: 0,
            /** The rows on screen by id, and the accounts a signature can still be registered for. */
            rows: {},
            candidates: [],
            columns: [
                'index',
                {
                    key: 'account',
                    render: row => `<div>${RadioUi.dash(row.account)}${row.account_gone ? ' ' + RadioUi.badge('Akun dihapus', 'red') : ''}</div>` +
                        `<div class="radio-muted">${RadioUi.esc(row.email)}</div>`
                },
                {
                    key: 'display_name',
                    render: row => RadioUi.dash(row.display_name)
                },
                {
                    key: 'image',
                    render: row => esignUi.preview(row.image, row.display_name)
                },
                {
                    key: 'active',
                    className: 'text-center',
                    render: row => row.active && !row.account_gone ? RadioUi.badge('Aktif', 'green') : RadioUi.badge('Nonaktif', 'secondary')
                },
                {
                    key: 'uses',
                    render: row => `<div>${Number(row.uses) || 0} kali</div>` + (row.last_used ? `<div class="radio-muted">terakhir ${RadioUi.esc(row.last_used)}</div>` : '')
                },
                {
                    key: 'actions',
                    className: 'text-end text-nowrap',
                    render: row => `<div class="rm-acts">
                        ${esignMay.update ? `<button type="button" class="rm-act" data-action="edit" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Edit" aria-label="Edit"><i class="ti ti-edit"></i></button>` : ''}
                        <button type="button" class="rm-act" data-action="uses" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Riwayat pemakaian" aria-label="Riwayat pemakaian"><i class="ti ti-history"></i></button>
                        ${esignMay.delete ? `<button type="button" class="rm-act is-danger" data-action="delete" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Hapus" aria-label="Hapus"><i class="ti ti-trash"></i></button>` : ''}
                    </div>`
                },
            ],

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#esignTable',
                    columns: esignTable.columns,
                    currentPage: esignTable.page,
                    limit: esignTable.limit,
                    emptyText: 'Belum ada tanda tangan yang didaftarkan',
                    onPageChange: page => {
                        esignTable.page = page;
                        esignTable.load();
                    },
                    onLimitChange: limit => {
                        esignTable.limit = limit;
                        esignTable.page = 1;
                        esignTable.load();
                    },
                    ...extra,
                });
            },

            /** Only the answer to the latest request is shown. */
            load() {
                const request = ++esignTable.request;
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                esignTable.render({ loading: true });
                $.getJSON(esignUrls.list, {
                    page: esignTable.page,
                    paginated: esignTable.limit,
                    search: esignTable.search,
                }).done(response => {
                    if (request !== esignTable.request) return;
                    const page = RadioUi.pageAfter(response, esignTable.page);
                    if (page) {
                        esignTable.page = page;
                        return esignTable.load();
                    }
                    esignTable.rows = {};
                    response.data.forEach(row => {
                        esignTable.rows[row.id] = row;
                    });
                    esignTable.candidates = Array.isArray(response.candidates) ? response.candidates : [];
                    $('#esignNotice').html('');
                    esignTable.render({ data: response.data, meta: response.meta });
                    esignGrid.render(response.data);
                    RadioUi.tooltips('#esignTable');
                }).fail(xhr => {
                    if (request !== esignTable.request) return;
                    esignTable.render({ emptyText: 'Gagal memuat data.' });
                    esignGrid.render([]);
                    $('#esignNotice').html(`<div class="rm-alert danger"><i class="ti ti-alert-triangle"></i><span>${RadioUi.errors(xhr).join('<br>')}</span></div>`);
                });
            },
        };

        /** The signatures as cards (the design's Grid): the same rows as the list, and a tile to register one more. */
        const esignGrid = {
            initials(name) {
                return String(name || '?').trim().split(/\s+/).slice(0, 2).map(word => word.charAt(0).toUpperCase()).join('') || '?';
            },
            card(row) {
                const name = row.display_name || row.account || '';
                const active = row.active && !row.account_gone;
                return `<div class="rm-esign-card${active ? '' : ' is-off'}">
                    <div class="rm-esign-head">
                        <span class="rm-avatar-ini">${RadioUi.esc(esignGrid.initials(name))}</span>
                        <div class="rm-esign-who">
                            <div class="rm-esign-name">${RadioUi.dash(name)}</div>
                            <div class="rm-line2">${RadioUi.esc(row.email || '')}</div>
                        </div>
                        ${active ? RadioUi.badge('Aktif', 'green') : RadioUi.badge('Nonaktif', 'secondary')}
                    </div>
                    <div class="rm-esign-sheet">${esignUi.isPicture(row.image) ? `<img src="${row.image}" alt="${RadioUi.esc('Tanda tangan ' + name)}">` : '<span class="radio-muted">Tidak ada gambar</span>'}</div>
                    <div class="rm-esign-foot">
                        <span class="rm-line2">Diperbarui ${RadioUi.esc(row.updated_at || '-')} · ${Number(row.uses) || 0} kali dipakai</span>
                        <span class="rm-acts">
                            <button type="button" class="rm-act" data-action="uses" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Riwayat pemakaian" aria-label="Riwayat pemakaian"><i class="ti ti-history"></i></button>
                            ${esignMay.update ? `<button type="button" class="btn-rm sm secondary" data-action="edit" data-id="${Number(row.id)}">Ganti</button>` : ''}
                            ${esignMay.delete ? `<button type="button" class="btn-rm sm secondary rm-text-danger" data-action="delete" data-id="${Number(row.id)}">Hapus</button>` : ''}
                        </span>
                    </div>
                </div>`;
            },
            render(rows) {
                const cards = (rows || []).map(esignGrid.card).join('');
                const add = esignMay.create ? `<button type="button" class="rm-esign-add" onclick="esignForm.open()"><i class="ti ti-plus"></i><span>Daftarkan tanda tangan</span></button>` : '';
                $('#esignGrid').html(cards + add || '<div class="radio-muted">Belum ada tanda tangan yang didaftarkan</div>');
                RadioUi.tooltips('#esignGrid');
            },
        };

        const esignView = {
            key: 'esignView',
            /** Grid or List; the choice is kept in the browser. */
            set(view, keep = true) {
                const list = view === 'list';
                $('#esignGrid').toggleClass('d-none', list);
                $('#esignListBox').toggleClass('d-none', !list);
                $(list ? '#esignViewList' : '#esignViewGrid').prop('checked', true);
                if (!keep) return;
                try {
                    window.localStorage.setItem(esignView.key, list ? 'list' : 'grid');
                } catch (e) {
                    // No storage: the choice lasts for this page.
                }
            },
            read() {
                try {
                    return window.localStorage.getItem(esignView.key) === 'list' ? 'list' : 'grid';
                } catch (e) {
                    return 'grid';
                }
            },
        };

        const esignForm = {
            /** The signature that is being changed; null: a new one is registered. */
            id: null,

            open(row = null) {
                esignForm.id = row ? row.id : null;
                $('#esignTitle').text(row ? 'Edit tanda tangan' : 'Daftarkan tanda tangan');
                const select = $('#esign_user');
                if (row) {
                    // The owner of a signature is not changed: another account gets a signature of its own.
                    select.html(`<option value="${Number(row.user_id)}">${RadioUi.esc(row.account || '(akun dihapus)')}${row.email ? ' · ' + RadioUi.esc(row.email) : ''}</option>`);
                } else {
                    select.html('<option value="">Pilih akun…</option>' + esignTable.candidates.map(user =>
                        `<option value="${Number(user.id)}">${RadioUi.esc(user.name)} · ${RadioUi.esc(user.email)}</option>`).join(''));
                }
                select.prop('disabled', !!row);
                $('#esignUserHint').text(row ? 'Pemilik tanda tangan tidak bisa diganti. Untuk akun lain, daftarkan tanda tangan baru.' :
                    'Tanda tangan ini hanya muncul di form yang dibuka dengan akun ini.');
                $('#esign_name').val(row ? (row.display_name ?? '') : '');
                $('#esign_active').prop('checked', row ? !!row.active : true);
                $('#esignActiveWrap').toggleClass('d-none', !row);
                $('#esignCurrentWrap').toggleClass('d-none', !row);
                $('#esignCurrent').html(row && esignUi.isPicture(row.image) ? `<img src="${row.image}" alt="Tanda tangan sekarang">` : '');
                $('#esignPadLabel').text(row ? 'Tanda tangan baru (biarkan kosong bila tidak diganti)' : 'Tanda tangan');
                esignForm.error('');
                // A pad of its own for every time the dialog opens: nothing of the signature before is left in it.
                $('#esignPadFrame').remove();
                const frame = document.createElement('iframe');
                frame.id = 'esignPadFrame';
                frame.title = 'Kotak tanda tangan';
                frame.src = esignUrls.pad;
                document.getElementById('esignPadHost').appendChild(frame);
                $('#esignModal').modal('show');
            },

            error(text) {
                $('#esignError').toggleClass('d-none', text === '').text(text);
            },

            /** What the pad of the frame holds: the picture that was taken, whether the pad is still open (ink that was not taken yet). */
            pad() {
                const frame = document.getElementById('esignPadFrame');
                try {
                    const page = frame.contentDocument;
                    const overlay = page.getElementById('signOverlay');
                    const image = page.querySelector('.sig-img');

                    return { open: !!overlay && !overlay.hidden, image: image ? image.getAttribute('src') : null };
                } catch (error) {
                    return { open: false, image: null };
                }
            },

            submit() {
                const pad = esignForm.pad();
                if (pad.open) {
                    esignForm.error('Tanda tangan belum dipakai. Tekan "Pakai tanda tangan" atau "Batal" di kotaknya dulu.');
                    return;
                }
                if (!esignForm.id && !$('#esign_user').val()) {
                    esignForm.error('Pilih akun pemilik tanda tangan.');
                    return;
                }
                if (!esignForm.id && pad.image === null) {
                    esignForm.error('Tanda tangan belum ada. Klik kotaknya untuk tanda tangan di layar, atau unggah file gambarnya.');
                    return;
                }
                esignForm.error('');
                const data = { display_name: $('#esign_name').val() };
                if (pad.image !== null) data.image = pad.image;
                if (esignForm.id) {
                    data._method = 'PUT';
                    data.active = $('#esign_active').is(':checked') ? 1 : 0;
                } else {
                    data.user_id = $('#esign_user').val();
                }
                RadioUi.send('#esignSave', {
                    url: esignForm.id ? esignUrls.update.replace(':id', esignForm.id) : esignUrls.store,
                    type: 'POST',
                    dataType: 'json',
                    data,
                })?.done(response => {
                    $('#esignModal').modal('hide');
                    rmFeedback.toast(response.message);
                    esignTable.load();
                }).fail(xhr => esignForm.error(esignForm.messages(xhr).join(' ')));
            },

            /** What a refusal says, as plain text (it is written into the dialog as text). */
            messages(xhr) {
                const body = xhr.responseJSON || {};
                if (Array.isArray(body.errors)) return body.errors.map(String);
                if (body.errors && typeof body.errors === 'object') return Object.values(body.errors).flat().map(String);
                if (xhr.status === 401 || xhr.status === 419) return ['Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.'];
                if (xhr.status === 403) return ['Anda tidak memiliki izin untuk aksi ini.'];
                return [typeof body.message === 'string' && body.message ? body.message : 'Terjadi kesalahan, coba lagi.'];
            },

            remove(row) {
                rmDialog.confirm({
                    tone: 'red',
                    icon: 'trash',
                    title: 'Hapus tanda tangan ini?',
                    html: `${RadioUi.esc(row.display_name)}<br><span class="text-muted small">Tanda tangan tidak lagi muncul di form. Riwayat pemakaiannya tetap tersimpan.</span>`,
                    confirmText: 'Hapus',
                    confirmKind: 'destructive',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: esignUrls.destroy.replace(':id', row.id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE' },
                    }).done(response => {
                        rmFeedback.toast(response.message);
                        esignTable.load();
                    }).fail(RadioUi.fail);
                });
            },

            uses(row) {
                $('#esignUsesLabel').text(row.display_name || '');
                $('#esignUsesList').html('<div class="radio-muted">Memuat…</div>');
                $('#esignUsesModal').modal('show');
                $.getJSON(esignUrls.uses.replace(':id', row.id)).done(response => {
                    const lines = response.data.map(use =>
                        `<tr><td class="text-nowrap">${RadioUi.esc(use.at)}</td><td>${RadioUi.dash(use.radio)}</td><td>${RadioUi.dash(use.user)}</td></tr>`).join('');
                    $('#esignUsesList').html(response.data.length ?
                        `<div class="rm-box mb-2"><table class="rm-table compact"><thead><tr><th>Waktu</th><th>Form radio</th><th>Dibuka oleh</th></tr></thead><tbody>${lines}</tbody></table></div>` +
                        `<div class="radio-muted">${Number(response.total) || 0} kali dipakai${Number(response.total) > response.data.length ? ', ditampilkan ' + response.data.length + ' terakhir' : ''}.</div>` :
                        '<div class="radio-muted">Belum pernah dipakai di form.</div>');
                }).fail(xhr => {
                    $('#esignUsesList').html(`<div class="rm-alert danger"><i class="ti ti-alert-triangle"></i><span>${RadioUi.errors(xhr).join('<br>')}</span></div>`);
                });
            },
        };

        $(function() {
            esignTable.load();
            $('#esignSearchButton').on('click', () => {
                esignTable.search = $('#esignSearch').val().trim();
                esignTable.page = 1;
                esignTable.load();
            });
            $('#esignSearch').on('keydown', e => {
                if (e.key === 'Enter') $('#esignSearchButton').trigger('click');
            });
            // The buttons of a row of the list and of a card of the grid do the same.
            const act = function() {
                const row = esignTable.rows[Number($(this).attr('data-id'))];
                $(this).tooltip('hide');
                if (!row) return;
                const action = $(this).attr('data-action');
                if (action === 'delete') esignForm.remove(row);
                else if (action === 'uses') esignForm.uses(row);
                else esignForm.open(row);
            };
            $('#esignTable').on('click', 'button[data-action]', act);
            $('#esignGrid').on('click', 'button[data-action]', act);
            esignView.set(esignView.read(), false);
            $('input[name="esignView"]').on('change', function() {
                esignView.set($(this).val());
            });
            // The pad goes with the dialog: a signature that was drawn and not saved is not kept anywhere.
            $('#esignModal').on('hidden.bs.modal', () => $('#esignPadFrame').remove());
        });
    </script>
@endpush
