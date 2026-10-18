{{-- Several radios at once (the bar: radio.bulk-bar). The radios ticked are kept while the page or the filter changes;
     "every radio of the filter" holds only as long as the filter stays the same. Verifying takes every radio of a filter,
     archiving and deleting only radios ticked one by one (RadioBulk on the server says the same). --}}
@php
    $bulkNote = \Modules\RadioManagement\Http\Controllers\RadioBulkController::DEFAULT_NOTE;
@endphp
<div class="modal fade" data-bs-backdrop="static" id="radioBulkVerifyModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
            <div class="modal-body p-0">
                <h3 class="modal-title mb-1">Verifikasi Radio</h3>
                <p class="radio-muted mb-3" id="radioBulkVerifyLabel"></p>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label for="bulk_verified_at" class="form-label required">Tanggal</label>
                        <input type="date" id="bulk_verified_at" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="bulk_verify_note" class="form-label">Catatan</label>
                        <textarea id="bulk_verify_note" class="form-control" rows="2" maxlength="2000"
                            placeholder="Mis. stock opname site Weda Bay"></textarea>
                        <div class="form-hint">Bila kosong: "{{ $bulkNote }}"</div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-0 small">
                            Setiap radio mendapat riwayat <b>Verifikasi</b> pada tanggal ini, dan tanggal verifikasinya
                            ikut maju bila tanggal ini lebih baru. Radio yang diarsipkan dilewati.
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true" data-bs-dismiss="modal">
                            <x-slot:icon>
                                <i class="ti ti-x"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                        <x-global.btn-detail label="Verifikasi" color="green" size="sm" id="radioBulkVerifyButton"
                            onclick="radioBulk.submitVerify()">
                            <x-slot:icon>
                                <i class="ti ti-checklist"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" data-bs-backdrop="static" id="radioBulkArchiveModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
            <div class="modal-body p-0">
                <h3 class="modal-title mb-1">Arsipkan Radio</h3>
                <p class="radio-muted mb-3" id="radioBulkArchiveLabel"></p>
                <div class="row g-2">
                    <div class="col-md-7">
                        <label for="bulk_archive_reason" class="form-label required">Alasan</label>
                        <select id="bulk_archive_reason" class="form-select">
                            @foreach ($archiveReasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="bulk_archived_at" class="form-label required">Tanggal</label>
                        <input type="date" id="bulk_archived_at" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="bulk_archive_note" class="form-label">Catatan</label>
                        <textarea id="bulk_archive_note" class="form-control" rows="2" maxlength="1000"
                            placeholder="Mis. dikirim ke site Konawe, BA no. ..."></textarea>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-warning py-2 mb-0 small">
                            Alasan, tanggal dan catatan yang sama untuk semua radio yang dipilih. Radio yang diarsipkan
                            keluar dari daftar aktif dan Radio ID-nya dilepas.
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true" data-bs-dismiss="modal">
                            <x-slot:icon>
                                <i class="ti ti-x"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                        <x-global.btn-detail label="Arsipkan" color="orange" size="sm" id="radioBulkArchiveButton"
                            onclick="radioBulk.submitArchive()">
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
        const radioBulk = {
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
                return `<input type="checkbox" class="form-check-input m-0 radio-pick" aria-label="Pilih ${label}" data-id="${Number(r.id)}" data-label="${label}"${on ? ' checked' : ''}${radioBulk.all ? ' disabled' : ''}>`;
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
                $('#radioBulkCount').text(radioBulk.all ? `Semua ${radioBulk.all.total} radio hasil filter dipilih` : `${count} radio dipilih`);
                const onPage = radioBulk.rows.filter(r => radioBulk.picked.has(r.id)).length;
                const whole = radioBulk.rows.length > 0 && (radioBulk.all !== null || onPage === radioBulk.rows.length);
                $('#radioPickPage').prop('checked', whole).prop('indeterminate', !whole && onPage > 0);
                // Every radio of the filter is offered once the whole page is ticked and the filter has more.
                const offer = !radioBulk.all && whole && radioBulk.total > radioBulk.rows.length;
                $('#radioBulkAll').toggleClass('d-none', !offer).text(`Pilih semua ${radioBulk.total} radio hasil filter`);
                // Archiving and deleting: radios ticked one by one, up to the limit.
                const ticked = !radioBulk.all && count <= radioBulk.max.ticked;
                const why = radioBulk.all ? 'Untuk aksi ini, centang radionya satu per satu.' :
                    (ticked ? '' : `Paling banyak ${radioBulk.max.ticked} radio sekaligus.`);
                $('#radioBulkArchive').prop('disabled', !ticked).attr('title', why);
                $('#radioBulkDelete').prop('disabled', !ticked).attr('title', why);
                const fits = count <= radioBulk.max.all;
                $('#radioBulkVerify').prop('disabled', !fits).attr('title', fits ? '' : `Paling banyak ${radioBulk.max.all} radio sekaligus.`);
            },
            /** What an action sends: the ids ticked, or the filter with all=1. */
            payload() {
                return radioBulk.all ? { all: 1, ...radioBulk.all.params } : { ids: [...radioBulk.picked.keys()] };
            },
            chosenText() {
                return radioBulk.all ? `Semua ${radioBulk.all.total} radio hasil filter.` : `${radioBulk.count()} radio dipilih.`;
            },

            verify() {
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
                Swal.fire({
                    title: `Hapus ${count} radio?`,
                    html: 'Hapus hanya untuk data yang salah input; riwayatnya tetap tersimpan, tetapi radionya tidak bisa ' +
                        'dikembalikan dari aplikasi. Radio yang sudah tidak dipakai sebaiknya <b>diarsipkan</b>.<br><br>' +
                        `Ketik <b>${count}</b> untuk menghapus.`,
                    icon: 'warning',
                    input: 'text',
                    inputAttributes: { autocomplete: 'off', inputmode: 'numeric' },
                    showCancelButton: true,
                    // Red: there is no way back from the page.
                    confirmButtonColor: '#d63939',
                    confirmButtonText: '<i class="ti ti-trash"></i> Hapus',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    if (String(result.value || '').trim() !== String(count)) {
                        Swal.fire('Tidak dihapus', `Yang diketik bukan ${count}.`, 'info');
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

            /** The Excel of the list, of the radios ticked (by their ids) or of every radio of the filter. */
            exportSelected() {
                const params = radioBulk.all ? { ...radioBulk.all.params } : { ids: [...radioBulk.picked.keys()] };
                window.location = radioBulk.urls.export + '?' + $.param({ ...params, orderBy: radioTable.orderBy, orderDir: radioTable.orderDir });
            },

            /** What was done, and to which radios not and why (as text); then a fresh list without a choice. */
            finished(response) {
                const skipped = Array.isArray(response.skipped) ? response.skipped : [];
                const items = skipped.slice(0, 10).map(s => `<li>${RadioUi.esc(s.radio)}: ${RadioUi.esc(s.reason)}</li>`).join('');
                const more = skipped.length > 10 ? `<div class="small">dan ${skipped.length - 10} lainnya</div>` : '';
                Swal.fire({
                    title: skipped.length ? 'Sebagian dilewati' : 'Berhasil',
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
            $('#radioPickPage').on('change', function() {
                radioBulk.pickPage($(this).is(':checked'));
            });
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
