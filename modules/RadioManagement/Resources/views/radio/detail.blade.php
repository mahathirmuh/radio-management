@extends('main.index')
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
    <style>
        .radio-dl dt {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--tblr-secondary);
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .radio-dl dd {
            margin-bottom: .6rem;
            font-size: 0.8rem;
        }

        .radio-hero-id {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
        }

        .radio-change-table td {
            padding: .15rem .5rem;
            font-size: 0.7rem;
        }
    </style>
@endpush

@include('RadioManagement::partials.scripts')

@section('page-title', 'Detail Radio')

@section('content')
    @php
        $label = ($radio->dmr_id ? 'ID ' . $radio->dmr_id . ' · ' : '') . ($radio->serial_number ?? 'tanpa SN');
    @endphp
    <div class="row g-2">
        {{-- Header --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar avatar-lg rounded-3 bg-blue-lt"><i class="ti ti-radio fs-1"></i></span>
                        <div>
                            <div class="radio-muted">Radio ID / MDC</div>
                            <div class="radio-hero-id">
                                @if ($radio->dmr_id)
                                    {{ $radio->dmr_id }} <span class="radio-mono text-secondary fs-4">/ {{ $radio->mdc_code }}</span>
                                @elseif ($radio->previous_dmr_id)
                                    <span class="text-secondary">ex {{ $radio->previous_dmr_id }}</span>
                                @else
                                    <span class="text-secondary">belum ada</span>
                                @endif
                            </div>
                            <div class="mt-1 radio-mono">SN {{ $radio->serial_number ?? '-' }}</div>
                        </div>
                        <div class="d-flex flex-column gap-1 ms-2">
                            @if ($radio->isArchived())
                                <span class="badge bg-secondary-lt radio-badge">Arsip · {{ $radio->archive_reason }} ·
                                    {{ $radio->archived_at->format('d-m-Y') }}</span>
                            @else
                                <span class="badge bg-{{ $radio->placement->color() }}-lt radio-badge">{{ $radio->placement->label() }}</span>
                            @endif
                            <span class="badge bg-{{ $radio->condition->color() }}-lt radio-badge">{{ $radio->condition->label() }}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <x-global.btn-detail label="Kembali" color="secondary" size="sm" outline="true"
                            href="{{ route('radio-management.radio.index') }}">
                            <x-slot:icon>
                                <i class="ti ti-arrow-left"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                        <x-global.btn-detail label="Tambah Riwayat" color="purple" size="sm" outline="true"
                            onclick="radioHistory.open()">
                            <x-slot:icon>
                                <i class="ti ti-timeline-event-plus"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                        @if ($radio->isArchived())
                            <x-global.btn-detail label="Pulihkan" color="green" size="sm" outline="true"
                                onclick="radioArchive.restore({{ $radio->id }}, radioLabel)">
                                <x-slot:icon>
                                    <i class="ti ti-archive-off"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        @else
                            <x-global.btn-detail label="Arsipkan" color="orange" size="sm" outline="true"
                                onclick="radioArchive.open({{ $radio->id }}, radioLabel)">
                                <x-slot:icon>
                                    <i class="ti ti-archive"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        @endif
                        <x-global.btn-detail label="Hapus" color="red" size="sm" outline="true" onclick="radioDelete()">
                            <x-slot:icon>
                                <i class="ti ti-trash"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                        <x-global.btn-detail label="Edit" color="orange" size="sm"
                            onclick="radioForm.open('edit', {{ $radio->id }})">
                            <x-slot:icon>
                                <i class="ti ti-edit"></i>
                            </x-slot:icon>
                        </x-global.btn-detail>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cek --}}
        @if (count($issues))
            <div class="col-12">
                <div class="card border-0">
                    <div class="card-body p-3">
                        <div class="radio-section-title">Hasil Cek</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($issues as $issue)
                                @if ($issue['code'] !== 'needs_review')
                                    <span class="badge bg-{{ $issue['color'] }}-lt radio-badge py-2 px-2">
                                        {{ $issue['label'] }}@if ($issue['hint'])
                                            <span class="fw-normal"> · {{ $issue['hint'] }}</span>
                                        @endif
                                    </span>
                                @endif
                            @endforeach
                        </div>
                        @if ($radio->needs_review)
                            <div class="alert alert-danger py-2 mt-3 mb-0">
                                <div class="fw-bold mb-1"><i class="ti ti-alert-triangle me-1"></i>Perlu dicek</div>
                                <ul class="mb-1 ps-3 small">
                                    @foreach (array_filter(explode("\n", (string) $radio->review_notes)) as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                                <div class="small">Setelah dibereskan, buka <b>Edit</b> lalu centang "Sudah dicek".</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Data --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="radio-section-title">Identitas Unit</div>
                    <dl class="radio-dl mb-0">
                        <dt>SN</dt>
                        <dd class="radio-mono">{{ $radio->serial_number ?? '-' }}</dd>
                        <dt>Tipe Radio</dt>
                        <dd>{{ $radio->radioType?->name ?? '-' }}</dd>
                        <dt>Kategori · Daya</dt>
                        <dd>{{ $radio->radioType?->category_label ?? '-' }} · {{ $radio->radioType?->power ?? '-' }}</dd>
                        <dt>Pemilik Aset</dt>
                        <dd>{{ $radio->ownerCompany?->label ?? '-' }}</dd>
                        <dt>Owner</dt>
                        <dd>{{ $radio->ownerGroup() ?? '-' }}</dd>
                        @if ($radio->previous_dmr_id)
                            <dt>Radio ID Lama</dt>
                            <dd>{{ $radio->previous_dmr_id }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="radio-section-title">Pengguna</div>
                    <dl class="radio-dl mb-0">
                        <dt>Nama User / Unit</dt>
                        <dd>{{ $radio->user_name ?? '-' }}</dd>
                        <dt>Department</dt>
                        <dd>{{ $radio->department?->name ?? '-' }}</dd>
                        <dt>Company</dt>
                        <dd>{{ $radio->company?->label ?? '-' }}</dd>
                        <dt>No. Dokumen (PO / BA)</dt>
                        <dd>{{ $radio->document_no ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="radio-section-title">Program & Verifikasi</div>
                    <dl class="radio-dl mb-0">
                        <dt>Template Channel</dt>
                        <dd>
                            {{ $radio->channelTemplate?->name ?? '-' }}
                            @if ($radio->channelTemplate?->channels)
                                <div class="radio-muted">{{ $radio->channelTemplate->channels }}</div>
                            @endif
                        </dd>
                        <dt>Tgl Program Terakhir</dt>
                        <dd>{{ $radio->last_programmed_at?->format('d-m-Y') ?? '-' }}</dd>
                        <dt>Tgl Verifikasi Terakhir</dt>
                        <dd>{{ $radio->last_verified_at?->format('d-m-Y') ?? '-' }}</dd>
                        <dt>Terakhir diubah</dt>
                        <dd>{{ $radio->updated_at?->format('d-m-Y H:i') }} {{ $radio->updater ? 'oleh ' . $radio->updater->name : '' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        @if ($radio->notes)
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="radio-section-title">Keterangan</div>
                        <div class="radio-pre small">{{ $radio->notes }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Riwayat --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="radio-section-title">Riwayat</div>
                    @if ($radio->histories->isEmpty())
                        <div class="radio-muted">Belum ada riwayat.</div>
                    @else
                        <ul class="timeline mb-0">
                            @foreach ($radio->histories as $history)
                                <li class="timeline-event">
                                    <div class="timeline-event-icon bg-blue-lt">
                                        <i class="ti {{ match ($history->action) {
                                            'Serah Terima' => 'ti-transfer',
                                            'Service' => 'ti-tool',
                                            'Program' => 'ti-cpu',
                                            'Verifikasi' => 'ti-checklist',
                                            'Ubah Status' => 'ti-status-change',
                                            'Arsip' => 'ti-archive',
                                            'Dipulihkan' => 'ti-archive-off',
                                            'Impor' => 'ti-file-import',
                                            'Dibuat' => 'ti-plus',
                                            default => 'ti-pencil',
                                        } }}"></i>
                                    </div>
                                    <div class="card timeline-event-card border-0 shadow-none">
                                        <div class="card-body p-0">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <span class="fw-bold">{{ $history->action }}</span>
                                                    @if ($history->document_no)
                                                        <span class="badge bg-azure-lt radio-badge ms-1">{{ $history->document_no }}</span>
                                                    @endif
                                                </div>
                                                <div class="radio-muted">{{ $history->event_date->format('d-m-Y') }} ·
                                                    {{ $history->user?->name ?? 'Sistem' }}</div>
                                            </div>
                                            @if ($history->changes)
                                                <table class="radio-change-table mt-1">
                                                    @foreach ($history->changes as $change)
                                                        <tr>
                                                            <td class="radio-muted">{{ $change['label'] }}</td>
                                                            <td>{{ $change['old'] ?? '-' }}</td>
                                                            <td><i class="ti ti-arrow-right"></i></td>
                                                            <td class="fw-bold">{{ $change['new'] ?? '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            @elseif ($history->description)
                                                <div class="small radio-pre">{{ $history->description }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- Data asli Excel --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body p-3">
                    <div class="radio-section-title">Data Asli Excel</div>
                    @if ($radio->source_data)
                        <div class="radio-muted mb-2">Sheet "{{ $radio->source_sheet }}" baris {{ $radio->source_row }}</div>
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach ($radio->source_data as $column => $value)
                                    <tr>
                                        <td class="radio-muted text-nowrap">{{ $column }}</td>
                                        <td class="small text-break">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="radio-muted">Radio ini ditambahkan langsung di aplikasi, bukan dari Excel.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('RadioManagement::radio.modal')
    @include('RadioManagement::radio.archive-modal')

    {{-- Tambah riwayat --}}
    <div class="modal fade" data-bs-backdrop="static" id="radioHistoryModal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-3">
                <button type="button" class="btn-close btn-pinned" data-bs-dismiss="modal" aria-label="Tutup"></button>
                <div class="modal-body p-0">
                    <h3 class="modal-title mb-3">Tambah Riwayat</h3>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="history_action" class="form-label required">Jenis</label>
                            <select id="history_action" class="form-select">
                                @foreach ($historyActions as $action)
                                    <option value="{{ $action }}">{{ $action }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="history_date" class="form-label required">Tanggal</label>
                            <input type="date" id="history_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label for="history_description" class="form-label required">Keterangan</label>
                            <textarea id="history_description" class="form-control" rows="3" maxlength="2000"
                                placeholder="Mis. program ulang template General v2, service ganti LCD di CN, ..."></textarea>
                            <div class="form-hint">Jenis "Program" / "Verifikasi" juga memperbarui tanggal program /
                                verifikasi radio.</div>
                        </div>
                        <div class="col-12">
                            <label for="history_document" class="form-label">No. Dokumen (PO / BA)</label>
                            <input type="text" id="history_document" class="form-control" maxlength="100">
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            <x-global.btn-detail label="Batal" color="secondary" size="sm" outline="true"
                                data-bs-dismiss="modal">
                                <x-slot:icon>
                                    <i class="ti ti-x"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                            <x-global.btn-detail label="Simpan" color="blue" size="sm" id="radioHistoryButton"
                                onclick="radioHistory.submit()">
                                <x-slot:icon>
                                    <i class="ti ti-device-floppy"></i>
                                </x-slot:icon>
                            </x-global.btn-detail>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
    <script>
        const radioLabel = @js($label);

        const radioHistory = {
            url: "{{ route('radio-management.radio.add-history', $radio) }}",
            open() {
                $('#history_action').prop('selectedIndex', 0);
                $('#history_date').val(RadioUi.today()).attr('max', RadioUi.today());
                $('#history_description').val('');
                $('#history_document').val('');
                $('#radioHistoryModal').modal('show');
            },
            submit() {
                RadioUi.send('#radioHistoryButton', {
                    url: radioHistory.url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'PUT',
                        action: $('#history_action').val(),
                        event_date: $('#history_date').val(),
                        description: $('#history_description').val(),
                        document_no: $('#history_document').val(),
                    },
                })?.done(response => {
                    $('#radioHistoryModal').modal('hide');
                    Swal.fire('Berhasil', response.message, 'success').then(() => window.location.reload());
                }).fail(RadioUi.fail);
            },
        };

        function radioDelete() {
            Swal.fire({
                title: 'Hapus radio ini dari register?',
                html: 'Gunakan <b>Arsipkan</b> untuk radio yang pindah site, afkir, atau hilang.<br>Hapus hanya untuk data yang salah input.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="ti ti-trash"></i> Hapus',
                cancelButtonText: '<i class="ti ti-x"></i> Batal',
                reverseButtons: true,
            }).then(result => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "{{ route('radio-management.radio.destroy', $radio) }}",
                    type: 'POST',
                    dataType: 'json',
                    data: { _method: 'DELETE' },
                }).done(response => {
                    Swal.fire('Terhapus', response.message, 'success')
                        .then(() => window.location = "{{ route('radio-management.radio.index') }}");
                }).fail(RadioUi.fail);
            });
        }

        $(function() {
            radioForm.onSaved = () => setTimeout(() => window.location.reload(), 800);
            radioArchive.onDone = () => setTimeout(() => window.location.reload(), 800);
        });
    </script>
@endpush
