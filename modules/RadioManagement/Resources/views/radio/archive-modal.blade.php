{{-- Archive (take out of service) / restore. Used by the register list and the detail page. --}}
@php
    // What each reason of config('radio.archive_reasons') means, under its name.
    $archiveHints = [
        'Pindah Site' => 'Radio kini masuk register site lain',
        'Afkir' => 'Akhir masa pakai',
        'Hilang (BA selesai)' => 'Radio hilang, BA sudah ditandatangani',
        'Lainnya' => 'Jelaskan di catatan',
    ];
@endphp
<div class="modal fade rm-modal" data-bs-backdrop="static" id="radioArchiveModal" aria-hidden="true"
    aria-labelledby="radioArchiveTitle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="rm-dialog-head">
                <span class="rm-dialog-icon tone-orange"><i class="ti ti-archive" aria-hidden="true"></i></span>
                <div class="rm-dialog-titles">
                    <h3 class="rm-dialog-title" id="radioArchiveTitle">Arsipkan radio?</h3>
                    <p class="rm-dialog-sub"><span id="radioArchiveLabel"></span>. Radio keluar dari register aktif,
                        riwayatnya tetap tersimpan.</p>
                </div>
                <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                        class="ti ti-x"></i></button>
            </div>
            <div class="rm-dialog-body">
                <div class="rm-field">
                    <span class="form-label required">Alasan</span>
                    <div class="rm-choices" role="radiogroup" aria-label="Alasan">
                        @foreach ($archiveReasons as $i => $reason)
                            <label class="rm-choice">
                                <input type="radio" name="archive_reason" value="{{ $reason }}"
                                    id="archiveReason{{ $i }}" @checked($i === 0)>
                                <span>
                                    <span class="rm-choice-label">{{ $reason }}</span>
                                    @if (isset($archiveHints[$reason]))
                                        <span class="rm-choice-hint">{{ $archiveHints[$reason] }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="rm-field">
                    <label for="archived_at" class="form-label required">Tanggal</label>
                    <input type="date" id="archived_at" class="form-control">
                </div>
                <div class="rm-field">
                    <label for="archive_note" class="form-label">Catatan</label>
                    <textarea id="archive_note" class="form-control" rows="3" maxlength="1000"
                        placeholder="Mis. dikirim ke site Konawe, BA no. ..."></textarea>
                </div>
                <div class="rm-note">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                    <span>Radio ID-nya dilepas dan bisa dipakai radio baru. Riwayatnya tetap ada di arsip.</span>
                </div>
            </div>
            <div class="rm-dialog-foot">
                <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn-rm primary" id="radioArchiveButton" onclick="radioArchive.submit()">
                    Arsipkan radio
                </button>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        const radioArchive = {
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
                    rmFeedback.toast(response.message || 'Radio diarsipkan.');
                    radioArchive.onDone(response);
                }).fail(RadioUi.fail);
            },

            restore(id, label) {
                rmDialog.confirm({
                    tone: 'green',
                    icon: 'archive-off',
                    title: 'Pulihkan radio dari arsip?',
                    text: `${label || ''}. Radio kembali ke register aktif dengan Radio ID lamanya bila masih bebas.`,
                    confirmText: 'Pulihkan radio',
                    confirmKind: 'success',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: radioArchive.urls.restore.replace(':id', id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'PUT' },
                    }).done(response => {
                        const note = response.dmr_id ? `Radio ID ${response.dmr_id} dipakai kembali.` :
                            'Radio ID lama tidak tersedia, isi Radio ID baru lewat Edit.';
                        rmFeedback.toast(`${response.message} ${note}`);
                        radioArchive.onDone(response);
                    }).fail(RadioUi.fail);
                });
            },
        };
    </script>
@endpush
