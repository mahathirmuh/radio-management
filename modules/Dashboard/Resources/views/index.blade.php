@extends('main.index')
@php
    // Numbers as the language of the page writes them (1.011 / 1,011).
    $fmt = fn ($n) => \App\Services\Language::number((int) $n);
    $pct = fn ($n, $total) => $total > 0 ? \App\Services\Language::number($n / $total * 100, 1) . '%' : '0%';
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
    $typeMax = max($byType->max() ?? 0, 1);
    // Per company as a ring: the five largest companies, each in a categorical colour in a fixed order, the radios without
    // a company in light grey where their number puts them, and the rest of the companies together in grey at the end.
    $noCompany = __('Dashboard::dashboard.no_name');
    $companyNamed = $byCompany->except([$noCompany]);
    $companyTop = $companyNamed->take(5)->keys()->values();
    $companyRest = $companyNamed->slice(5);
    $companyEntries = collect();
    foreach ($byCompany as $company => $count) {
        $slot = $companyTop->search($company);
        if ($company === $noCompany || $slot !== false) {
            $companyEntries->push([
                'key' => $company === $noCompany ? 'none' : 'c' . $slot,
                'label' => $company,
                'count' => (int) $count,
                'color' => $company === $noCompany ? 'var(--viz-empty)' : 'var(--viz-cat-' . ($slot + 1) . ')',
                'names' => null,
            ]);
        }
    }
    if ($companyRest->isNotEmpty()) {
        $companyEntries->push([
            'key' => 'other',
            'label' => __('Dashboard::dashboard.by_company.other'),
            'count' => (int) $companyRest->sum(),
            'color' => 'var(--viz-other)',
            'names' => $companyRest->map(fn ($count, $company) => $company . ' ' . $fmt($count))->implode(' · '),
        ]);
    }
    $companyPie = \Modules\Dashboard\Services\Pie::slices($companyEntries->pluck('count', 'key')->all(), 148);
    $companyTip = fn (array $entry) => $entry['label'] . ': ' . trans_choice('Dashboard::dashboard.radios', $entry['count'], ['count' => $fmt($entry['count'])])
        . ' (' . $pct($entry['count'], $totalActive) . ')';
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
            /* Categorical, in this order (validated on white: adjacent CVD ΔE >= 12.6, normal vision >= 16.3; the orange is
               under 3:1, so every value is also written in the list), and apart from the green of "Layak" (ΔE 15.9).
               Without a company: light grey; the rest of the companies together: grey. */
            --viz-cat-1: var(--rm-brand-600);
            --viz-cat-2: var(--rm-cyan-600);
            --viz-cat-3: var(--rm-violet-500);
            --viz-cat-4: var(--rm-orange-500);
            --viz-cat-5: var(--rm-pink-500);
            --viz-empty: var(--rm-gray-300);
            --viz-other: var(--rm-gray-400);
        }

        /* Columns: one series, one hue, the value above each, the name under one baseline. They fill the height of their
           panel (as high as the one beside it), 220 at least. */
        .viz-cols {
            display: flex;
            flex: 1 0 220px;
        }

        .viz-col {
            display: flex;
            flex: 1 1 0;
            flex-direction: column;
            align-items: center;
            min-width: 0;
            border-radius: 6px;
            outline: none;
        }

        .viz-col:hover,
        .viz-col:focus-visible {
            background: var(--rm-gray-50);
        }

        .viz-col:focus-visible {
            box-shadow: 0 0 0 2px var(--viz-series-1);
        }

        .viz-col-plot {
            display: flex;
            flex: 1;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            width: 100%;
            min-height: 0;
            padding: 8px 7px 0;
            box-shadow: inset 0 -1px 0 var(--rm-gray-200);
        }

        .viz-col-value {
            color: var(--viz-text);
            font-size: 12px;
            line-height: 16px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .viz-col-bar {
            width: min(40px, 100%);
            min-height: 2px;
            border-radius: 4px 4px 0 0;
            background: var(--viz-series-1);
        }

        /* Two lines of room under each column, so that a long name does not lift the baseline of its column. */
        .viz-col-label {
            min-height: 44px;
            padding: 8px 2px 4px;
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
            text-align: center;
            overflow-wrap: anywhere;
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

        .viz-slice.is-static {
            cursor: default;
        }

        .viz-pie-empty {
            fill: var(--rm-gray-100);
        }

        /* A ring: the hole in the colour of the card, the total and its unit in it. */
        .viz-donut-hole {
            fill: #fff;
            pointer-events: none;
        }

        .viz-donut-total,
        .viz-donut-unit {
            text-anchor: middle;
            dominant-baseline: central;
            pointer-events: none;
        }

        .viz-donut-total {
            fill: var(--viz-text);
            font-size: 22px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .viz-donut-unit {
            fill: var(--viz-text-secondary);
            font-size: 12px;
        }

        .viz-cond.has-hot .viz-slice:not(.is-hot) {
            opacity: .35;
        }

        .viz-cond.has-hot .viz-legend-row.is-hot {
            background: var(--rm-gray-50);
        }

        @media (prefers-reduced-motion: reduce) {
            .viz-slice {
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

        .viz-legend-row:focus-visible {
            outline: 2px solid var(--viz-series-1);
            outline-offset: -2px;
        }

        /* The companies in "Lainnya", under its name. */
        .viz-legend-sub {
            display: block;
            color: var(--viz-text-secondary);
            font-size: 12px;
            line-height: 16px;
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
@section('welcome', __('Dashboard::dashboard.welcome'))
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
        @if ($totalActive === 0 && $totalArchived === 0)
            <div class="rm-card">
                <div class="rm-empty-state">
                    <span class="rm-empty-icon"><i class="ti ti-radio" aria-hidden="true"></i></span>
                    <div class="rm-empty-title">{{ __('Dashboard::dashboard.empty.title') }}</div>
                    <div class="rm-empty-text">{{ __('Dashboard::dashboard.empty.text') }}</div>
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
                        <x-global.summary-card value="{{ $fmt($totalActive) }}" count="{{ $totalActive }}" title="{{ __('Dashboard::dashboard.kpi.active') }}" color="brand">
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
                        <x-global.summary-card value="{{ $fmt($totalArchived) }}" count="{{ $totalArchived }}" title="{{ __('Dashboard::dashboard.kpi.archived') }}">
                            <x-slot:icon><i class="ti ti-archive"></i></x-slot:icon>
                        </x-global.summary-card>
                    </a>
                </div>

                <div class="rm-dash-split">
                    <div class="rm-dash-stack d-flex flex-column gap-3 min-w-0">
                    {{-- Kondisi --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">{{ __('Dashboard::dashboard.conditions.title') }}</h2>
                                <div class="rm-cardbox-sub">{{ trans_choice('Dashboard::dashboard.conditions.sub', $totalActive, ['count' => $fmt($totalActive)]) }}</div>
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
                                <svg class="viz-pie" viewBox="0 0 148 148" role="img" aria-label="{{ __('Dashboard::dashboard.conditions.chart', ['parts' => $ariaParts]) }}">
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
                                </svg>
                            <div>
                                @foreach ($conditions as $condition)
                                    <a href="{{ $radioUrl }}?condition={{ $condition['value'] }}" class="viz-legend-row"
                                        data-viz-key="{{ $condition['value'] }}" aria-label="{{ __('Dashboard::dashboard.conditions.show', ['condition' => $condition['label']]) }}">
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
                                <h2 class="rm-cardbox-title">{{ __('Dashboard::dashboard.next_ids.title') }}</h2>
                                <div class="rm-cardbox-sub">{{ __('Dashboard::dashboard.next_ids.sub') }}</div>
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
                                <h2 class="rm-cardbox-title">{{ __('Dashboard::dashboard.attention.title') }}</h2>
                                <div class="rm-cardbox-sub">{{ __('Dashboard::dashboard.attention.sub') }}</div>
                            </div>
                            @if (\Modules\SharePointDocs\Services\MenuAccess::allows(auth()->user(), 'radio-management.attention.index'))
                                <a href="{{ route('radio-management.attention.index') }}" class="rm-cardbox-link">{{ __('Dashboard::dashboard.attention.open') }} <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                            @endif
                        </header>
                        <div class="rm-cardbox-body">
                            @if ($issues->isEmpty())
                                <div class="rm-note"><i class="ti ti-circle-check text-green" aria-hidden="true"></i><span>{{ __('Dashboard::dashboard.attention.none') }}</span></div>
                            @else
                                <div class="rm-attn">
                                    @foreach ($issues as $issue)
                                        <div class="rm-attn-row">
                                            <span class="flex-fill"><span class="rm-badge {{ $issueTone($issue['color']) }}">{{ $issue['label'] }}</span></span>
                                            <span class="rm-attn-count">{{ $fmt($issue['count']) }}</span>
                                            <a href="{{ $radioUrl }}?issue={{ $issue['code'] }}{{ in_array($issue['code'], ['needs_review', 'sn_duplicate', 'sn_empty', 'sn_format'], true) ? '&scope=all' : '' }}"
                                                class="rm-attn-link">{{ __('Dashboard::dashboard.attention.show') }}</a>
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
                                <h2 class="rm-cardbox-title">{{ __('Dashboard::dashboard.by_company.title') }}</h2>
                                <div class="rm-cardbox-sub">{{ __('Dashboard::dashboard.by_company.sub', ['count' => $fmt($totalActive)]) }}</div>
                            </div>
                        </header>
                        <div class="rm-cardbox-body viz-cond-box">
                            <div class="viz-cond">
                                {{-- The slices say their number in a tooltip; the rows say every number, and take the keyboard. --}}
                                <svg class="viz-pie" viewBox="0 0 148 148" role="img"
                                    aria-label="{{ __('Dashboard::dashboard.by_company.chart', ['parts' => $companyEntries->map(fn ($entry) => $entry['label'] . ' ' . $entry['count'])->implode(', ')]) }}">
                                    @if ($companyPie === [])
                                        <circle class="viz-pie-empty" cx="74" cy="74" r="73"></circle>
                                    @endif
                                    @foreach ($companyPie as $slice)
                                        @php($entry = $companyEntries->firstWhere('key', $slice['key']))
                                        <g class="viz-slice is-static" data-viz-key="{{ $slice['key'] }}" data-viz-tip="{{ $companyTip($entry) }}">
                                            @if ($slice['full'])
                                                <circle cx="74" cy="74" r="73" style="fill: {{ $entry['color'] }};"></circle>
                                            @else
                                                <path d="{{ $slice['path'] }}" style="fill: {{ $entry['color'] }};"></path>
                                            @endif
                                        </g>
                                    @endforeach
                                    <circle class="viz-donut-hole" cx="74" cy="74" r="48"></circle>
                                    <text class="viz-donut-total" x="74" y="68">{{ $fmt($totalActive) }}</text>
                                    <text class="viz-donut-unit" x="74" y="90">{{ trans_choice('Dashboard::dashboard.by_company.unit', $totalActive) }}</text>
                                </svg>
                                <div>
                                    @foreach ($companyEntries as $entry)
                                        <div class="viz-legend-row" tabindex="0" data-viz-key="{{ $entry['key'] }}" data-viz-tip="{{ $companyTip($entry) }}">
                                            <span class="viz-key" style="background: {{ $entry['color'] }};"></span>
                                            <span class="flex-fill">{{ $entry['label'] }}@if ($entry['names'])<span class="viz-legend-sub">{{ $entry['names'] }}</span>@endif</span>
                                            <span class="viz-num">{{ $fmt($entry['count']) }}</span>
                                            <span class="viz-pct">{{ $pct($entry['count'], $totalActive) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Per tipe --}}
                    <section class="rm-cardbox">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title">{{ __('Dashboard::dashboard.by_type.title') }}</h2>
                                <div class="rm-cardbox-sub">{{ __('Dashboard::dashboard.by_type.sub') }}</div>
                            </div>
                        </header>
                        <div class="rm-cardbox-body">
                            <div class="viz-cols" role="list">
                                @foreach ($byType as $type => $count)
                                    <div class="viz-col" role="listitem" tabindex="0"
                                        data-viz-tip="{{ $type }}: {{ trans_choice('Dashboard::dashboard.radios', $count, ['count' => $fmt($count)]) }} ({{ $pct($count, $totalActive) }})">
                                        <div class="viz-col-plot">
                                            <span class="viz-col-value">{{ $fmt($count) }}</span>
                                            @if ($count > 0)
                                                <span class="viz-col-bar" style="height: calc((100% - 22px) * {{ $count / $typeMax }})"></span>
                                            @endif
                                        </div>
                                        <span class="viz-col-label">{{ $type }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>

                {{-- Recent activity --}}
                <section class="rm-cardbox rm-cardbox-flush">
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title">{{ __('Dashboard::dashboard.activity.title') }}</h2>
                            <div class="rm-cardbox-sub">{{ __('Dashboard::dashboard.activity.sub') }}</div>
                        </div>
                        <a href="{{ route('radio-management.history.index') }}" class="rm-cardbox-link">{{ __('Dashboard::dashboard.activity.all') }} <i
                                class="ti ti-arrow-right"></i></a>
                    </header>
                    <div>
                        @if ($recentHistory->isEmpty())
                            <div class="rm-cardbox-body radio-muted">{{ __('Dashboard::dashboard.activity.none') }}</div>
                        @else
                            <div class="table-responsive">
                                <table class="rm-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Dashboard::dashboard.activity.date') }}</th>
                                            <th>{{ __('Dashboard::dashboard.activity.kind') }}</th>
                                            <th>{{ __('Dashboard::dashboard.activity.radio') }}</th>
                                            <th>{{ __('Dashboard::dashboard.activity.description') }}</th>
                                            <th class="text-end">{{ __('Dashboard::dashboard.activity.by') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentHistory as $history)
                                            <tr>
                                                <td class="text-nowrap">{{ $history->event_date->format('d-m-Y') }}</td>
                                                <td class="text-nowrap"><span class="rm-badge {{ $historyTone($history->action) }}">{{ \Modules\RadioManagement\Services\RadioWords::of('history_actions', $history->action) }}</span></td>
                                                <td class="text-nowrap">
                                                    @if ($history->radio && !$history->radio->trashed())
                                                        <a href="{{ route('radio-management.radio.detail', $history->radio) }}" class="fw-medium">{{ $history->radio->pageName() }}</a>
                                                    @else
                                                        {{ $history->radio?->pageName() ?? '-' }}
                                                    @endif
                                                </td>
                                                <td class="rm-dash-text">{{ \Illuminate\Support\Str::limit($history->description, 140) }}</td>
                                                <td class="text-nowrap text-end radio-muted">{{ $history->user?->name ?? __('RadioManagement::common.ui.system') }}</td>
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
            // In each pie, a slice and its row light up together, under the pointer or (a row) in focus.
            $('.viz-cond').each(function() {
                const chart = $(this);
                const lightUp = key => {
                    chart.toggleClass('has-hot', key !== null);
                    chart.find('[data-viz-key]').each(function() {
                        $(this).toggleClass('is-hot', this.getAttribute('data-viz-key') === key);
                    });
                };
                chart.on('mouseenter focusin', '[data-viz-key]', function() {
                    lightUp(this.getAttribute('data-viz-key'));
                });
                chart.on('mouseleave focusout', '[data-viz-key]', () => lightUp(null));
            });
        });
    </script>
@endpush
