@extends('main.index')
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
    $pct = fn ($n, $total) => $total > 0 ? number_format($n / $total * 100, 1, ',', '.') . '%' : '0%';
    $radioUrl = route('radio-management.radio.index');

    $placementTiles = [
        'di_user' => ['color' => 'blue', 'icon' => 'ti-user-check'],
        'stok_it' => ['color' => 'green', 'icon' => 'ti-building-warehouse'],
        'service' => ['color' => 'orange', 'icon' => 'ti-tool'],
        'hilang' => ['color' => 'red', 'icon' => 'ti-map-pin-off'],
    ];
    // Kondisi is a status: fixed status colors, always shown with an icon + label.
    $conditionStyle = [
        'layak' => ['var' => '--viz-good', 'icon' => 'ti-circle-check'],
        'perlu_service' => ['var' => '--viz-warning', 'icon' => 'ti-alert-triangle'],
        'rusak' => ['var' => '--viz-critical', 'icon' => 'ti-circle-x'],
        'belum_dicek' => ['var' => '--viz-neutral', 'icon' => 'ti-help-circle'],
    ];
    $companyMax = max($byCompany->max() ?? 0, 1);
    $typeMax = max($byType->max() ?? 0, 1);
@endphp

@include('RadioManagement::partials.scripts')

@push('style')
    <style>
        /* Chart roles. Light values; dark steps follow the app theme attribute. */
        .viz-root {
            --viz-series-1: #2a78d6;
            --viz-good: #0ca30c;
            --viz-warning: #fab219;
            --viz-critical: #d03b3b;
            --viz-neutral: #898781;
            --viz-text: #0b0b0b;
            --viz-text-secondary: #52514e;
        }

        [data-bs-theme="dark"] .viz-root {
            --viz-series-1: #3987e5;
            --viz-text: #ffffff;
            --viz-text-secondary: #c3c2b7;
        }

        .viz-title {
            font-size: .8rem;
            font-weight: 700;
            margin-bottom: .15rem;
            color: var(--viz-text);
        }

        .viz-subtitle {
            font-size: .68rem;
            color: var(--viz-text-secondary);
            margin-bottom: .75rem;
        }

        /* Horizontal bars: one series, one hue, value at the tip. */
        .viz-bar-row {
            display: grid;
            grid-template-columns: 7.5rem 1fr;
            align-items: center;
            gap: .5rem;
            padding: 3px 4px;
            border-radius: 4px;
            text-decoration: none;
            color: inherit;
        }

        .viz-bar-row:hover,
        .viz-bar-row:focus-visible {
            background: rgba(42, 120, 214, .06);
            outline: none;
        }

        .viz-bar-row:focus-visible {
            box-shadow: 0 0 0 2px var(--viz-series-1);
        }

        .viz-bar-label {
            font-size: .72rem;
            color: var(--viz-text-secondary);
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .viz-bar-track {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }

        .viz-bar {
            height: 14px;
            min-width: 2px;
            background: var(--viz-series-1);
            border-radius: 0 4px 4px 0;
        }

        .viz-bar-row:hover .viz-bar,
        .viz-bar-row:focus-visible .viz-bar {
            filter: brightness(1.15);
        }

        .viz-bar-value {
            font-size: .72rem;
            font-weight: 600;
            color: var(--viz-text);
        }

        /* Part-to-whole: one stacked bar, 2px surface gaps between segments. */
        .viz-stack {
            display: flex;
            gap: 2px;
            height: 20px;
        }

        .viz-seg {
            min-width: 4px;
        }

        .viz-seg:first-child {
            border-radius: 4px 0 0 4px;
        }

        .viz-seg:last-child {
            border-radius: 0 4px 4px 0;
        }

        .viz-seg:hover,
        .viz-seg:focus-visible {
            filter: brightness(1.15);
            outline: none;
        }

        .viz-legend-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .3rem 0;
            border-bottom: 1px solid var(--tblr-border-color);
            font-size: .75rem;
            color: var(--viz-text);
        }

        .viz-legend-row:last-child {
            border-bottom: 0;
        }

        .viz-key {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex: none;
        }

        .viz-num {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }

        .viz-pct {
            font-variant-numeric: tabular-nums;
            color: var(--viz-text-secondary);
            width: 3.5rem;
            text-align: right;
        }
    </style>
@endpush

