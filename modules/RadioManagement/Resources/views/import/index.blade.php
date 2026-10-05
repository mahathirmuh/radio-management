@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'Import Excel')

@section('content')
    <div class="row g-2">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="radio-section-title">Impor Register Radio dari Excel</div>
                    <p class="small mb-2">Membaca sheet <b>Form</b> (dan <b>to Konawe</b>) dari file register lama,
                        misalnya <span class="radio-mono">Radio Register 07-08-25.xlsx</span>.</p>
                    <ul class="small ps-3 mb-3">
                        <li>1 baris berisi SN atau User Name = 1 radio. Baris yang hanya berisi No + Radio ID (slot kosong)
                            dilewati.</li>
                        <li>Ejaan, spasi, dan isi kolom Status dirapikan otomatis menjadi <b>Posisi</b> + <b>Kondisi</b>.
                        </li>
                        <li>Data yang bertentangan (SN/ID dobel, status vs catatan, dll.) <b>tidak ditebak</b>: radio
                            tetap diimpor dan ditandai <b>Perlu dicek</b>.</li>
                        <li>Isi asli setiap baris disimpan dan bisa dilihat di halaman detail radio.</li>
                    </ul>

                    <form id="importForm">
                        <div class="mb-2">
                            <label for="importFile" class="form-label required">File Excel (.xlsx)</label>
                            <input type="file" id="importFile" class="form-control" accept=".xlsx">
                        </div>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" id="importKonawe" checked>
                            <span class="form-check-label">Impor juga sheet "to Konawe" (radio pindah site)</span>
                        </label>
                        {{-- Wiping the register needs Delete on Register Radio (checked again on the server). --}}
                        @permission('radio-management.radio', 'Delete')
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" id="importFresh" @checked($existing > 0)>
                                <span class="form-check-label">Hapus semua data radio & riwayat yang ada sebelum impor</span>
                            </label>
                        @endpermission
                        @if ($existing > 0)
                            <div class="alert alert-warning py-2 small">
                                Saat ini ada <b>{{ number_format($existing) }}</b> radio di database. Impor ulang hanya bisa
                                dilakukan dengan menghapus data lama (master data tetap dipertahankan).
                                @permission('radio-management.radio', 'Delete')
                                @else
                                    Role Anda tidak punya izin <b>Delete</b> pada menu Register Radio, jadi impor ulang
                                    tidak bisa dilakukan dari akun ini.
                                @endpermission
                            </div>
                        @endif
                        <x-global.btn-detail label="Mulai Impor" color="blue" size="sm" id="importButton"
                            onclick="radioImport.start()">
                            <x-slot:icon>
                                <i class="ti ti-file-import"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body p-3" id="importReport">
                    <div class="radio-section-title">Hasil Impor Terakhir</div>
                    <div class="radio-muted">Belum ada impor.</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const radioImport = {
            url: "{{ route('radio-management.import.store') }}",
            radioUrl: "{{ route('radio-management.radio.index') }}",
            detailUrl: "{{ route('radio-management.radio.detail', ':id') }}",

            start() {
                const file = $('#importFile')[0].files[0];
                if (!file) {
                    showError(['Pilih file Excel (.xlsx) terlebih dahulu.']);
                    return;
                }
                const fresh = $('#importFresh').is(':checked');
                Swal.fire({
                    title: 'Mulai impor?',
                    html: fresh ? '<b>Semua data radio dan riwayat yang ada akan dihapus</b> lalu diganti isi file ini.' :
                        'Data dari file akan ditambahkan ke database.',
                    icon: fresh ? 'warning' : 'question',
                    showCancelButton: true,
                    confirmButtonText: '<i class="ti ti-check"></i> Impor',
                    cancelButtonText: '<i class="ti ti-x"></i> Batal',
                    reverseButtons: true,
                }).then(result => {
                    if (!result.isConfirmed) return;
                    const data = new FormData();
                    data.append('file', file);
                    data.append('fresh', fresh ? 1 : 0);
                    data.append('konawe', $('#importKonawe').is(':checked') ? 1 : 0);

                    Swal.fire({
                        title: 'Mengimpor…',
                        text: 'Mohon tunggu, proses bisa memakan waktu sekitar 1 menit.',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                    });
                    $.ajax({
                        url: radioImport.url,
                        type: 'POST',
                        data,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                    }).done(response => {
                        Swal.fire('Impor selesai', `${response.report.imported} radio diimpor, ${response.report.flagged} ditandai "Perlu dicek".`, 'success');
                        radioImport.render(response.report);
                        $('#importFresh').prop('checked', true);
                    }).fail(RadioUi.fail);
                });
            },

            render(report) {
                if (!report) return;
                const esc = RadioUi.esc;
                const stat = (label, value, color) => `
                    <div class="col-6 col-md-3">
                        <div class="border rounded-3 p-2 h-100">
                            <div class="radio-muted">${label}</div>
                            <div class="fs-2 fw-bold text-${color}">${esc(value)}</div>
                        </div>
                    </div>`;
                const flags = (report.flags || []).map(flag => `
                    <tr>
                        <td class="text-nowrap small">${esc(flag.sheet)} #${esc(flag.row)}</td>
                        <td class="text-nowrap"><a href="${esc(radioImport.detailUrl.replace(':id', flag.radio))}" class="radio-mono">${esc(flag.sn || '-')}</a>
                            <div class="radio-muted">${flag.dmr_id ? 'ID ' + esc(flag.dmr_id) : ''}</div></td>
                        <td><ul class="mb-0 ps-3 small">${flag.reasons.map(r => `<li>${esc(r)}</li>`).join('')}</ul></td>
                    </tr>`).join('');
                const skipped = (report.skipped || []).map(item =>
                    `<li>${esc(item.sheet)} baris ${esc(item.row)}: ${esc(item.reason)}</li>`).join('');
                const created = report.masters_created || {};

                $('#importReport').html(`
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="radio-section-title flex-grow-1">Hasil Impor Terakhir</div>
                    </div>
                    <div class="radio-muted mb-2">${esc(report.file)} · ${esc(report.at || '')} ${report.by ? '· ' + esc(report.by) : ''} · ${esc(report.seconds)} detik</div>
                    <div class="row g-2 mb-3">
                        ${stat('Radio diimpor', report.imported, 'blue')}
                        ${stat('Aktif', report.imported_active, 'green')}
                        ${stat('Diarsipkan', report.imported_archived, 'secondary')}
                        ${stat('Perlu dicek', report.flagged, 'red')}
                    </div>
                    <div class="small mb-2">Slot ID kosong dilewati: <b>${esc(report.empty_slots)}</b> ·
                        Master baru: tipe ${esc(created.types ?? 0)}, perusahaan ${esc(created.companies ?? 0)},
                        department ${esc(created.departments ?? 0)}, template ${esc(created.templates ?? 0)}</div>
                    ${skipped ? `<div class="alert alert-warning py-2 small"><b>Baris dilewati</b> (ada isi tapi tanpa SN/User Name):<ul class="mb-0 ps-3">${skipped}</ul></div>` : ''}
                    ${flags ? `
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-bold small">Radio yang perlu dicek</div>
                            <a href="${radioImport.radioUrl}?issue=needs_review" class="small">Buka di Register Radio <i class="ti ti-arrow-right"></i></a>
                        </div>
                        <div class="table-responsive border rounded" style="max-height: 480px">
                            <table class="table table-sm table-vcenter mb-0">
                                <thead><tr class="mdka-bg-gray-50"><th>Baris</th><th>SN</th><th>Alasan</th></tr></thead>
                                <tbody>${flags}</tbody>
                            </table>
                        </div>` : ''}
                `);
            },
        };

        $(function() {
            radioImport.render(@json($lastReport));
        });
    </script>
@endpush
