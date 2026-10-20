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
                                        data-bs-auto-close="outside" id="radioNextIdButton" aria-expanded="false">Pilih ID <i
                                            class="ti ti-chevron-down" aria-hidden="true"></i></button>
                                    {{-- The picker (radioForm.renderPicker): whether the ID can be used, the IDs around it,
                                         the next free ones. --}}
                                    <div class="dropdown-menu dropdown-menu-end rm-idp" id="radioNextIdMenu">
                                        <span class="radio-muted">Memuat…</span>
                                    </div>
                                </div>
                                {{-- An ID that cannot be used (its MDC Code has F, another radio holds or keeps it): why,
                                     and the one to use instead. --}}
                                <div class="rm-alert danger mt-2 d-none" id="radioIdStatus" role="status">
                                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                                    <div class="flex-fill min-w-0">
                                        <div class="fw-semibold" id="radioIdStatusTitle"></div>
                                        <div id="radioIdStatusText"></div>
                                    </div>
                                    <button type="button" class="btn-rm sm primary align-self-center d-none" id="radioIdUse"
                                        data-use=""></button>
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
                idCheck: "{{ route('radio-management.radio.id-check') }}",
            },
            /** What the register said of the ID typed last (id-check), and the free IDs when none is typed. */
            idInfo: null,
            nextIds: null,
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
                $('#radioNextIdButton').on('show.bs.dropdown', radioForm.openPicker);
                // An ID chosen in the picker or under the field.
                $('#radioNextIdMenu, #radioIdStatus').on('click', '[data-use]', function(e) {
                    e.preventDefault();
                    radioForm.useId($(this).attr('data-use'));
                });
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
                radioForm.idInfo = null;
                radioForm.showIdStatus(null);

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

            /** The MDC Code beside the field (its F in red), and what the register says of the ID, a moment after typing. */
            previewId() {
                const id = String($('#dmr_id').val() ?? '');
                $('#radioMdcPreview').html(/^[1-9]\d*$/.test(id) ? `MDC ${radioForm.hexHtml(RadioUi.mdc(id))}` : '');
                radioForm.idInfo = null;
                if (!/^[1-9]\d*$/.test(id)) {
                    radioForm.showIdStatus(null);
                    return;
                }
                // An F is seen at once; who holds it, and what to use instead, once the register has answered.
                radioForm.showIdStatus(RadioUi.hexHasF(id) ? { id: Number(id), mdc: RadioUi.mdc(id), status: 'hex_f', own: id === String(radioForm.loaded.dmr_id ?? ''), suggested: null, skipped: null } : null);
                radioForm.checkId(id);
            },

            /** An MDC Code with its F in red (as text: the code is made of hex digits only). */
            hexHtml(mdc) {
                return String(mdc ?? '').split('').map(ch => ch === 'F' ? '<span class="rm-hex-f">F</span>' : RadioUi.esc(ch)).join('');
            },

            checkId: debounce(id => {
                $.getJSON(radioForm.urls.idCheck, { id, except: radioForm.id || '' }).done(({ data }) => {
                    if (String($('#dmr_id').val()) !== String(id)) return; // typed on meanwhile
                    radioForm.idInfo = data;
                    radioForm.showIdStatus(data);
                    radioForm.renderPicker();
                });
            }),

            /** What is said of an ID: a title and a sentence, in the words of the form. */
            idWords(info) {
                const id = Number(info.id);
                const holder = info.holder ? [info.holder.label, info.holder.user, info.holder.department ? `(${info.holder.department})` : ''].filter(Boolean).join(', ').replace(', (', ' (') : '';
                const next = info.suggested ? ` ID berikutnya yang bisa dipakai: ${Number(info.suggested.id)} (MDC ${info.suggested.mdc}).` : '';
                switch (info.status) {
                    case 'ok':
                        return info.own ?
                            { title: `${id} adalah ID radio ini`, text: `MDC Code ${info.mdc}.` } :
                            { title: `${id} bebas`, text: `MDC Code ${info.mdc} tanpa huruf F. ID ini dipakai radio ini saat disimpan.` };
                    case 'hex_f':
                        return info.own ?
                            { title: `Radio ini memakai ${id}: MDC ${info.mdc} mengandung F`, text: `ID seperti ini seharusnya tidak dipakai: program ulang radionya ke ID lain, lalu ganti di sini.${next}` } :
                            { title: `${id} tidak bisa dipakai: MDC ${info.mdc} mengandung F`, text: (info.skipped ? `ID ${Number(info.skipped.from)} sampai ${Number(info.skipped.to)} dilewati.` : 'ID yang MDC Code-nya mengandung F tidak dipakai.') + next };
                    case 'taken':
                        return { title: `${id} sudah dipakai`, text: `Dipakai ${holder}. Radio ID unik di antara radio aktif.${next}` };
                    case 'reserved':
                        return { title: `${id} dicadangkan`, text: `${holder} belum punya ID dan kemungkinan masih memakai ${id}. Cek radio tersebut dulu.${next}` };
                    default:
                        return { title: String(id), text: '' };
                }
            },

            /** The line under the field when the ID cannot be used: why, and a button for the ID to use instead. */
            showIdStatus(info) {
                const box = $('#radioIdStatus');
                if (!info || info.status === 'ok') {
                    box.addClass('d-none');
                    return;
                }
                const words = radioForm.idWords(info);
                $('#radioIdStatusTitle').text(words.title);
                $('#radioIdStatusText').text(words.text);
                const use = $('#radioIdUse');
                if (info.suggested) {
                    use.attr('data-use', String(Number(info.suggested.id))).text(`Pakai ${Number(info.suggested.id)}`).removeClass('d-none');
                } else {
                    use.attr('data-use', '').addClass('d-none');
                }
                box.removeClass('d-none');
            },

            /** The picker: for an ID typed, whether it can be used and the IDs around it; the next free IDs in any case. */
            renderPicker() {
                const info = radioForm.idInfo;
                const esc = RadioUi.esc;
                let html = '';
                if (info) {
                    const words = radioForm.idWords(info);
                    const ok = info.status === 'ok';
                    html += `<div class="rm-idp-status ${ok ? 'is-ok' : 'is-bad'}"><i class="ti ti-${ok ? 'circle-check' : 'alert-triangle'}" aria-hidden="true"></i>` +
                        `<div class="rm-idp-words"><div class="rm-idp-title">${esc(words.title)}</div><div>${esc(words.text)}</div></div>` +
                        (info.suggested ? `<button type="button" class="btn-rm sm primary" data-use="${Number(info.suggested.id)}">Pakai ${Number(info.suggested.id)}</button>` : '') + `</div>`;
                    const cells = (info.strip || []).map(cell => {
                        if (cell.gap) return `<span class="rm-idp-cell is-gap"><b>···</b><small>${Number(cell.gap)} lagi</small></span>`;
                        const kind = cell.state === 'hex_f' ? 'hex' : (cell.state === 'used' ? 'used' : 'free');
                        const label = `<b>${Number(cell.id)}</b><small>${esc(cell.mdc)}</small>`;
                        return kind === 'free' && !cell.selected ?
                            `<button type="button" class="rm-idp-cell is-free" data-use="${Number(cell.id)}">${label}</button>` :
                            `<span class="rm-idp-cell is-${kind}${cell.selected ? ' is-selected' : ''}">${label}</span>`;
                    }).join('');
                    html += `<div><div class="rm-idp-head"><span>ID SEKITAR</span><span class="rm-idp-legend"><i class="is-free"></i>bebas<i class="is-used"></i>dipakai<i class="is-hex"></i>ada F</span></div>` +
                        `<div class="rm-idp-strip">${cells}</div></div>`;
                }
                const next = info ? info.next : radioForm.nextIds;
                const chips = next === null ? '<span class="radio-muted">Memuat…</span>' : (next.length ?
                    next.map(item => `<button type="button" class="rm-idchip" data-use="${Number(item.id)}"><b>${Number(item.id)}</b> <span class="radio-mono">${esc(item.mdc)}</span></button>`).join('') :
                    '<span class="radio-muted">Tidak ada ID bebas lagi</span>');
                html += `<div><div class="rm-idp-head"><span>ID BEBAS BERIKUTNYA</span></div><div class="rm-idp-chips">${chips}</div></div>` +
                    `<div class="rm-idp-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>MDC Code adalah Radio ID dalam heksadesimal. Kode yang mengandung F berlaku sebagai wildcard di sistem radio, jadi ID seperti itu tidak pernah dipakai.</span></div>`;
                $('#radioNextIdMenu').html(`<div class="rm-idp-body">${html}</div>`);
            },

            /** The picker opens: on what is known of the ID typed, or on the lowest free IDs. */
            openPicker() {
                radioForm.renderPicker();
                if (radioForm.idInfo) return;
                const typed = String($('#dmr_id').val() ?? '');
                if (/^[1-9]\d*$/.test(typed)) {
                    radioForm.checkId(typed);
                    return;
                }
                radioForm.nextIds = null;
                radioForm.renderPicker();
                $.getJSON(radioForm.urls.nextId, { except: radioForm.id || '' })
                    .done(({ data }) => {
                        radioForm.nextIds = data || [];
                        radioForm.renderPicker();
                    })
                    .fail(() => $('#radioNextIdMenu').html('<span class="text-danger">Gagal memuat</span>'));
            },

            /** An ID chosen: into the field, and the picker closes. */
            useId(id) {
                if (!/^[1-9]\d*$/.test(String(id))) return;
                $('#dmr_id').val(id).trigger('input');
                $('#radioNextIdButton').dropdown('hide');
            },

            previewType() {
                const option = $('#radio_type_id').find(':selected');
                const category = option.data('category');
                $('#radioTypeHint').text(category ? `${category} · ${option.data('power') || '-'}` : '');
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