@section('page-title', 'Dashboard')
@section('content')
    <div class="row g-3 viz-root">
        {{-- Title only: the register is in the sidebar, and the figures below lead to it. --}}
        <div class="col-12">
            <h2 class="mb-0">Dashboard Radio</h2>
            <div class="text-secondary small">Register radio HT & rig · data per {{ now()->format('d-m-Y H:i') }}</div>
        </div>

        @if ($totalActive === 0 && $totalArchived === 0)
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="empty py-4">
                            <div class="empty-icon"><i class="ti ti-radio fs-1"></i></div>
                            <p class="empty-title">Belum ada data radio</p>
                            <p class="empty-subtitle text-secondary">Impor register lama dari Excel, atau tambah radio satu
                                per satu di Register Radio.</p>
                            <div class="empty-action">
                                <x-global.btn-detail label="Import Excel" color="blue" size="sm"
                                    href="{{ route('radio-management.import.index') }}">
                                    <x-slot:icon>
                                        <i class="ti ti-file-import"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- KPI row --}}
            <div class="col-12">
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ $radioUrl }}" class="text-reset text-decoration-none">
                            <x-global.summary-card value="{{ $fmt($totalActive) }}" title="Radio aktif" color="brand">
                                <x-slot:icon><i class="ti ti-radio fs-2"></i></x-slot:icon>
                            </x-global.summary-card>
                        </a>
                    </div>
                    @foreach ($placements as $placement)
                        <div class="col-6 col-md-4 col-xl-2">
                            <a href="{{ $radioUrl }}?placement={{ $placement['value'] }}" class="text-reset text-decoration-none">
                                <x-global.summary-card value="{{ $fmt($placement['count']) }}" title="{{ $placement['label'] }}"
                                    color="{{ $placementTiles[$placement['value']]['color'] }}">
                                    <x-slot:icon><i class="ti {{ $placementTiles[$placement['value']]['icon'] }} fs-2"></i></x-slot:icon>
                                </x-global.summary-card>
                            </a>
                        </div>
                    @endforeach
                    <div class="col-6 col-md-4 col-xl-2">
                        <a href="{{ $radioUrl }}?scope=archived" class="text-reset text-decoration-none">
                            <x-global.summary-card value="{{ $fmt($totalArchived) }}" title="Diarsipkan">
                                <x-slot:icon><i class="ti ti-archive fs-2"></i></x-slot:icon>
                            </x-global.summary-card>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Kondisi --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="viz-title">Kondisi radio aktif</div>
                        <div class="viz-subtitle">{{ $fmt($totalActive) }} radio, menurut kondisi terakhir yang tercatat</div>
                        @php
                            $ariaParts = $conditions->map(fn ($c) => $c['label'] . ' ' . $c['count'])->implode(', ');
                        @endphp
                        <div class="viz-stack mb-3" role="img" aria-label="Kondisi radio aktif: {{ $ariaParts }}">
                            @foreach ($conditions as $condition)
                                @if ($condition['count'] > 0)
                                    <div class="viz-seg" tabindex="0"
                                        style="flex-grow: {{ $condition['count'] }}; background: var({{ $conditionStyle[$condition['value']]['var'] }});"
                                        data-viz-tip="{{ $condition['label'] }}: {{ $fmt($condition['count']) }} ({{ $pct($condition['count'], $totalActive) }})">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <div>
                            @foreach ($conditions as $condition)
                                <div class="viz-legend-row">
                                    <span class="viz-key" style="background: var({{ $conditionStyle[$condition['value']]['var'] }});"></span>
                                    <i class="ti {{ $conditionStyle[$condition['value']]['icon'] }} text-secondary"></i>
                                    <span>{{ $condition['label'] }}</span>
                                    <span class="ms-auto viz-num">{{ $fmt($condition['count']) }}</span>
                                    <span class="viz-pct">{{ $pct($condition['count'], $totalActive) }}</span>
                                    <a href="{{ $radioUrl }}?condition={{ $condition['value'] }}" class="small"
                                        aria-label="Lihat radio {{ $condition['label'] }}"><i class="ti ti-chevron-right"></i></a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Perlu tindakan --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="viz-title">Perlu tindakan</div>
                        <div class="viz-subtitle">Hasil cek otomatis di kolom "Cek" pada Register Radio</div>
                        @if ($issues->isEmpty())
                            <div class="text-secondary small"><i class="ti ti-circle-check text-green"></i> Tidak ada masalah
                                yang terdeteksi.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-vcenter mb-0">
                                    <tbody>
                                        @foreach ($issues as $issue)
                                            <tr>
                                                <td><span class="badge bg-{{ $issue['color'] }}-lt radio-badge"
                                                        style="font-size: .7rem">{{ $issue['label'] }}</span></td>
                                                <td class="text-end viz-num" style="width: 5rem">{{ $fmt($issue['count']) }}</td>
                                                <td class="text-end" style="width: 5rem">
                                                    <a href="{{ $radioUrl }}?issue={{ $issue['code'] }}{{ in_array($issue['code'], ['needs_review', 'sn_duplicate', 'sn_empty', 'sn_format'], true) ? '&scope=all' : '' }}"
                                                        class="small">Lihat <i class="ti ti-arrow-right"></i></a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Per company --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="viz-title">Radio aktif per company pengguna</div>
                        <div class="viz-subtitle">Jumlah unit, diurutkan dari terbanyak</div>
                        <div role="list">
                            @foreach ($byCompany as $company => $count)
                                <div class="viz-bar-row" role="listitem" tabindex="0"
                                    data-viz-tip="{{ $company }}: {{ $fmt($count) }} radio ({{ $pct($count, $totalActive) }})">
                                    <div class="viz-bar-label">{{ $company }}</div>
                                    <div class="viz-bar-track">
                                        <div class="viz-bar" style="width: calc((100% - 3rem) * {{ $count / $companyMax }})"></div>
                                        <span class="viz-bar-value">{{ $fmt($count) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="row g-3">
                    {{-- Per tipe --}}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="viz-title">Radio aktif per tipe</div>
                                <div class="viz-subtitle">MotoTrbo XiR · jumlah unit</div>
                                <div role="list">
                                    @foreach ($byType as $type => $count)
                                        <div class="viz-bar-row" role="listitem" tabindex="0"
                                            data-viz-tip="{{ $type }}: {{ $fmt($count) }} radio ({{ $pct($count, $totalActive) }})">
                                            <div class="viz-bar-label">{{ $type }}</div>
                                            <div class="viz-bar-track">
                                                <div class="viz-bar" style="width: calc((100% - 3rem) * {{ $count / $typeMax }})"></div>
                                                <span class="viz-bar-value">{{ $fmt($count) }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Next free IDs --}}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="viz-title">Radio ID bebas berikutnya</div>
                                <div class="viz-subtitle">ID yang heksadesimalnya mengandung F otomatis dilewati</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($nextIds as $id)
                                        <span class="badge bg-blue-lt py-2 px-2" style="font-size: .75rem">
                                            {{ $id }} <span class="fw-normal text-secondary">· MDC {{ strtoupper(dechex($id)) }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent activity --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="viz-title">Aktivitas terbaru</div>
                                <div class="viz-subtitle">Riwayat terbaru menurut tanggal kejadian (tanpa catatan impor)</div>
                            </div>
                            <a href="{{ route('radio-management.history.index') }}" class="small">Semua riwayat <i
                                    class="ti ti-arrow-right"></i></a>
                        </div>
                        @if ($recentHistory->isEmpty())
                            <div class="text-secondary small">Belum ada aktivitas.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-vcenter mb-0">
                                    <tbody>
                                        @foreach ($recentHistory as $history)
                                            <tr>
                                                <td class="text-nowrap text-secondary small" style="width: 6rem">
                                                    {{ $history->event_date->format('d-m-Y') }}</td>
                                                <td class="text-nowrap" style="width: 7rem"><span
                                                        class="badge bg-azure-lt">{{ $history->action }}</span></td>
                                                <td class="text-nowrap" style="width: 14rem">
                                                    @if ($history->radio && !$history->radio->trashed())
                                                        <a href="{{ route('radio-management.radio.detail', $history->radio) }}">{{ $history->radio->display_name }}</a>
                                                    @else
                                                        {{ $history->radio?->display_name ?? '-' }}
                                                    @endif
                                                </td>
                                                <td class="small text-wrap">{{ \Illuminate\Support\Str::limit($history->description, 140) }}</td>
                                                <td class="text-nowrap text-secondary small text-end">{{ $history->user?->name ?? 'Sistem' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('javascript')
    <script>
        // Hover + keyboard-focus tooltips for every chart mark (values are also printed beside them).
        $(function() {
            $('[data-viz-tip]').each(function() {
                $(this).attr('data-bs-title', $(this).attr('data-viz-tip'))
                    .tooltip({ trigger: 'hover focus', placement: 'top' });
            });
        });
    </script>
@endpush
