@extends('main.index')

@section('page-title', __('SnipeIt::snipe.title') . ' · ' . __('SnipeIt::snipe.asset.page_title'))
@section('crumb', $asset['tag'] ?? '#' . $id)

@push('style')
    <style>
        .snipe-detail {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* The picture of the model over its icon; without one (or when it cannot be loaded) the icon. */
        .snipe-tile {
            position: relative;
            overflow: hidden;
        }

        .snipe-tile img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            padding: 6px;
            background: #fff;
            object-fit: contain;
        }

        .snipe-hero-name {
            margin-left: 8px;
            color: var(--rm-gray-500);
            font-size: 16px;
            font-weight: 500;
        }

        .snipe-mono {
            font-family: var(--rm-mono);
        }

        .snipe-muted {
            color: var(--rm-gray-400);
        }

        .snipe-sub {
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
        }

        .snipe-text {
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .snipe-link {
            color: var(--rm-brand-700);
            font-weight: 600;
            text-decoration: none;
        }

        .snipe-link:hover {
            color: var(--rm-brand-600);
        }

        .snipe-list {
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0 16px 16px;
            list-style: none;
        }

        .snipe-list > li + li {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--rm-gray-100);
        }

        .snipe-list-title {
            color: var(--rm-gray-900);
            font-size: 14px;
            line-height: 20px;
            font-weight: 600;
        }

        .snipe-more {
            margin-top: 4px;
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
        }

        .snipe-detail .rm-change-old:empty::before,
        .snipe-detail .rm-change-new:empty::before {
            content: "—";
        }
    </style>
@endpush

