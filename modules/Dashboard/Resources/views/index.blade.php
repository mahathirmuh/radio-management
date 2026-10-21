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
        /* Chart roles, in the colours of the design layer. */
        .viz-root {
            --viz-series-1: var(--rm-brand-600);
            --viz-good: var(--rm-success-500);
            --viz-warning: var(--rm-warning-500);
            --viz-critical: var(--rm-error-600);
            --viz-neutral: var(--rm-gray-400);
            --viz-text: var(--rm-gray-900);
            --viz-text-secondary: var(--rm-gray-500);
        }

        /* Horizontal bars: one series, one hue, value at the tip. */
        .viz-bar-row {
            display: grid;
            grid-template-columns: 96px 1fr;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
        }

        .viz-bar-row:hover,
        .viz-bar-row:focus-visible {
            background: var(--rm-gray-50);
            outline: none;
        }

        .viz-bar-row:focus-visible {
            box-shadow: 0 0 0 2px var(--viz-series-1);
        }

        .viz-bar-label {
            font-size: 13px;
            color: var(--rm-gray-700);
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .viz-bar-track {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .viz-bar {
            height: 14px;
            min-width: 2px;
            background: var(--viz-series-1);
            border-radius: 0 4px 4px 0;
        }

        .viz-bar-value {
            font-size: 12px;
            font-weight: 600;
            color: var(--viz-text);
            font-variant-numeric: tabular-nums;
        }

        /* Part-to-whole: a pie beside its list (under it where the box is narrow), a 2px gap in the colour of the card
           between the slices. A slice and its row light up together. */
        .viz-cond-box {
            container: viz-cond / inline-size;
        }

        .viz-cond {
            display: grid;
            grid-template-columns: 148px minmax(0, 1fr);
            align-items: center;
            gap: 24px;
        }

        @container viz-cond (max-width: 459.98px) {
            .viz-cond {
                grid-template-columns: minmax(0, 1fr);
                gap: 12px;
            }

            .viz-pie {
                justify-self: center;
            }
        }

        .viz-pie {
            display: block;
            width: 148px;
            height: 148px;
            overflow: visible;
        }

        .viz-slice > path,
        .viz-slice > circle {
            stroke: #fff;
            stroke-width: 2;
            stroke-linejoin: round;
        }

        .viz-slice {
            cursor: pointer;
            transition: opacity .15s ease-out;
        }

        .viz-pie-empty {
            fill: var(--rm-gray-100);
        }

        /* The share of a large slice, in the ink of the text (dark enough on each of the colours). */
        .viz-pie-label {
            fill: var(--viz-text);
            font-size: 12px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            text-anchor: middle;
            dominant-baseline: central;
            pointer-events: none;
            transition: opacity .15s ease-out;
        }

        .viz-cond.has-hot .viz-slice:not(.is-hot),
        .viz-cond.has-hot .viz-pie-label:not(.is-hot) {
            opacity: .35;
        }

        .viz-cond.has-hot .viz-legend-row.is-hot {
            background: var(--rm-gray-50);
        }

        @media (prefers-reduced-motion: reduce) {
            .viz-slice,
            .viz-pie-label {
                transition: none;
            }
        }

        .viz-legend-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 0;
            box-shadow: inset 0 1px 0 var(--rm-gray-100);
            color: var(--viz-text);
            font-size: 13px;
            text-decoration: none;
            transition: background-color .12s ease-out;
        }

        .viz-icon {
            color: var(--rm-gray-400);
            font-size: 16px;
        }

        .viz-legend-row:hover {
            background: var(--rm-gray-50);
            color: var(--viz-text);
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
            width: 52px;
            text-align: right;
        }

        .viz-go {
            color: var(--rm-gray-400);
            font-size: 14px;
        }
    </style>
@endpush

@section('page-title', 'Dashboard')
@section('content')
    @php
        // The colour of each kind of history entry (as on Riwayat Radio).
        $historyTone = fn (string $action) => [
            'Serah Terima' => 'blue', 'Service' => 'orange', 'Program' => 'purple', 'Verifikasi' => 'green',
            'Ubah Status' => 'yellow', 'Dipulihkan' => 'green', 'Dihapus' => 'red',
        ][$action] ?? 'gray';
        $issueTone = fn (?string $color) => match ($color) {
            'secondary', 'muted', null => 'gray',
            'azure', 'cyan', 'info', 'primary' => 'blue',
            'teal', 'lime', 'success' => 'green',
            'danger', 'pink' => 'red',
            'warning' => 'yellow',
            default => in_array($color, ['blue', 'green', 'yellow', 'orange', 'red', 'purple', 'gray'], true) ? $color : 'gray',
        };
    @endphp
    <div class="viz-root">
        {{-- Title only: the register is in the sidebar, and the figures below lead to it. --}}
        <div class="rm-head">
            <div>
                <h1 class="rm-title">Dashboard Radio</h1>
            </div>
        </div>

        @if ($totalActive === 0 && $totalArchived === 0)
            <div class="rm-card">
                <div class="rm-empty-state">
                    <span class="rm-empty-icon"><i class="ti ti-radio" aria-hidden="true"></i></span>
                    <div class="rm-empty-title">Belum ada data radio</div>
                    <div class="rm-empty-text">Impor register lama dari Excel, atau tambah radio satu per satu di Register Radio.</div>
                    <a href="{{ route('radio-management.import.index') }}" class="btn-rm primary">
                        <i class="ti ti-file-import" aria-hidden="true"></i> Import Excel
                    </a>
                </div>
            </div>
        @else
            <div class="rm-card">
                {{-- KPI row --}}
                <div class="rm-kpis">
                    <a href="{{ $radioUrl }}" class="rm-kpi-link">
                        <x-global.summary-card value="{{ $fmt($totalActive) }}" count="{{ $totalActive }}" title="Radio aktif" color="brand">
                            <x-slot:icon><i class="ti ti-radio"></i></x-slot:icon>
                        </x-global.summary-card>
                    </a>
                    @foreach ($placements as $placement)
                        <a href="{{ $radioUrl }}?placement={{ $placement['value'] }}" class="rm-kpi-link">
                            <x-global.summary-card value="{{ $fmt($placement['count']) }}" count="{{ $placement['count'] }}" title="{{ $placement['label'] }}"
                                color="{{ $placementTiles[$placement['value']]['color'] }}">
                                <x-slot:icon><i class="ti {{ $placementTiles[$placement['value']]['icon'] }}"></i></x-slot:icon>
                            </x-global.summary-card>
                        </a>
                    @endforeach
                    <a href="{{ $radioUrl }}?scope=archived" class="rm-kpi-link">
                        <x-global.summary-card value="{{ $fmt($totalArchived) }}" count="{{ $totalArchived }}" title="Diarsipkan">
                            <x-slot:icon><i class="ti ti-archive"></i></x-slot:icon>
                        </x-global.summary-card>
                    </a>
                </div>

                <div class="rm-dash-split">
                    <div class="d-flex flex-column gap-3 min-w-0">
                    {{-- Kondisi --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">Kondisi radio aktif</h2>
                                <div class="rm-cardbox-sub">{{ $fmt($totalActive) }} radio, menurut kondisi terakhir yang tercatat</div>
                            </div>
                        </header>
                        <div class="rm-cardbox-body viz-cond-box">
                            @php
                                $ariaParts = $conditions->map(fn ($c) => $c['label'] . ' ' . $c['count'])->implode(', ');
                                $byCondition = $conditions->keyBy('value');
                                // The pie: a slice per condition that has radios, clockwise from the top in the order of the list.
                                $pie = \Modules\Dashboard\Services\Pie::slices($conditions->pluck('count', 'value')->all(), 148);
                            @endphp
                            <div class="viz-cond">
                                {{-- For the pointer: each slice leads where its row does. The rows are the way in for the keyboard
                                     and say every number, so the slices are left out of the tab order. --}}
                                <svg class="viz-pie" viewBox="0 0 148 148" role="img" aria-label="Kondisi radio aktif: {{ $ariaParts }}">
                                    @if ($pie === [])
                                        <circle class="viz-pie-empty" cx="74" cy="74" r="73"></circle>
                                    @endif
                                    @foreach ($pie as $slice)
                                        @php($condition = $byCondition[$slice['key']])
                                        <a href="{{ $radioUrl }}?condition={{ $slice['key'] }}" class="viz-slice" tabindex="-1"
                                            data-viz-key="{{ $slice['key'] }}"
                                            data-viz-tip="{{ $condition['label'] }}: {{ $fmt($condition['count']) }} ({{ $pct($condition['count'], $totalActive) }})">
                                            @if ($slice['full'])
                                                <circle cx="74" cy="74" r="73" style="fill: var({{ $conditionStyle[$slice['key']]['var'] }});"></circle>
                                            @else
                                                <path d="{{ $slice['path'] }}" style="fill: var({{ $conditionStyle[$slice['key']]['var'] }});"></path>
                                            @endif
                                        </a>
                                    @endforeach
                                    {{-- The share written on the slices that are large enough to hold it. --}}
                                    @foreach ($pie as $slice)
                                        @if ($slice['share'] >= 0.1)
                                            <text class="viz-pie-label" data-viz-key="{{ $slice['key'] }}" x="{{ $slice['label_x'] }}"
                                                y="{{ $slice['label_y'] }}">{{ $pct($slice['count'], $totalActive) }}</text>
                                        @endif
                                    @endforeach
                                </svg>
                            <div>
                                @foreach ($conditions as $condition)
                                    <a href="{{ $radioUrl }}?condition={{ $condition['value'] }}" class="viz-legend-row"
                                        data-viz-key="{{ $condition['value'] }}" aria-label="Lihat radio {{ $condition['label'] }}">
                                        <span class="viz-key" style="background: var({{ $conditionStyle[$condition['value']]['var'] }});"></span>
                                        <i class="ti {{ $conditionStyle[$condition['value']]['icon'] }} viz-icon" aria-hidden="true"></i>
                                        <span class="flex-fill">{{ $condition['label'] }}</span>
                                        <span class="viz-num">{{ $fmt($condition['count']) }}</span>
                                        <span class="viz-pct">{{ $pct($condition['count'], $totalActive) }}</span>
                                        <i class="ti ti-chevron-right viz-go" aria-hidden="true"></i>
                                    </a>
                                @endforeach
                            </div>
                            </div>
                        </div>
                    </section>

                    {{-- The next free Radio IDs --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">Radio ID bebas berikutnya</h2>
                                <div class="rm-cardbox-sub">ID yang MDC Code-nya mengandung F otomatis dilewati</div>
                            </div>
                        </header>
                        <div class="rm-cardbox-body d-flex flex-wrap gap-2">
                            @foreach ($nextIds as $id)
                                <span class="rm-dash-id">{{ $id }}<span> · MDC {{ strtoupper(dechex($id)) }}</span></span>
                            @endforeach
                        </div>
                    </section>
                    </div>

                    {{-- Perlu tindakan --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">Perlu tindakan</h2>
                                <div class="rm-cardbox-sub">Hasil cek otomatis di kolom "Cek" pada Register Radio</div>
                            </div>
                            @if (\Modules\SharePointDocs\Services\MenuAccess::allows(auth()->user(), 'radio-management.attention.index'))
                                <a href="{{ route('radio-management.attention.index') }}" class="rm-cardbox-link">Buka inbox <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                            @endif
                        </header>
                        <div class="rm-cardbox-body">
                            @if ($issues->isEmpty())
                                <div class="rm-note"><i class="ti ti-circle-check text-green" aria-hidden="true"></i><span>Tidak ada
                                        masalah yang terdeteksi.</span></div>
                            @else
                                <div class="rm-attn">
                                    @foreach ($issues as $issue)
                                        <div class="rm-attn-row">
                                            <span class="flex-fill"><span class="rm-badge {{ $issueTone($issue['color']) }}">{{ $issue['label'] }}</span></span>
                                            <span class="rm-attn-count">{{ $fmt($issue['count']) }}</span>
                                            <a href="{{ $radioUrl }}?issue={{ $issue['code'] }}{{ in_array($issue['code'], ['needs_review', 'sn_duplicate', 'sn_empty', 'sn_format'], true) ? '&scope=all' : '' }}"
                                                class="rm-attn-link">Lihat</a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <div class="rm-dash-pair">
                    {{-- Per company --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">Radio aktif per company pengguna</h2>
                                <div class="rm-cardbox-sub">Jumlah unit, diurutkan dari terbanyak</div>
                            </div>
                        </header>
                        <div class="rm-cardbox-body" role="list">
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
                    </section>

                    {{-- Per tipe --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">Radio aktif per tipe</h2>
                                <div class="rm-cardbox-sub">MotoTrbo XiR · jumlah unit</div>
                            </div>
                        </header>
                        <div class="rm-cardbox-body" role="list">
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
                    </section>
                </div>

                {{-- Recent activity --}}
                <section class="rm-cardbox rm-cardbox-flush">
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title">Aktivitas terbaru</h2>
                            <div class="rm-cardbox-sub">Riwayat terbaru menurut tanggal kejadian (tanpa catatan impor)</div>
                        </div>
                        <a href="{{ route('radio-management.history.index') }}" class="rm-cardbox-link">Semua riwayat <i
                                class="ti ti-arrow-right"></i></a>
                    </header>
                    <div>
                        @if ($recentHistory->isEmpty())
                            <div class="rm-cardbox-body radio-muted">Belum ada aktivitas.</div>
                        @else
                            <div class="table-responsive">
                                <table class="rm-table">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis</th>
                                            <th>Radio</th>
                                            <th>Keterangan</th>
                                            <th class="text-end">Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentHistory as $history)
                                            <tr>
                                                <td class="text-nowrap">{{ $history->event_date->format('d-m-Y') }}</td>
                                                <td class="text-nowrap"><span class="rm-badge {{ $historyTone($history->action) }}">{{ $history->action }}</span></td>
                                                <td class="text-nowrap">
                                                    @if ($history->radio && !$history->radio->trashed())
                                                        <a href="{{ route('radio-management.radio.detail', $history->radio) }}" class="fw-medium">{{ $history->radio->display_name }}</a>
                                                    @else
                                                        {{ $history->radio?->display_name ?? '-' }}
                                                    @endif
                                                </td>
                                                <td class="rm-dash-text">{{ \Illuminate\Support\Str::limit($history->description, 140) }}</td>
                                                <td class="text-nowrap text-end radio-muted">{{ $history->user?->name ?? 'Sistem' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        @endif
    </div>
@endsection

@push('javascript')
    <script>
        // Hover + keyboard-focus tooltips for every chart mark (values are also printed beside them); the numbers of the
        // tiles count up (the page has them right already: without the script they are simply there).
        $(function() {
            $('.rm-kpi-value[data-count]').each(function() {
                rmMotion.countUp(this, Number(this.getAttribute('data-count')));
            });
            $('[data-viz-tip]').each(function() {
                $(this).attr('data-bs-title', $(this).attr('data-viz-tip'))
                    .tooltip({ trigger: 'hover focus', placement: 'top' });
            });
            // A slice and its row light up together, under the pointer or (a row) in focus.
            const conditionChart = $('.viz-cond');
            const lightUp = key => {
                conditionChart.toggleClass('has-hot', key !== null);
                conditionChart.find('[data-viz-key]').each(function() {
                    $(this).toggleClass('is-hot', this.getAttribute('data-viz-key') === key);
                });
            };
            conditionChart.on('mouseenter focusin', '[data-viz-key]', function() {
                lightUp(this.getAttribute('data-viz-key'));
            });
            conditionChart.on('mouseleave focusout', '[data-viz-key]', () => lightUp(null));
        });
    </script>
@endpush
