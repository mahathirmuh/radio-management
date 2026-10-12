@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'E-Sign Master')

@push('style')
    <style>
        /* A signature is dark ink on nothing: it is shown on white, whatever the theme. */
        .esign-preview {
            display: inline-block;
            width: 150px;
            height: 56px;
            border: 1px solid var(--tblr-border-color);
            border-radius: 4px;
            background: #fff;
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
            border: 1px solid var(--tblr-border-color);
            border-radius: 4px;
            background: #fff;
        }
    </style>
@endpush

@section('content')
    <div class="row g-2">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-12 d-flex justify-content-between gap-2">
                            <div class="d-flex gap-2">
                                <input type="text" id="esignSearch" class="form-control form-control-lg pb-1 rounded-3"
                                    autocomplete="off" maxlength="100" placeholder="Cari nama atau e-mail…" />
                                <x-global.btn-detail label="" color="blue" size="sm" outline="true" id="esignSearchButton"
                                    data-bs-toggle="tooltip" data-bs-title="Cari">
                                    <x-slot:icon>
                                        <i class="ti ti-search"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                            </div>
                            <x-global.btn-detail label="Daftarkan tanda tangan" color="blue" size="sm" id="esignAdd"
                                onclick="esignForm.open()">
                                <x-slot:icon>
                                    <i class="ti ti-plus"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>

                        <div class="col-12">
                            <div class="radio-muted">
                                Tanda tangan tim ICT untuk kotak <b>PROCESSED BY</b> di IT Equipment Checkout Form (tombol Cetak di Register
                                Radio). Di form, yang muncul adalah tanda tangan <b>akun yang sedang membuka form itu</b>: tidak ada yang bisa
                                menempelkan tanda tangan orang lain. Setiap pemakaian dicatat (tombol riwayat). Gambar disimpan terenkripsi.
                            </div>
                            <div id="esignNotice"></div>
                        </div>

                        <div class="col-12">
                            <div class="card overflow-hidden mdka-border-gray-200">
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-vcenter mdka-border-gray-100 mb-0" id="esignTable">
                                        <thead>
                                            <tr class="mdka-bg-gray-50">
                                                <th class="text-capitalize fs-5 py-3 text-center" width="30">No</th>
                                                <th class="text-capitalize fs-5 py-3">Akun</th>
                                                <th class="text-capitalize fs-5 py-3">Nama tercetak</th>
                                                <th class="text-capitalize fs-5 py-3">Tanda tangan</th>
                                                <th class="text-capitalize fs-5 py-3 text-center">Status</th>
                                                <th class="text-capitalize fs-5 py-3">Dipakai</th>
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

    {{-- Registers a signature, or changes one. Static: what was signed is lost when the dialog closes. --}}
    <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="esignModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content p-3">
                <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
                <div class="modal-body p-0">
                    <h3 class="modal-title mb-3" id="esignTitle">Daftarkan tanda tangan</h3>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="esign_user" class="form-label required">Akun pemilik</label>
                            <select id="esign_user" class="form-select"></select>
                            <div class="form-hint" id="esignUserHint">Tanda tangan ini hanya muncul di form yang dibuka dengan akun ini.</div>
                        </div>
                        <div class="col-md-6">
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
                        <div class="col-12 d-none" id="esignCurrentWrap">
                            <div class="form-label mb-1">Tanda tangan sekarang</div>
                            <span class="esign-preview" id="esignCurrent"></span>
                        </div>
                        <div class="col-12">
                            <div class="form-label mb-1" id="esignPadLabel">Tanda tangan</div>
                            <div id="esignPadHost"></div>
                        </div>
                        <div class="col-12" role="alert">
                            <div class="alert alert-danger py-2 small mb-0 d-none" id="esignError"></div>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true" data-bs-dismiss="modal">
                                <x-slot:icon>
                                    <i class="ti ti-x"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                            <x-global.btn-detail label="Simpan" color="blue" size="sm" id="esignSave" onclick="esignForm.submit()">
                                <x-slot:icon>
                                    <i class="ti ti-device-floppy"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Where a signature was put on a form. --}}
    <div class="modal fade" id="esignUsesModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content p-3">
                <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
                <div class="modal-body p-0">
                    <h3 class="modal-title mb-1">Riwayat pemakaian</h3>
                    <p class="radio-muted mb-2" id="esignUsesLabel"></p>
                    <div id="esignUsesList"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
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
                    className: 'text-center text-nowrap',
                    render: row => `
                        <button type="button" class="btn btn-sm px-2 py-1 rounded-2 mdka-btn-orange-500 text-white border-0" data-action="edit" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Edit"><i class="ti ti-edit"></i></button>
                        <button type="button" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-blue" data-action="uses" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Riwayat pemakaian"><i class="ti ti-history"></i></button>
                        <button type="button" class="btn btn-sm px-2 py-1 rounded-2 btn-outline-red" data-action="delete" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="Hapus"><i class="ti ti-trash"></i></button>`
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
                    RadioUi.tooltips('#esignTable');
                }).fail(xhr => {
                    if (request !== esignTable.request) return;
                    esignTable.render({ emptyText: 'Gagal memuat data.' });
                    $('#esignNotice').html(`<div class="alert alert-danger py-2 small mt-2 mb-0">${RadioUi.errors(xhr).join('<br>')}</div>`);
                });
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
                    Swal.fire('Berhasil', RadioUi.esc(response.message), 'success');
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
                Swal.fire({
                    title: 'Hapus tanda tangan ini?',
                    html: `${RadioUi.esc(row.display_name)}<br><span class="text-muted small">Tanda tangan tidak lagi muncul di form. Riwayat pemakaiannya tetap tersimpan.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-trash"></i> Hapus',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: esignUrls.destroy.replace(':id', row.id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE' },
                    }).done(response => {
                        Swal.fire('Terhapus', RadioUi.esc(response.message), 'success');
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
                        `<table class="table table-sm mb-1"><thead><tr><th>Waktu</th><th>Form radio</th><th>Dibuka oleh</th></tr></thead><tbody>${lines}</tbody></table>` +
                        `<div class="radio-muted">${Number(response.total) || 0} kali dipakai${Number(response.total) > response.data.length ? ', ditampilkan ' + response.data.length + ' terakhir' : ''}.</div>` :
                        '<div class="radio-muted">Belum pernah dipakai di form.</div>');
                }).fail(xhr => {
                    $('#esignUsesList').html(`<div class="alert alert-danger py-2 small mb-0">${RadioUi.errors(xhr).join('<br>')}</div>`);
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
            $('#esignTable').on('click', 'button[data-action]', function() {
                const row = esignTable.rows[Number($(this).attr('data-id'))];
                $(this).tooltip('hide');
                if (!row) return;
                const action = $(this).attr('data-action');
                if (action === 'delete') esignForm.remove(row);
                else if (action === 'uses') esignForm.uses(row);
                else esignForm.open(row);
            });
            // The pad goes with the dialog: a signature that was drawn and not saved is not kept anywhere.
            $('#esignModal').on('hidden.bs.modal', () => $('#esignPadFrame').remove());
        });
    </script>
@endpush