@section('content')
    @if ($error)
        <div class="rm-alert warning mb-3" role="alert" id="snipeError">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($asset)
        <div class="snipe-detail">
            <section class="rm-hero">
                <span class="rm-hero-tile snipe-tile"><i class="ti ti-{{ $asset['icon'] }}" aria-hidden="true"></i>
                    @if ($asset['image'])
                        <img src="{{ $asset['image'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">
                    @endif
                </span>
                <div class="rm-hero-main">
                    <div class="rm-hero-kicker">{{ __('SnipeIt::snipe.asset.kicker') }}@if ($asset['category']) · {{ $asset['category'] }}@endif</div>
                    <div class="rm-hero-id"><span>{{ $asset['tag'] }}</span>@if ($asset['name'])<span class="snipe-hero-name">{{ $asset['name'] }}</span>@endif</div>
                    <div class="rm-hero-meta">
                        @if ($asset['serial'])
                            <span class="snipe-mono">SN {{ $asset['serial'] }}</span>
                        @endif
                        @if ($asset['model'])
                            <span>{{ $asset['model'] }}</span>
                        @endif
                        @if ($asset['status']['name'])
                            <span class="rm-badge {{ $asset['status']['tone'] }}">{{ $asset['status']['name'] }}</span>
                        @endif
                        @if ($asset['deleted'])
                            <span class="rm-badge red">{{ __('SnipeIt::snipe.asset.deleted', ['date' => $asset['deleted']]) }}</span>
                        @endif
                    </div>
                </div>
                <div class="rm-hero-actions">
                    <a class="btn-rm secondary is-icon" id="snipeRefresh" href="{{ route('snipe-it.assets.show', ['asset' => $asset['id'], 'fresh' => 1]) }}"
                        data-bs-toggle="tooltip" data-bs-title="{{ __('SnipeIt::snipe.refresh') }}" aria-label="{{ __('SnipeIt::snipe.refresh') }}"><i
                            class="ti ti-refresh" aria-hidden="true"></i></a>
                    @if ($asset['web'])
                        <a class="btn-rm secondary" href="{{ $asset['web'] }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link"
                                aria-hidden="true"></i> {{ __('SnipeIt::snipe.open_row') }}</a>
                    @endif
                </div>
            </section>

            <div class="rm-info-grid">
                <section class="rm-cardbox" aria-labelledby="snipeFactsDetails">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeFactsDetails">{{ __('SnipeIt::snipe.asset.sections.details') }}</h2>
                    </header>
                    @include('SnipeIt::partials.facts', ['facts' => $asset['facts']['details']])
                </section>
                <section class="rm-cardbox" aria-labelledby="snipeFactsHolder">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeFactsHolder">{{ __('SnipeIt::snipe.asset.sections.holder') }}</h2>
                    </header>
                    @include('SnipeIt::partials.facts', ['facts' => $asset['facts']['holder']])
                </section>
                <section class="rm-cardbox" aria-labelledby="snipeFactsPurchase">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeFactsPurchase">{{ __('SnipeIt::snipe.asset.sections.purchase') }}</h2>
                    </header>
                    @include('SnipeIt::partials.facts', ['facts' => $asset['facts']['purchase']])
                </section>
            </div>

            <div class="rm-detail-lower">
                <section class="rm-cardbox" aria-labelledby="snipeHistory">
                    <header class="rm-cardbox-head">
                        <div>
                            <h2 class="rm-cardbox-title" id="snipeHistory">{{ __('SnipeIt::snipe.asset.sections.history') }}</h2>
                            <div class="rm-cardbox-sub">{{ trans_choice('SnipeIt::snipe.asset.history_sub', $asset['history']['total'], ['count' => \App\Services\Language::number($asset['history']['total'])]) }}</div>
                        </div>
                    </header>
                    <div class="rm-cardbox-body">
                        @if ($asset['history']['items'] === [])
                            <div class="snipe-muted">{{ __('SnipeIt::snipe.asset.no_history') }}</div>
                        @else
                            <ol class="rm-timeline" id="snipeTimeline">
                                @foreach ($asset['history']['items'] as $event)
                                    <li class="rm-timeline-item">
                                        <span @class(['rm-timeline-dot', 'tone-' . $event['tone'] => $event['tone'] !== ''])><i class="ti ti-{{ $event['icon'] }}" aria-hidden="true"></i></span>
                                        <div class="rm-timeline-content">
                                            <div class="rm-timeline-title">
                                                <span>{{ $event['action'] }}</span>
                                                @if ($event['toward'])
                                                    <span class="snipe-sub">{{ $event['toward'] }}@if ($event['toward_kind']) ({{ $event['toward_kind'] }})@endif</span>
                                                @endif
                                            </div>
                                            <div class="rm-timeline-meta">{{ $event['at'] ?? '—' }}@if ($event['by']) · {{ __('SnipeIt::snipe.asset.by', ['name' => $event['by']]) }}@endif</div>
                                            @if ($event['changes'])
                                                <div class="rm-changes">
                                                    @foreach ($event['changes'] as $change)
                                                        <div class="rm-change">
                                                            <span class="rm-change-field">{{ $change['field'] }}</span>
                                                            <span class="rm-change-old">{{ $change['old'] }}</span>
                                                            <i class="ti ti-arrow-right" aria-hidden="true"></i>
                                                            <span class="rm-change-new">{{ $change['new'] }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($event['note'])
                                                <div class="rm-timeline-text snipe-text">{{ $event['note'] }}</div>
                                            @endif
                                            @if ($event['file'])
                                                <div class="rm-timeline-text"><i class="ti ti-paperclip" aria-hidden="true"></i> {{ $event['file'] }}</div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                            @if ($asset['history']['total'] > count($asset['history']['items']))
                                <div class="snipe-more">{{ __('SnipeIt::snipe.asset.history_more', ['shown' => count($asset['history']['items']), 'total' => \App\Services\Language::number($asset['history']['total'])]) }}</div>
                            @endif
                        @endif
                    </div>
                </section>

                <div class="d-flex flex-column gap-3 min-w-0">
                    @if ($asset['notes'])
                        <section class="rm-cardbox" aria-labelledby="snipeNotes">
                            <header class="rm-cardbox-head">
                                <h2 class="rm-cardbox-title" id="snipeNotes">{{ __('SnipeIt::snipe.asset.sections.notes') }}</h2>
                            </header>
                            <div class="rm-cardbox-body snipe-text">{{ $asset['notes'] }}</div>
                        </section>
                    @endif

                    <section class="rm-cardbox" aria-labelledby="snipeCustom">
                        <header class="rm-cardbox-head">
                            <h2 class="rm-cardbox-title" id="snipeCustom">{{ __('SnipeIt::snipe.asset.sections.custom') }}</h2>
                        </header>
                        @if ($asset['custom'] === [])
                            <div class="rm-cardbox-body snipe-muted">{{ __('SnipeIt::snipe.asset.no_custom') }}</div>
                        @else
                            <dl class="rm-facts">
                                @foreach ($asset['custom'] as $field)
                                    <div>
                                        <dt>{{ $field['name'] }}</dt>
                                        <dd>
                                            @if ($field['hidden'])
                                                <span class="snipe-muted"><i class="ti ti-lock" aria-hidden="true"></i> {{ __('SnipeIt::snipe.asset.hidden') }}</span>
                                            @elseif ($field['value'] === null)
                                                <span class="snipe-muted">—</span>
                                            @else
                                                {{ $field['value'] }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </section>

                    <section class="rm-cardbox" aria-labelledby="snipeMaintenances">
                        <header class="rm-cardbox-head">
                            <h2 class="rm-cardbox-title" id="snipeMaintenances">{{ __('SnipeIt::snipe.asset.sections.maintenances') }}</h2>
                        </header>
                        @if ($asset['maintenances'] === [])
                            <div class="rm-cardbox-body snipe-muted">{{ __('SnipeIt::snipe.asset.no_maintenances') }}</div>
                        @else
                            <ul class="snipe-list">
                                @foreach ($asset['maintenances'] as $maintenance)
                                    <li>
                                        <div class="snipe-list-title">{{ $maintenance['title'] }}</div>
                                        <div class="snipe-sub">
                                            {{ implode(' · ', array_filter([$maintenance['kind'], trim(($maintenance['start'] ?? '') . ($maintenance['end'] ? ' – ' . $maintenance['end'] : '')) ?: null, $maintenance['supplier'],
                                                $maintenance['cost'] ? __('SnipeIt::snipe.asset.cost', ['cost' => $maintenance['cost']]) : null, $maintenance['warranty'] ? __('SnipeIt::snipe.asset.warranty') : null])) }}
                                        </div>
                                        @if ($maintenance['notes'])
                                            <div class="snipe-text mt-1">{{ $maintenance['notes'] }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    @if ($asset['licenses'])
                        <section class="rm-cardbox" aria-labelledby="snipeLicenses">
                            <header class="rm-cardbox-head">
                                <h2 class="rm-cardbox-title" id="snipeLicenses">{{ __('SnipeIt::snipe.asset.sections.licenses') }}</h2>
                            </header>
                            <ul class="snipe-list">
                                @foreach ($asset['licenses'] as $license)
                                    <li>
                                        @if ($license['link'])
                                            <a class="snipe-link" href="{{ $license['link'] }}" target="_blank" rel="noopener noreferrer">{{ $license['name'] }}</a>
                                        @else
                                            <span class="snipe-list-title">{{ $license['name'] }}</span>
                                        @endif
                                        <div class="snipe-sub">{{ implode(' · ', array_filter([$license['category'], $license['expires']])) }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($asset['attached'])
                        <section class="rm-cardbox" aria-labelledby="snipeAttached">
                            <header class="rm-cardbox-head">
                                <h2 class="rm-cardbox-title" id="snipeAttached">{{ __('SnipeIt::snipe.asset.sections.attached') }}</h2>
                            </header>
                            <ul class="snipe-list">
                                @foreach ($asset['attached'] as $attached)
                                    <li>
                                        @if ($attached['link'])
                                            <a class="snipe-link" href="{{ $attached['link'] }}">{{ $attached['tag'] }}</a>
                                        @else
                                            <span class="snipe-list-title">{{ $attached['tag'] }}</span>
                                        @endif
                                        @if ($attached['name'])
                                            <div class="snipe-sub">{{ $attached['name'] }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <section class="rm-cardbox" aria-labelledby="snipeDates">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title" id="snipeDates">{{ __('SnipeIt::snipe.asset.sections.dates') }}</h2>
                                <div class="rm-cardbox-sub">{{ __('SnipeIt::snipe.asset.fetched', ['at' => $asset['at']]) }}</div>
                            </div>
                        </header>
                        @include('SnipeIt::partials.facts', ['facts' => $asset['facts']['dates']])
                    </section>
                </div>
            </div>
        </div>
    @endif
@endsection
