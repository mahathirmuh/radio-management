@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'Import Excel')

@section('content')
    <div class="rm-import-grid">
        <section class="rm-cardbox">
            <header class="rm-cardbox-head">
                <div>
                    <h2 class="rm-cardbox-title">Impor register radio dari Excel</h2>
                    <div class="rm-cardbox-sub">Membaca sheet <b>Form</b> (dan <b>to Konawe</b>) dari file register lama,
                        misalnya <span class="radio-mono">Radio Register 07-08-25.xlsx</span>.</div>
                </div>
            </header>
            <div class="rm-cardbox-body d-flex flex-column gap-3">
                <ul class="rm-import-rules">
                    <li>1 baris berisi SN atau User Name = 1 radio. Baris yang hanya berisi No + Radio ID (slot kosong)
                        dilewati.</li>
                    <li>Ejaan, spasi, dan isi kolom Status dirapikan otomatis menjadi <b>Posisi</b> + <b>Kondisi</b>.</li>
                    <li>Data yang bertentangan (SN/ID dobel, status vs catatan, dll.) <b>tidak ditebak</b>: radio tetap
                        diimpor dan ditandai <b>Perlu dicek</b>.</li>
                    <li>Isi asli setiap baris disimpan dan bisa dilihat di halaman detail radio.</li>
                </ul>

                <form id="importForm" class="d-flex flex-column gap-3" onsubmit="return false;">
                    <div class="rm-field">
                        <span class="form-label required">File Excel (.xlsx)</span>
                        <label class="rm-file" for="importFile">
                            <span class="rm-file-btn"><i class="ti ti-upload" aria-hidden="true"></i> Pilih file</span>
                            <span class="rm-file-name" id="importFileName">Belum ada file dipilih</span>
                            <input type="file" id="importFile" accept=".xlsx" class="visually-hidden">
                        </label>
                    </div>
                    <label class="rm-check">
                        <input type="checkbox" id="importKonawe" checked>
                        <span>Impor juga sheet "to Konawe" (radio pindah site)</span>
                    </label>
                    {{-- Wiping the register needs Delete on Register Radio (checked again on the server). --}}
                    @permission('radio-management.radio', 'Delete')
                        <label class="rm-check is-danger">
                            <input type="checkbox" id="importFresh" @checked($existing > 0)>
                            <span>Hapus semua data radio & riwayat yang ada sebelum impor</span>
                        </label>
                    @endpermission
                    @if ($existing > 0)
                        <div class="rm-alert warning">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                            <span>Saat ini ada <b>{{ number_format($existing) }}</b> radio di database. Impor ulang hanya
                                bisa dilakukan dengan menghapus data lama (master data tetap dipertahankan).
                                @permission('radio-management.radio', 'Delete')
                                @else
                                    Role Anda tidak punya izin <b>Delete</b> pada menu Register Radio, jadi impor ulang tidak
                                    bisa dilakukan dari akun ini.
                                @endpermission
                            </span>
                        </div>
                    @endif
                    <div>
                        <button type="button" class="btn-rm primary" id="importButton" onclick="radioImport.start()">
                            <i class="ti ti-file-import" aria-hidden="true"></i> Mulai impor
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="rm-cardbox">
            <div id="importReport">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title">Hasil impor terakhir</h2>
                </header>
                <div class="rm-cardbox-body radio-muted">Belum ada impor.</div>
            </div>
        </section>
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
                // Orange when the register is wiped first (the design's import dialog).
                rmDialog.confirm({
                    tone: fresh ? 'orange' : 'blue',
                    icon: fresh ? 'alert-triangle' : 'file-import',
                    title: 'Mulai impor?',
                    html: fresh ? '<b>Semua data radio dan riwayat yang ada akan dihapus</b> lalu diganti isi file ini.' :
                        'Data dari file akan ditambahkan ke database.',
                    confirmText: 'Impor',
                    confirmKind: 'primary',
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
                        Swal.close();
                        rmFeedback.toast(`${response.report.imported} radio diimpor, ${response.report.flagged} ditandai "Perlu dicek".`);
                        radioImport.render(response.report);
                        $('#importFresh').prop('checked', true);
                    }).fail(RadioUi.fail);
                });
            },

            render(report) {
                if (!report) return;
                const esc = RadioUi.esc;
                const stat = (label, value, tone) => `
                    <div class="rm-import-stat tone-${tone}">
                        <div class="rm-import-stat-label">${label}</div>
                        <div class="rm-import-stat-value">${esc(value)}</div>
                    </div>`;
                const flags = (report.flags || []).map(flag => `
                    <tr>
                        <td class="text-nowrap">${esc(flag.sheet)} #${esc(flag.row)}</td>
                        <td class="text-nowrap"><a href="${esc(radioImport.detailUrl.replace(':id', flag.radio))}" class="radio-mono">${esc(flag.sn || '-')}</a>
                            <div class="radio-muted">${flag.dmr_id ? 'ID ' + esc(flag.dmr_id) : ''}</div></td>
                        <td><ul class="mb-0 ps-3">${flag.reasons.map(r => `<li>${esc(r)}</li>`).join('')}</ul></td>
                    </tr>`).join('');
                const skipped = (report.skipped || []).map(item =>
                    `<li>${esc(item.sheet)} baris ${esc(item.row)}: ${esc(item.reason)}</li>`).join('');
                const created = report.masters_created || {};

                $('#importReport').html(`
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title">Hasil impor terakhir</h2>
                            <div class="rm-cardbox-sub">${esc(report.file)} · ${esc(report.at || '')} ${report.by ? '· ' + esc(report.by) : ''} · ${esc(report.seconds)} detik</div>
                        </div>
                    </header>
                    <div class="rm-cardbox-body d-flex flex-column gap-3">
                        <div class="rm-import-stats">
                            ${stat('Radio diimpor', report.imported, 'blue')}
                            ${stat('Aktif', report.imported_active, 'green')}
                            ${stat('Diarsipkan', report.imported_archived, 'gray')}
                            ${stat('Perlu dicek', report.flagged, 'red')}
                        </div>
                        <div class="rm-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>Slot ID kosong dilewati: <b>${esc(report.empty_slots)}</b> ·
                            Master baru: tipe ${esc(created.types ?? 0)}, perusahaan ${esc(created.companies ?? 0)},
                            department ${esc(created.departments ?? 0)}, template ${esc(created.templates ?? 0)}</span></div>
                        ${skipped ? `<div class="rm-alert warning"><i class="ti ti-alert-triangle" aria-hidden="true"></i><div><b>Baris dilewati</b> (ada isi tapi tanpa SN/User Name):<ul class="mb-0 ps-3">${skipped}</ul></div></div>` : ''}
                        ${flags ? `
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rm-cardbox-title">Radio yang perlu dicek</div>
                                <a href="${radioImport.radioUrl}?issue=needs_review" class="rm-cardbox-link">Buka di Register Radio <i class="ti ti-arrow-right"></i></a>
                            </div>
                            <div class="rm-box table-responsive" style="max-height: 480px">
                                <table class="rm-table compact">
                                    <thead><tr><th>Baris</th><th>SN</th><th>Alasan</th></tr></thead>
                                    <tbody>${flags}</tbody>
                                </table>
                            </div>` : ''}
                    </div>
                `);
            },
        };

        $(function() {
            radioImport.render(@json($lastReport));
            // The name of the file chosen, as text.
            $('#importFile').on('change', function() {
                const file = this.files && this.files[0];
                $('#importFileName').text(file ? file.name : 'Belum ada file dipilih').toggleClass('is-set', !!file);
            });
        });
    </script>
@endpush
