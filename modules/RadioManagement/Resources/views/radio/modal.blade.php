{{-- Add / edit radio. Used by the register list and the detail page. --}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="radioModal" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
            {{-- overflow-x: the row gutters reach past the unpadded body, which a scrollable modal
                 would otherwise answer with a horizontal scrollbar. --}}
            <div class="modal-body p-0" style="overflow-x: hidden">
                <h3 class="modal-title mb-2">Tambah Radio</h3>

                <div class="alert alert-danger py-2 d-none" id="radioReviewBox">
                    <div class="fw-bold mb-1"><i class="ti ti-alert-triangle me-1"></i>Perlu dicek (hasil impor Excel)</div>
                    <div class="radio-pre small" id="radioReviewNotes"></div>
                    <label class="form-check mt-2 mb-0">
                        <input class="form-check-input" type="checkbox" id="radioReviewDone" />
                        <span class="form-check-label">Sudah dicek dan dibereskan, hapus tanda "Perlu dicek"</span>
                    </label>
                </div>

                <form id="radioForm" autocomplete="off">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="radio-section-title">Identitas Unit</div>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label for="dmr_id" class="form-label">Radio ID
                                        <span class="radio-muted ms-1" id="radioMdcPreview"></span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" min="1" id="dmr_id" name="dmr_id" class="form-control"
                                            placeholder="Kosongkan bila belum diprogram">
                                        <button class="btn btn-outline-blue dropdown-toggle px-2" type="button"
                                            data-bs-toggle="dropdown" id="radioNextIdButton"
                                            style="font-size: .7rem">ID bebas</button>
                                        <div class="dropdown-menu dropdown-menu-end" id="radioNextIdMenu">
                                            <span class="dropdown-item-text radio-muted">Memuat…</span>
                                        </div>
                                    </div>
                                    {{-- An ID whose MDC Code (hexadecimal) contains F cannot be used: say so and offer replacements. --}}
                                    <div class="form-hint text-danger d-none" id="radioHexWarning">
                                        <span id="radioHexWarningText"></span>
                                        <span class="d-block mt-1" id="radioHexSuggestions"></span>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label for="serial_number" class="form-label required">SN</label>
                                    <input type="text" id="serial_number" name="serial_number"
                                        class="form-control text-uppercase" maxlength="50" placeholder="867TZB5309">
                                </div>
                                <div class="col-md-7">
                                    <label for="radio_type_id" class="form-label required">Tipe Radio</label>
                                    <select id="radio_type_id" name="radio_type_id" class="form-select">
                                        <option></option>
                                        @foreach ($types as $type)
                                            <option value="{{ $type->id }}" data-category="{{ $type->category_label }}"
                                                data-power="{{ $type->power }}" @if (!$type->is_active) data-inactive @endif>
                                                {{ $type->name }}{{ $type->is_active ? '' : ' (nonaktif)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-hint" id="radioTypeHint"></div>
                                </div>
                                <div class="col-md-5">
                                    <label for="owner_company_id" class="form-label">Pemilik Aset</label>
                                    <select id="owner_company_id" name="owner_company_id" class="form-select">
                                        <option></option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @if (!$company->is_active) data-inactive @endif>{{ $company->code }}{{ $company->is_active ? '' : ' (nonaktif)' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="radio-section-title mt-3">Pengguna</div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label for="user_name" class="form-label">Nama User / Unit</label>
                                    <input type="text" id="user_name" name="user_name" class="form-control"
                                        maxlength="150" placeholder="Nama karyawan, atau unit seperti LV 51 / POS LABOTA">
                                </div>
                                <div class="col-md-7">
                                    <label for="department_id" class="form-label">Department</label>
                                    <select id="department_id" name="department_id" class="form-select">
                                        <option></option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @if (!$department->is_active) data-inactive @endif>{{ $department->name }}{{ $department->is_active ? '' : ' (nonaktif)' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="company_id" class="form-label">Company</label>
                                    <select id="company_id" name="company_id" class="form-select">
                                        <option></option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @if (!$company->is_active) data-inactive @endif>{{ $company->code }}{{ $company->is_active ? '' : ' (nonaktif)' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="radio-section-title">Program & Status</div>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label for="channel_template_id" class="form-label">Template Channel</label>
                                    <select id="channel_template_id" name="channel_template_id" class="form-select">
                                        <option></option>
                                        @foreach ($templates as $template)
                                            <option value="{{ $template->id }}" @if (!$template->is_active) data-inactive @endif>{{ $template->name }}{{ $template->is_active ? '' : ' (nonaktif)' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="last_programmed_at" class="form-label">Tgl Program Terakhir</label>
                                    <input type="date" id="last_programmed_at" name="last_programmed_at" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label for="placement" class="form-label required">Posisi</label>
                                    <select id="placement" name="placement" class="form-select">
                                        @foreach ($placements as $placement)
                                            <option value="{{ $placement['value'] }}">{{ $placement['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="condition" class="form-label required">Kondisi</label>
                                    <select id="condition" name="condition" class="form-select">
                                        @foreach ($conditions as $condition)
                                            <option value="{{ $condition['value'] }}">{{ $condition['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="last_verified_at" class="form-label">Tgl Verifikasi</label>
                                    <input type="date" id="last_verified_at" name="last_verified_at" class="form-control">
                                </div>
                            </div>

                            <div class="radio-section-title mt-3">Lain-lain</div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label for="document_no" class="form-label">No. Dokumen (PO / BA)</label>
                                    <input type="text" id="document_no" name="document_no" class="form-control"
                                        maxlength="100" placeholder="PO 14938 / BA2300012">
                                </div>
                                <div class="col-12">
                                    <label for="notes" class="form-label">Keterangan</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="4" maxlength="5000"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true"
                                data-bs-dismiss="modal">
                                <x-slot:icon>
                                    <i class="ti ti-x"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                            <x-global.btn-detail label="Simpan" color="blue" size="sm" id="radioSaveButton"
                                onclick="radioForm.submit()">
                                <x-slot:icon>
                                    <i class="ti ti-device-floppy"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        const radioForm = {
            urls: {
                show: "{{ route('radio-management.radio.show', ':id') }}",
                store: "{{ route('radio-management.radio.store') }}",
                update: "{{ route('radio-management.radio.update', ':id') }}",
                nextId: "{{ route('radio-management.radio.next-id') }}",
            },
            fields: ['dmr_id', 'serial_number', 'radio_type_id', 'owner_company_id', 'user_name', 'department_id',
                'company_id', 'channel_template_id', 'last_programmed_at', 'placement', 'condition',
                'last_verified_at', 'document_no', 'notes'
            ],
            selects: ['radio_type_id', 'owner_company_id', 'department_id', 'company_id', 'channel_template_id'],
            id: null,
            // The radio as it was when the form opened ({} for a new one): values that are not
            // being changed are not re-checked, and `version` lets the server refuse a stale save.
            loaded: {},
            onSaved: () => {},

            init() {
                const modal = $('#radioModal');
                radioForm.selects.forEach(name => {
                    $('#' + name).select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Pilih…',
                        allowClear: true,
                        dropdownParent: modal,
                        width: '100%',
                        // Master data switched to "nonaktif" is no longer offered, except the
                        // value this radio already has.
                        matcher: (params, data) => {
                            const inactive = data.element && data.element.hasAttribute('data-inactive');
                            if (inactive && String(data.id) !== String(radioForm.loaded[name] ?? '')) return null;
                            return $.fn.select2.defaults.defaults.matcher(params, data);
                        },
                    });
                });
                $('#last_programmed_at, #last_verified_at').attr('max', RadioUi.today());
                $('#dmr_id').on('input', radioForm.previewId);
                $('#radio_type_id').on('change', radioForm.previewType);
                $('#radioNextIdButton').on('show.bs.dropdown', radioForm.loadNextIds);
            },

            open(mode, id = null) {
                radioForm.id = mode === 'edit' ? id : null;
                radioForm.loaded = {};
                const modal = $('#radioModal');
                modal.find('.modal-title').text(mode === 'edit' ? 'Edit Radio' : 'Tambah Radio');
                $('#radioForm')[0].reset();
                radioForm.selects.forEach(name => $('#' + name).val(null).trigger('change'));
                $('#radioReviewBox').addClass('d-none');
                $('#radioReviewDone').prop('checked', false);
                $('#serial_number').prev('label').toggleClass('required', mode !== 'edit');
                $('#radio_type_id').closest('div').find('label').toggleClass('required', mode !== 'edit');
                $('#dmr_id').prop('disabled', false).attr('placeholder', 'Kosongkan bila belum diprogram');
                $('#radioHexWarning').addClass('d-none');

                if (mode !== 'edit') {
                    $('#placement').val('di_user');
                    $('#condition').val('layak');
                    radioForm.previewId();
                    radioForm.previewType();
                    modal.modal('show');
                    return;
                }

                $.getJSON(radioForm.urls.show.replace(':id', id))
                    .done(({ data }) => {
                        radioForm.loaded = data;
                        radioForm.fields.forEach(name => {
                            const input = $('#' + name);
                            input.val(data[name] ?? '');
                            if (radioForm.selects.includes(name)) input.trigger('change');
                        });
                        if (data.archived) {
                            $('#dmr_id').prop('disabled', true).attr('placeholder', 'Radio diarsipkan, tidak memegang ID');
                        }
                        if (data.needs_review) {
                            $('#radioReviewNotes').text(data.review_notes || '');
                            $('#radioReviewBox').removeClass('d-none');
                        }
                        radioForm.previewId();
                        radioForm.previewType();
                        modal.modal('show');
                    })
                    .fail(RadioUi.fail);
            },

            previewId() {
                const id = $('#dmr_id').val();
                $('#radioMdcPreview').text(id ? `MDC ${RadioUi.mdc(id)}` : '');

                const blocked = RadioUi.hexHasF(id);
                $('#radioHexWarning').toggleClass('d-none', !blocked);
                if (!blocked) return;

                // The radio's own imported ID may contain F: it can still be saved, but should be replaced.
                const own = String(id) === String(radioForm.loaded.dmr_id ?? '');
                $('#radioHexWarningText').text(own ?
                    `Radio ini masih memakai Radio ID ${id}, yang MDC Code-nya (${RadioUi.mdc(id)}) mengandung huruf F. ID seperti ini seharusnya tidak dipakai: program ulang radionya ke ID lain, lalu ganti di sini.` :
                    `Radio ID ${id} tidak bisa dipakai: MDC Code-nya (${RadioUi.mdc(id)}) mengandung huruf F. Ganti dengan ID lain.`);
                $('#radioHexSuggestions').empty();
                radioForm.suggestIds(id);
            },

            /** Free IDs just above the one that cannot be used, as one-click replacements. */
            suggestIds: debounce(id => {
                $.getJSON(radioForm.urls.nextId, { except: radioForm.id, after: id }).done(({ data }) => {
                    if (String($('#dmr_id').val()) !== String(id) || !data.length) return; // typed on meanwhile
                    const box = $('#radioHexSuggestions').html('<span class="radio-muted">ID bebas terdekat:</span> ' + data.map(item =>
                        `<a href="#" class="badge bg-blue-lt radio-badge ms-1" data-id="${item.id}">${item.id} · MDC ${RadioUi.esc(item.mdc)}</a>`
                    ).join(''));
                    box.find('a').on('click', function(e) {
                        e.preventDefault();
                        $('#dmr_id').val($(this).data('id')).trigger('input');
                    });
                });
            }),

            previewType() {
                const option = $('#radio_type_id').find(':selected');
                const category = option.data('category');
                $('#radioTypeHint').text(category ? `${category} · ${option.data('power') || '-'}` : '');
            },

            loadNextIds() {
                const menu = $('#radioNextIdMenu').html('<span class="dropdown-item-text radio-muted">Memuat…</span>');
                $.getJSON(radioForm.urls.nextId, { except: radioForm.id })
                    .done(({ data }) => {
                        menu.html(data.map(item =>
                            `<a class="dropdown-item" href="#" data-id="${item.id}">${item.id} <span class="radio-muted ms-2">MDC ${RadioUi.esc(item.mdc)}</span></a>`
                        ).join('') + '<div class="dropdown-divider"></div><span class="dropdown-item-text radio-muted">ID yang MDC Code-nya mengandung huruf F dilewati</span>');
                        menu.find('a').on('click', function(e) {
                            e.preventDefault();
                            $('#dmr_id').val($(this).data('id')).trigger('input');
                        });
                    })
                    .fail(() => menu.html('<span class="dropdown-item-text text-danger">Gagal memuat</span>'));
            },

            submit() {
                const payload = {};
                radioForm.fields.forEach(name => {
                    const input = $('#' + name);
                    if (input.prop('disabled')) return;
                    payload[name] = input.val() === null ? '' : String(input.val()).trim();
                });
                if (!$('#radioReviewBox').hasClass('d-none')) {
                    payload.review_done = $('#radioReviewDone').is(':checked') ? 1 : 0;
                }

                const errors = [];
                if (!radioForm.id && !payload.serial_number) errors.push('<strong>SN</strong> wajib diisi');
                if (!radioForm.id && !payload.radio_type_id) errors.push('<strong>Tipe Radio</strong> wajib dipilih');
                if (payload.placement === 'di_user' && !payload.user_name) errors.push('<strong>Nama User / Unit</strong> wajib diisi bila Posisi "Di User"');
                // An imported radio may already carry an ID with F: only a newly typed one is refused.
                const idChanged = String(payload.dmr_id ?? '') !== String(radioForm.loaded.dmr_id ?? '');
                if (payload.dmr_id && idChanged && RadioUi.hexHasF(payload.dmr_id)) {
                    errors.push(`<strong>Radio ID ${RadioUi.esc(payload.dmr_id)}</strong> tidak bisa dipakai: MDC Code-nya (${RadioUi.mdc(payload.dmr_id)}) mengandung huruf F. Ganti dengan ID lain`);
                }
                if (errors.length) {
                    showError(errors);
                    return;
                }

                const editing = !!radioForm.id;
                if (editing) {
                    payload._method = 'PUT';
                    payload.version = radioForm.loaded.version ?? '';
                }

                Swal.fire({
                    title: 'Simpan data radio?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-check"></i> Simpan',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    RadioUi.send('#radioSaveButton', {
                        url: editing ? radioForm.urls.update.replace(':id', radioForm.id) : radioForm.urls.store,
                        type: 'POST',
                        data: payload,
                        dataType: 'json',
                    })?.done(response => {
                        $('#radioModal').modal('hide');
                        Swal.fire('Berhasil', response.message, 'success');
                        radioForm.onSaved(response);
                    }).fail(RadioUi.fail);
                });
            },
        };

        $(radioForm.init);
    </script>
@endpush
