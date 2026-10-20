{{-- Add / edit radio. Used by the register list and the detail page. A dialog of the design layer, 760 wide; it stays
     open on a click outside it, since what was typed would be lost (K11 in docs/PLAN.md). --}}
<div class="modal fade rm-modal wide" data-bs-backdrop="static" data-bs-keyboard="false" id="radioModal" aria-hidden="true"
    aria-labelledby="radioModalTitle">
    <div class="modal-dialog modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="rm-dialog-head is-ruled">
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title modal-title" id="radioModalTitle">Tambah radio</h3>
                    <p class="rm-dialog-sub">Kolom bertanda * wajib diisi. Radio ID dan SN dicek duplikatnya saat disimpan.</p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="rm-dialog-body is-form">
                <div class="rm-alert danger d-none" id="radioReviewBox">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                    <div>
                        <b>Perlu dicek (hasil impor Excel)</b>
                        <div class="radio-pre" id="radioReviewNotes"></div>
                        <label class="form-check mt-2 mb-0">
                            <input class="form-check-input" type="checkbox" id="radioReviewDone" />
                            <span class="form-check-label">Sudah dicek dan dibereskan, hapus tanda "Perlu dicek"</span>
                        </label>
                    </div>
                </div>

                <form id="radioForm" autocomplete="off" class="d-flex flex-column gap-4">
                    <section>
                        <div class="rm-dialog-section-title">Identitas unit</div>
                        <div class="row g-3">
                            <div class="col-md-6 rm-field">
                                <label for="dmr_id" class="form-label">Radio ID</label>
                                <div class="input-group rm-input-group">
                                    <input type="number" min="1" id="dmr_id" name="dmr_id" class="form-control"
                                        placeholder="Kosongkan bila belum diprogram">
                                    <span class="input-group-text rm-suffix radio-mono" id="radioMdcPreview"></span>
                                    <button class="btn-rm secondary rm-input-btn" type="button" data-bs-toggle="dropdown"
                                        id="radioNextIdButton" aria-expanded="false">ID bebas <i class="ti ti-chevron-down"
                                            aria-hidden="true"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end rm-menu rm-id-menu" id="radioNextIdMenu">
                                        <span class="dropdown-item-text radio-muted">Memuat…</span>
                                    </div>
                                </div>
                                {{-- An ID whose MDC Code (hexadecimal) contains F cannot be used: say so and offer replacements. --}}
                                <div class="rm-alert danger mt-2 d-none" id="radioHexWarning">
                                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                                    <div>
                                        <span id="radioHexWarningText"></span>
                                        <span class="d-flex flex-wrap gap-2 mt-2" id="radioHexSuggestions"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 rm-field">
                                <label for="serial_number" class="form-label required">Serial number (SN)</label>
                                <input type="text" id="serial_number" name="serial_number"
                                    class="form-control text-uppercase radio-mono" maxlength="50" placeholder="867TZB5309">
                                <div class="rm-hint">Format: 3 angka, 3 huruf, lalu 4 huruf atau angka</div>
                            </div>
                            <div class="col-md-6 rm-field">
                                <label for="radio_type_id" class="form-label required">Tipe radio</label>
                                <select id="radio_type_id" name="radio_type_id" class="form-select">
                                    <option></option>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->id }}" data-category="{{ $type->category_label }}"
                                            data-power="{{ $type->power }}" @if (!$type->is_active) data-inactive @endif>
                                            {{ $type->name }}{{ $type->is_active ? '' : ' (nonaktif)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 rm-field">
                                <span class="form-label">Kategori · daya</span>
                                <div class="rm-readonly" id="radioTypeHint"></div>
                                <div class="rm-hint">Dari tipe radio</div>
                            </div>
                            <div class="col-md-6 rm-field">
                                <label for="owner_company_id" class="form-label">Pemilik Aset</label>
                                <select id="owner_company_id" name="owner_company_id" class="form-select">
                                    <option></option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}" @if (!$company->is_active) data-inactive @endif>{{ $company->code }}{{ $company->is_active ? '' : ' (nonaktif)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="rm-dialog-section-title">Pengguna</div>
                        <div class="row g-3">
                            <div class="col-md-6 rm-field">
                                <label for="user_name" class="form-label">Nama User / Unit</label>
                                <input type="text" id="user_name" name="user_name" class="form-control"
                                    maxlength="150" placeholder="Nama karyawan, atau unit seperti LV 51 / POS LABOTA">
                            </div>
                            <div class="col-md-6 rm-field">
                                <label for="department_id" class="form-label">Department</label>
                                <select id="department_id" name="department_id" class="form-select">
                                    <option></option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @if (!$department->is_active) data-inactive @endif>{{ $department->name }}{{ $department->is_active ? '' : ' (nonaktif)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 rm-field">
                                <label for="company_id" class="form-label">Company</label>
                                <select id="company_id" name="company_id" class="form-select">
                                    <option></option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}" @if (!$company->is_active) data-inactive @endif>{{ $company->code }}{{ $company->is_active ? '' : ' (nonaktif)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 rm-field">
                                <label for="document_no" class="form-label">No. Dokumen (PO / BA)</label>
                                <input type="text" id="document_no" name="document_no" class="form-control radio-mono"
                                    maxlength="100" placeholder="PO 14938 / BA2300012">
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="rm-dialog-section-title">Status</div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-7 rm-field">
                                <span class="form-label required" id="placementLabel">Posisi</span>
                                <div class="rm-seg rm-seg-full" role="radiogroup" aria-labelledby="placementLabel">
                                    @foreach ($placements as $i => $placement)
                                        <input type="radio" class="btn-check" name="placement_pick" id="placementPick{{ $i }}"
                                            value="{{ $placement['value'] }}">
                                        <label for="placementPick{{ $i }}">{{ $placement['label'] }}</label>
                                    @endforeach
                                </div>
                                {{-- The value sent (the segmented control above writes it). --}}
                                <select id="placement" name="placement" class="d-none" tabindex="-1" aria-hidden="true">
                                    @foreach ($placements as $placement)
                                        <option value="{{ $placement['value'] }}">{{ $placement['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 rm-field">
                                <label for="condition" class="form-label required">Kondisi</label>
                                <select id="condition" name="condition" class="form-select">
                                    @foreach ($conditions as $condition)
                                        <option value="{{ $condition['value'] }}">{{ $condition['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="rm-dialog-section-title">Program dan verifikasi</div>
                        <div class="row g-3">
                            <div class="col-md-4 rm-field">
                                <label for="channel_template_id" class="form-label">Template channel</label>
                                <select id="channel_template_id" name="channel_template_id" class="form-select">
                                    <option></option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @if (!$template->is_active) data-inactive @endif>{{ $template->name }}{{ $template->is_active ? '' : ' (nonaktif)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 rm-field">
                                <label for="last_programmed_at" class="form-label">Program terakhir</label>
                                <input type="date" id="last_programmed_at" name="last_programmed_at" class="form-control">
                            </div>
                            <div class="col-md-4 rm-field">
                                <label for="last_verified_at" class="form-label">Verifikasi terakhir</label>
                                <input type="date" id="last_verified_at" name="last_verified_at" class="form-control">
                            </div>
                        </div>
                    </section>

                    <section class="rm-field">
                        <label for="notes" class="form-label">Keterangan</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" maxlength="5000"></textarea>
                    </section>
                </form>
            </div>
            <div class="rm-dialog-foot is-gray">
                <span class="rm-dialog-note" id="radioFormNote"></span>
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn-rm primary" id="radioSaveButton" onclick="radioForm.submit()">Simpan radio</button>
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
                // The segmented control of "Posisi" writes the value that is sent.
                $('input[name="placement_pick"]').on('change', function() {
                    $('#placement').val($(this).val());
                });
            },

            /** The segmented control shows the value of "Posisi". */
            showPlacement() {
                $(`input[name="placement_pick"][value="${$('#placement').val()}"]`).prop('checked', true);
            },

            open(mode, id = null) {
                radioForm.id = mode === 'edit' ? id : null;
                radioForm.loaded = {};
                const modal = $('#radioModal');
                modal.find('.modal-title').text(mode === 'edit' ? 'Edit radio' : 'Tambah radio');
                $('#radioSaveButton').text(mode === 'edit' ? 'Simpan perubahan' : 'Simpan radio');
                $('#radioFormNote').text('');
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
                    radioForm.showPlacement();
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
                        if (data.updated_at) $('#radioFormNote').text(`Terakhir diubah ${data.updated_at}`);
                        radioForm.showPlacement();
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
                        `<a href="#" class="rm-idchip" data-id="${Number(item.id)}"><b>${Number(item.id)}</b> <span class="radio-mono">MDC ${RadioUi.esc(item.mdc)}</span></a>`
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
                            `<a class="dropdown-item rm-menu-item" href="#" data-id="${Number(item.id)}"><b>${Number(item.id)}</b> <span class="radio-mono radio-muted ms-auto">MDC ${RadioUi.esc(item.mdc)}</span></a>`
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

                // Saved at once (the button is busy meanwhile), and said in a toast.
                RadioUi.send('#radioSaveButton', {
                    url: editing ? radioForm.urls.update.replace(':id', radioForm.id) : radioForm.urls.store,
                    type: 'POST',
                    data: payload,
                    dataType: 'json',
                })?.done(response => {
                    $('#radioModal').modal('hide');
                    rmFeedback.toast(response.message || 'Data radio disimpan.');
                    radioForm.onSaved(response);
                }).fail(RadioUi.fail);
            },
        };

        $(radioForm.init);
    </script>
@endpush
