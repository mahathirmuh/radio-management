{{-- Archive (take out of service) / restore. Used by the register list and the detail page. --}}
<div class="modal fade" data-bs-backdrop="static" id="radioArchiveModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
            <div class="modal-body p-0">
                <h3 class="modal-title mb-1">Arsipkan Radio</h3>
                <p class="radio-muted mb-3" id="radioArchiveLabel"></p>
                <div class="row g-2">
                    <div class="col-md-7">
                        <label for="archive_reason" class="form-label required">Alasan</label>
                        <select id="archive_reason" class="form-select">
                            @foreach ($archiveReasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="archived_at" class="form-label required">Tanggal</label>
                        <input type="date" id="archived_at" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="archive_note" class="form-label">Catatan</label>
                        <textarea id="archive_note" class="form-control" rows="2" maxlength="1000"
                            placeholder="Mis. dikirim ke site Konawe, BA kehilangan no. ..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-warning py-2 mb-0 small">
                            Radio yang diarsipkan keluar dari daftar aktif dan Radio ID-nya dilepas sehingga bisa dipakai
                            radio lain.
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true"
                            data-bs-dismiss="modal">
                            <x-slot:icon>
                                <i class="ti ti-x"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                        <x-global.btn-detail label="Arsipkan" color="orange" size="sm" id="radioArchiveButton"
                            onclick="radioArchive.submit()">
                            <x-slot:icon>
                                <i class="ti ti-archive"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    </div>
                </div>
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
                $('#archive_reason').prop('selectedIndex', 0);
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
                        archive_reason: $('#archive_reason').val(),
                        archived_at: $('#archived_at').val(),
                        note: $('#archive_note').val(),
                    },
                })?.done(response => {
                    $('#radioArchiveModal').modal('hide');
                    Swal.fire('Berhasil', response.message, 'success');
                    radioArchive.onDone(response);
                }).fail(RadioUi.fail);
            },

            restore(id, label) {
                Swal.fire({
                    title: 'Pulihkan radio dari arsip?',
                    text: label || '',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-check"></i> Pulihkan',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
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
                        Swal.fire('Berhasil', `${response.message} ${note}`, 'success');
                        radioArchive.onDone(response);
                    }).fail(RadioUi.fail);
                });
            },
        };
    </script>
@endpush
