{{-- Archive (take out of service) / restore. Used by the register list and the detail page. --}}
@php
    // What each reason of config('radio.archive_reasons') means, under its name (RadioManagement::common.archive_hints).
    $archiveHints = \Modules\RadioManagement\Services\RadioWords::all('archive_hints');
@endphp
<div class="modal fade rm-modal" data-bs-backdrop="static" id="radioArchiveModal" aria-hidden="true"
    aria-labelledby="radioArchiveTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head">
                <span class="rm-dialog-icon tone-orange"><i class="ti ti-archive" aria-hidden="true"></i></span>
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title" id="radioArchiveTitle">{{ __('RadioManagement::register.archive.title') }}</h3>
                    <p class="rm-dialog-sub"><span id="radioArchiveLabel"></span>. {{ __('RadioManagement::register.archive.sub') }}</p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="rm-dialog-body">
                <div class="rm-field">
                    <span class="form-label required">{{ __('RadioManagement::register.archive.reason') }}</span>
                    <div class="rm-choices" role="radiogroup" aria-label="{{ __('RadioManagement::register.archive.reason') }}">
                        @foreach ($archiveReasons as $i => $reason)
                            <label class="rm-choice">
                                <input type="radio" name="archive_reason" value="{{ $reason }}"
                                    id="archiveReason{{ $i }}" @checked($i === 0)>
                                <span>
                                    <span class="rm-choice-label">{{ \Modules\RadioManagement\Services\RadioWords::of('archive_reasons', $reason) }}</span>
                                    @if (isset($archiveHints[$reason]))
                                        <span class="rm-choice-hint">{{ $archiveHints[$reason] }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="rm-field">
                    <label for="archived_at" class="form-label required">{{ __('RadioManagement::register.archive.date') }}</label>
                    <input type="date" id="archived_at" class="form-control">
                </div>
                <div class="rm-field">
                    <label for="archive_note" class="form-label">{{ __('RadioManagement::register.archive.note') }}</label>
                    <textarea id="archive_note" class="form-control" rows="3" maxlength="1000"
                        placeholder="{{ __('RadioManagement::register.archive.note_hint') }}"></textarea>
                </div>
                <div class="rm-note">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                    <span>{{ __('RadioManagement::register.archive.explain') }}</span>
                </div>
            </div>
            <div class="rm-dialog-foot">
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                <button type="button" class="btn-rm primary" id="radioArchiveButton" onclick="radioArchive.submit()">
                    {{ __('RadioManagement::register.archive.submit') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        const radioArchive = {
            /** Its words in the language of the page (RadioManagement::register.archive). */
            words: @json(__('RadioManagement::register.archive')),
            urls: {
                archive: "{{ route('radio-management.radio.archive', ':id') }}",
                restore: "{{ route('radio-management.radio.restore', ':id') }}",
            },
            id: null,
            onDone: () => {},

            open(id, label) {
                radioArchive.id = id;
                $('#radioArchiveLabel').text(label || '');
                $('#archiveReason0').prop('checked', true);
                $('#archived_at').val(RadioUi.today()).attr('max', RadioUi.today());
                $('#archive_note').val('');
                $('#radioArchiveModal').modal('show');
            },

            submit() {
                RadioUi.send('#radioArchiveButton', {
                    url: radioArchive.urls.archive.replace(':id', radioArchive.id),
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'PUT',
                        archive_reason: $('input[name="archive_reason"]:checked').val(),
                        archived_at: $('#archived_at').val(),
                        note: $('#archive_note').val(),
                    },
                })?.done(response => {
                    $('#radioArchiveModal').modal('hide');
                    rmFeedback.toast(response.message || radioArchive.words.done);
                    radioArchive.onDone(response);
                }).fail(RadioUi.fail);
            },

            restore(id, label) {
                const words = radioArchive.words;
                rmDialog.confirm({
                    tone: 'green',
                    icon: 'archive-off',
                    title: words.restore_title,
                    text: RadioUi.fill(words.restore_text, { radio: label || '' }),
                    confirmText: words.restore_submit,
                    confirmKind: 'success',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: radioArchive.urls.restore.replace(':id', id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'PUT' },
                    }).done(response => {
                        const note = response.dmr_id ? RadioUi.fill(words.id_back, { id: response.dmr_id }) : words.id_gone;
                        rmFeedback.toast(`${response.message} ${note}`);
                        radioArchive.onDone(response);
                    }).fail(RadioUi.fail);
                });
            },
        };
    </script>
@endpush
