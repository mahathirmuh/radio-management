{{-- Several radios at once (the bar: radio.bulk-bar). The radios ticked are kept while the page or the filter changes;
     "every radio of the filter" holds only as long as the filter stays the same. Verifying takes every radio of a filter,
     archiving and deleting only radios ticked one by one (RadioBulk on the server says the same). --}}
@php
    $bulkNote = \Modules\RadioManagement\Http\Controllers\RadioBulkController::DEFAULT_NOTE;
@endphp
<div class="modal fade rm-modal" data-bs-backdrop="static" id="radioBulkVerifyModal" aria-hidden="true"
    aria-labelledby="radioBulkVerifyTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head">
                <span class="rm-dialog-icon tone-green"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title" id="radioBulkVerifyTitle">{{ __('RadioManagement::register.bulk.verify_title') }}</h3>
                    <p class="rm-dialog-sub" id="radioBulkVerifyLabel"></p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="rm-dialog-body">
                <div class="rm-field">
                    <label for="bulk_verified_at" class="form-label required">{{ __('RadioManagement::register.bulk.verify_date') }}</label>
                    <input type="date" id="bulk_verified_at" class="form-control">
                </div>
                <div class="rm-field">
                    <label for="bulk_verify_note" class="form-label">{{ __('RadioManagement::register.bulk.note') }}</label>
                    <textarea id="bulk_verify_note" class="form-control" rows="3" maxlength="2000"
                        placeholder="{{ __('RadioManagement::register.bulk.verify_note_hint') }}"></textarea>
                    {{-- The note written into the history when none is typed: kept in Indonesian, as the history is. --}}
                    <div class="rm-hint">{{ __('RadioManagement::register.bulk.if_empty', ['note' => $bulkNote]) }}</div>
                </div>
                <div class="rm-alert">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                    <span>{!! __('RadioManagement::register.bulk.verify_explain') !!}</span>
                </div>
            </div>
            <div class="rm-dialog-foot">
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                <button type="button" class="btn-rm success" id="radioBulkVerifyButton" onclick="radioBulk.submitVerify()">
                    {{ __('RadioManagement::register.bulk.verify') }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade rm-modal" data-bs-backdrop="static" id="radioBulkArchiveModal" aria-hidden="true"
    aria-labelledby="radioBulkArchiveTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head">
                <span class="rm-dialog-icon tone-orange"><i class="ti ti-archive" aria-hidden="true"></i></span>
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title" id="radioBulkArchiveTitle">{{ __('RadioManagement::register.bulk.archive_title') }}</h3>
                    <p class="rm-dialog-sub" id="radioBulkArchiveLabel"></p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="rm-dialog-body">
                <div class="row g-3">
                    <div class="col-md-7 rm-field">
                        <label for="bulk_archive_reason" class="form-label required">{{ __('RadioManagement::register.bulk.reason') }}</label>
                        <select id="bulk_archive_reason" class="form-select">
                            @foreach ($archiveReasons as $reason)
                                <option value="{{ $reason }}">{{ \Modules\RadioManagement\Services\RadioWords::of('archive_reasons', $reason) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5 rm-field">
                        <label for="bulk_archived_at" class="form-label required">{{ __('RadioManagement::register.bulk.date') }}</label>
                        <input type="date" id="bulk_archived_at" class="form-control">
                    </div>
                </div>
                <div class="rm-field">
                    <label for="bulk_archive_note" class="form-label">{{ __('RadioManagement::register.bulk.note') }}</label>
                    <textarea id="bulk_archive_note" class="form-control" rows="3" maxlength="1000"
                        placeholder="{{ __('RadioManagement::register.bulk.archive_note_hint') }}"></textarea>
                </div>
                <div class="rm-alert warning">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                    <span>{{ __('RadioManagement::register.bulk.archive_explain') }}</span>
                </div>
            </div>
            <div class="rm-dialog-foot">
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                <button type="button" class="btn-rm primary" id="radioBulkArchiveButton" onclick="radioBulk.submitArchive()">
                    {{ __('RadioManagement::register.bulk.archive') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        const radioBulk = {
            /** Its words in the language of the page (RadioManagement::register.bulk). */
            words: @json(__('RadioManagement::register.bulk')),
            urls: {
                verify: "{{ route('radio-management.radio.bulk-verify') }}",
                archive: "{{ route('radio-management.radio.bulk-archive') }}",
                destroy: "{{ route('radio-management.radio.bulk-destroy') }}",
                export: "{{ route('radio-management.radio.export') }}",
            },
            max: {
                ticked: {{ \Modules\RadioManagement\Services\RadioBulk::MAX_TICKED }},
                all: {{ \Modules\RadioManagement\Services\RadioBulk::MAX_ALL }},
            },
            /** The radios ticked one by one, kept while the page or the filter changes: id => label. */
            picked: new Map(),
            /** Every radio of a filter, while that is what is chosen: { params, total }. */
            all: null,
            /** The rows on screen, and how many radios the filter shows. */
            rows: [],
            total: 0,

            count() {
                return radioBulk.all ? radioBulk.all.total : radioBulk.picked.size;
            },
            label(r) {
                return `${r.dmr_id ? 'ID ' + r.dmr_id + (r.serial_number ? ' · ' : '') : ''}${r.serial_number || ''}` || `#${r.id}`;
            },
            /** The box of a row, ticked when it is chosen (and fixed while every radio of the filter is). */
            cell(r) {
                const label = RadioUi.esc(radioBulk.label(r));
                const on = radioBulk.all !== null || radioBulk.picked.has(Number(r.id));
                return `<input type="checkbox" class="form-check-input m-0 radio-pick" aria-label="${RadioUi.esc(RadioUi.fill(radioBulk.words.pick, { radio: radioBulk.label(r) }))}" data-id="${Number(r.id)}" data-label="${label}"${on ? ' checked' : ''}${radioBulk.all ? ' disabled' : ''}>`;
            },
            /** After the list is drawn: what is on screen, and the box of the header and the bar to match. */
            shown(rows, meta) {
                radioBulk.rows = (rows || []).map(r => ({ id: Number(r.id), label: radioBulk.label(r) }));
                radioBulk.total = meta ? Number(meta.total) || 0 : 0;
                radioBulk.refresh();
            },
            pick(id, label, on) {
                id = Number(id);
                if (!id || radioBulk.all) return;
                if (on) {
                    radioBulk.picked.set(id, String(label || ''));
                } else {
                    radioBulk.picked.delete(id);
                }
                radioBulk.refresh();
            },
            pickPage(on) {
                if (radioBulk.all) {
                    if (!on) radioBulk.clear();
                    return;
                }
                radioBulk.rows.forEach(r => (on ? radioBulk.picked.set(r.id, r.label) : radioBulk.picked.delete(r.id)));
                radioTable.redraw();
                radioBulk.refresh();
            },
            chooseAll() {
                radioBulk.all = { params: radioBulk.filter(), total: radioBulk.total };
                radioBulk.picked.clear();
                radioTable.redraw();
                radioBulk.refresh();
            },
            clear() {
                radioBulk.picked.clear();
                radioBulk.all = null;
                radioTable.redraw();
                radioBulk.refresh();
            },
            /** The filter of the list without its page and order: what "every radio of the filter" is. */
            filter() {
                const params = radioTable.params();
                ['page', 'paginated', 'orderBy', 'orderDir'].forEach(key => delete params[key]);
                return params;
            },
            /** Before the list loads: another filter is other radios, so "every radio of the filter" no longer holds. */
            loading() {
                if (radioBulk.all && JSON.stringify(radioBulk.filter()) !== JSON.stringify(radioBulk.all.params)) {
                    radioBulk.all = null;
                    radioBulk.refresh();
                }
            },
            refresh() {
                const count = radioBulk.count();
                $('#radioBulkBar').toggleClass('d-none', count === 0);
                const words = radioBulk.words;
                $('#radioBulkCount').text(radioBulk.all ? RadioUi.fill(words.chosen_all, { count: radioBulk.all.total }) : RadioUi.choice(words.chosen, count));
                const onPage = radioBulk.rows.filter(r => radioBulk.picked.has(r.id)).length;
                const whole = radioBulk.rows.length > 0 && (radioBulk.all !== null || onPage === radioBulk.rows.length);
                $('#radioPickPage').prop('checked', whole).prop('indeterminate', !whole && onPage > 0);
                // Every radio of the filter is offered once the whole page is ticked and the filter has more.
                const offer = !radioBulk.all && whole && radioBulk.total > radioBulk.rows.length;
                $('#radioBulkAll').toggleClass('d-none', !offer).text(RadioUi.fill(words.choose_all, { count: radioBulk.total }));
                // Archiving and deleting: radios ticked one by one, up to the limit.
                const ticked = !radioBulk.all && count <= radioBulk.max.ticked;
                const why = radioBulk.all ? words.one_by_one :
                    (ticked ? '' : RadioUi.fill(words.at_most, { max: radioBulk.max.ticked }));
                $('#radioBulkArchive').prop('disabled', !ticked).attr('title', why);
                $('#radioBulkDelete').prop('disabled', !ticked).attr('title', why);
                const fits = count <= radioBulk.max.all;
                $('#radioBulkVerify').prop('disabled', !fits).attr('title', fits ? '' : RadioUi.fill(words.at_most, { max: radioBulk.max.all }));
            },
            /** What an action sends: the ids ticked, or the filter with all=1. */
            payload() {
                return radioBulk.all ? { all: 1, ...radioBulk.all.params } : { ids: [...radioBulk.picked.keys()] };
            },
            chosenText() {
                return radioBulk.all ? RadioUi.fill(radioBulk.words.chosen_all_text, { count: radioBulk.all.total }) : RadioUi.choice(radioBulk.words.chosen_text, radioBulk.count());
            },

            verify() {
                $('#radioBulkVerifyTitle').text(RadioUi.choice(radioBulk.words.verify_question, radioBulk.count()));
                $('#radioBulkVerifyLabel').text(radioBulk.chosenText());
                $('#bulk_verified_at').val(RadioUi.today()).attr('max', RadioUi.today());
                $('#bulk_verify_note').val('');
                $('#radioBulkVerifyModal').modal('show');
            },
            submitVerify() {
                RadioUi.send('#radioBulkVerifyButton', {
                    url: radioBulk.urls.verify,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'PUT',
                        ...radioBulk.payload(),
                        event_date: $('#bulk_verified_at').val(),
                        description: $('#bulk_verify_note').val(),
                    },
                })?.done(response => {
                    $('#radioBulkVerifyModal').modal('hide');
                    radioBulk.finished(response);
                }).fail(RadioUi.fail);
            },

            archive() {
                $('#radioBulkArchiveTitle').text(RadioUi.choice(radioBulk.words.archive_question, radioBulk.count()));
                $('#radioBulkArchiveLabel').text(radioBulk.chosenText());
                $('#bulk_archive_reason').prop('selectedIndex', 0);
                $('#bulk_archived_at').val(RadioUi.today()).attr('max', RadioUi.today());
                $('#bulk_archive_note').val('');
                $('#radioBulkArchiveModal').modal('show');
            },
            submitArchive() {
                RadioUi.send('#radioBulkArchiveButton', {
                    url: radioBulk.urls.archive,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'PUT',
                        ...radioBulk.payload(),
                        archive_reason: $('#bulk_archive_reason').val(),
                        archived_at: $('#bulk_archived_at').val(),
                        note: $('#bulk_archive_note').val(),
                    },
                })?.done(response => {
                    $('#radioBulkArchiveModal').modal('hide');
                    radioBulk.finished(response);
                }).fail(RadioUi.fail);
            },

            /** Deleting is for radios entered by mistake: the person types how many, as a last look. */
            destroy() {
                const count = radioBulk.count();
                const words = radioBulk.words;
                // Red: there is no way back from the page.
                rmDialog.confirm({
                    tone: 'red',
                    icon: 'trash',
                    title: RadioUi.choice(words.delete_question, count),
                    html: RadioUi.fill(words.delete_explain, { count: Number(count) }),
                    input: 'text',
                    inputAttributes: { autocomplete: 'off', inputmode: 'numeric' },
                    confirmText: words.delete,
                    confirmKind: 'destructive',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    if (String(result.value || '').trim() !== String(count)) {
                        Swal.fire(words.not_deleted, RadioUi.fill(words.typed_other, { count }), 'info');
                        return;
                    }
                    $.ajax({
                        url: radioBulk.urls.destroy,
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE', ...radioBulk.payload(), confirm: count },
                    }).done(radioBulk.finished).fail(RadioUi.fail);
                });
            },

            /** The box in the header ticks the whole page; it is drawn again with the header (radioTable.head). */
            bindPage() {
                $('#radioPickPage').off('change').on('change', function() {
                    radioBulk.pickPage($(this).is(':checked'));
                });
            },

            /** The Excel of the list, of the radios ticked (by their ids) or of every radio of the filter. */
            exportSelected() {
                const params = radioBulk.all ? { ...radioBulk.all.params } : { ids: [...radioBulk.picked.keys()] };
                window.location = radioBulk.urls.export + '?' + $.param({ ...params, orderBy: radioTable.orderBy, orderDir: radioTable.orderDir });
            },

            /** What was done, and to which radios not and why (as text); then a fresh list without a choice. */
            finished(response) {
                const skipped = Array.isArray(response.skipped) ? response.skipped : [];
                const items = skipped.slice(0, 10).map(s => `<li>${RadioUi.esc(s.radio)}: ${RadioUi.esc(s.reason)}</li>`).join('');
                const more = skipped.length > 10 ? `<div class="small">${RadioUi.esc(RadioUi.fill(RadioUi.words.more, { count: skipped.length - 10 }))}</div>` : '';
                Swal.fire({
                    title: skipped.length ? radioBulk.words.partly_skipped : RadioUi.words.success,
                    html: RadioUi.esc(response.message) + (items ? `<ul class="text-start small mt-2 mb-0">${items}</ul>` : '') + more,
                    icon: skipped.length ? 'warning' : 'success',
                });
                radioBulk.picked.clear();
                radioBulk.all = null;
                radioBulk.refresh();
                radioTable.load();
            },
        };

        $(function() {
            $('#radioTable').on('change', 'input.radio-pick', function() {
                radioBulk.pick($(this).data('id'), $(this).data('label'), $(this).is(':checked'));
            });
            radioBulk.bindPage();
            $('#radioBulkAll').on('click', e => {
                e.preventDefault();
                radioBulk.chooseAll();
            });
            $('#radioBulkClear').on('click', e => {
                e.preventDefault();
                radioBulk.clear();
            });
            $('#radioBulkVerify').on('click', () => radioBulk.verify());
            $('#radioBulkArchive').on('click', () => radioBulk.archive());
            $('#radioBulkDelete').on('click', () => radioBulk.destroy());
            $('#radioBulkExport').on('click', () => radioBulk.exportSelected());
        });
    </script>
@endpush
