@extends('main.index')

@section('page-title', __('SnipeIt::snipe.title') . ' · ' . __('SnipeIt::snipe.user.page_title'))
@section('crumb', $user['name'] ?? '#' . $id)

@push('style')
    <style>
        .snipe-detail {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* The initials of the person in place of a picture (no picture of a person leaves Snipe-IT). */
        .snipe-initials {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: .5px;
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

        /* An asset of the user: Snipe-IT's picture over the icon of its category, the tag, what it is, its status. */
        .snipe-asset {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .snipe-asset-main {
            flex: 1;
            min-width: 0;
        }

        .snipe-thumb {
            position: relative;
            display: grid;
            place-items: center;
            flex: none;
            width: 40px;
            height: 40px;
            overflow: hidden;
            border: 1px solid var(--rm-gray-200);
            border-radius: 8px;
            background: var(--rm-gray-50);
            color: var(--rm-gray-500);
            font-size: 18px;
        }

        .snipe-thumb img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            padding: 2px;
            background: #fff;
            object-fit: contain;
        }

        .snipe-asset-side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            flex: none;
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

    @if ($user)
        @php
            $number = fn (int $count) => \App\Services\Language::number($count);
        @endphp
        <div class="snipe-detail">
            <section class="rm-hero">
                <span class="rm-hero-tile snipe-initials" aria-hidden="true">{{ $user['initials'] }}</span>
                <div class="rm-hero-main">
                    <div class="rm-hero-kicker">{{ __('SnipeIt::snipe.user.kicker') }}@if ($user['department']) · {{ $user['department'] }}@endif</div>
                    <div class="rm-hero-id"><span>{{ $user['name'] }}</span></div>
                    <div class="rm-hero-meta">
                        @if ($user['username'])
                            <span class="snipe-mono">{{ $user['username'] }}</span>
                        @endif
                        @if ($user['employee_num'])
                            <span class="snipe-mono">{{ $user['employee_num'] }}</span>
                        @endif
                        @if ($user['jobtitle'])
                            <span>{{ $user['jobtitle'] }}</span>
                        @endif
                        @if ($user['active'] !== null)
                            <span class="rm-badge {{ $user['active'] ? 'green' : 'gray' }}">{{ __('SnipeIt::snipe.active.' . ($user['active'] ? 'yes' : 'no')) }}</span>
                        @endif
                        @if ($user['vip'])
                            <span class="rm-badge purple">{{ __('SnipeIt::snipe.user.facts.vip') }}</span>
                        @endif
                        @if ($user['deleted'])
                            <span class="rm-badge red">{{ __('SnipeIt::snipe.user.deleted', ['date' => $user['deleted']]) }}</span>
                        @endif
                    </div>
                </div>
                <div class="rm-hero-actions">
                    <a class="btn-rm secondary is-icon" id="snipeRefresh" href="{{ route('snipe-it.users.show', ['user' => $user['id'], 'fresh' => 1]) }}"
                        data-bs-toggle="tooltip" data-bs-title="{{ __('SnipeIt::snipe.refresh') }}" aria-label="{{ __('SnipeIt::snipe.refresh') }}"><i
                            class="ti ti-refresh" aria-hidden="true"></i></a>
                    @if ($user['web'])
                        <a class="btn-rm secondary" href="{{ $user['web'] }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link"
                                aria-hidden="true"></i> {{ __('SnipeIt::snipe.open_row') }}</a>
                    @endif
                </div>
            </section>

            <div class="rm-info-grid">
                <section class="rm-cardbox" aria-labelledby="snipeProfile">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeProfile">{{ __('SnipeIt::snipe.user.sections.profile') }}</h2>
                    </header>
                    @include('SnipeIt::partials.facts', ['facts' => $user['facts']['profile']])
                </section>
                <section class="rm-cardbox" aria-labelledby="snipeOrganization">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeOrganization">{{ __('SnipeIt::snipe.user.sections.organization') }}</h2>
                    </header>
                    @include('SnipeIt::partials.facts', ['facts' => $user['facts']['organization']])
                </section>
                <section class="rm-cardbox" aria-labelledby="snipeHolding">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeHolding">{{ __('SnipeIt::snipe.user.sections.holding') }}</h2>
                    </header>
                    <dl class="rm-facts" id="snipeCounts">
                        @foreach ($user['counts'] as $kind => $count)
                            <div>
                                <dt>{{ __('SnipeIt::snipe.user.counts.' . $kind) }}</dt>
                                <dd>
                                    {{ $number($count) }}
                                    @if ($kind === 'consumables' && $count > 0)
                                        <div class="snipe-sub">{{ __('SnipeIt::snipe.user.consumables_note') }}</div>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </div>

            <div class="rm-detail-lower">
                <section class="rm-cardbox" aria-labelledby="snipeAssets">
                    <header class="rm-cardbox-head">
                        <h2 class="rm-cardbox-title" id="snipeAssets">{{ __('SnipeIt::snipe.user.sections.assets') }}</h2>
                        <span class="rm-badge gray">{{ $number(count($user['assets'])) }}</span>
                    </header>
                    @if ($user['assets'] === [])
                        <div class="rm-cardbox-body snipe-muted">{{ __('SnipeIt::snipe.user.no_assets') }}</div>
                    @else
                        <ul class="snipe-list" id="snipeUserAssets">
                            @foreach ($user['assets'] as $asset)
                                <li class="snipe-asset">
                                    <span class="snipe-thumb"><i class="ti ti-{{ $asset['icon'] }}" aria-hidden="true"></i>
                                        @if ($asset['image'])
                                            <img src="{{ $asset['image'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">
                                        @endif
                                    </span>
                                    <div class="snipe-asset-main">
                                        @if ($asset['link'])
                                            <a class="snipe-link" href="{{ $asset['link'] }}">{{ $asset['tag'] }}</a>
                                        @else
                                            <span class="snipe-list-title">{{ $asset['tag'] }}</span>
                                        @endif
                                        <div class="snipe-sub">{{ implode(' · ', array_filter([$asset['name'], $asset['model'], $asset['serial'] ? 'SN ' . $asset['serial'] : null])) ?: '—' }}</div>
                                    </div>
                                    <div class="snipe-asset-side">
                                        @if ($asset['status']['name'])
                                            <span class="rm-badge {{ $asset['status']['tone'] }}">{{ $asset['status']['name'] }}</span>
                                        @endif
                                        @if ($asset['since'])
                                            <span class="snipe-sub">{{ __('SnipeIt::snipe.user.since', ['date' => $asset['since']]) }}</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <div class="d-flex flex-column gap-3 min-w-0">
                    <section class="rm-cardbox" aria-labelledby="snipeLicenses">
                        <header class="rm-cardbox-head">
                            <h2 class="rm-cardbox-title" id="snipeLicenses">{{ __('SnipeIt::snipe.user.sections.licenses') }}</h2>
                        </header>
                        @if ($user['licenses'] === [])
                            <div class="rm-cardbox-body snipe-muted">{{ __('SnipeIt::snipe.user.no_licenses') }}</div>
                        @else
                            <ul class="snipe-list" id="snipeUserLicenses">
                                @foreach ($user['licenses'] as $license)
                                    <li>
                                        @if ($license['link'])
                                            <a class="snipe-link" href="{{ $license['link'] }}" target="_blank" rel="noopener noreferrer">{{ $license['name'] }}</a>
                                        @else
                                            <span class="snipe-list-title">{{ $license['name'] }}</span>
                                        @endif
                                        <div class="snipe-sub">{{ implode(' · ', array_filter([$license['manufacturer'], $license['category'], $license['expires']])) }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="rm-cardbox" aria-labelledby="snipeAccessories">
                        <header class="rm-cardbox-head">
                            <h2 class="rm-cardbox-title" id="snipeAccessories">{{ __('SnipeIt::snipe.user.sections.accessories') }}</h2>
                        </header>
                        @if ($user['accessories'] === [])
                            <div class="rm-cardbox-body snipe-muted">{{ __('SnipeIt::snipe.user.no_accessories') }}</div>
                        @else
                            <ul class="snipe-list" id="snipeUserAccessories">
                                @foreach ($user['accessories'] as $accessory)
                                    <li>
                                        @if ($accessory['link'])
                                            <a class="snipe-link" href="{{ $accessory['link'] }}" target="_blank" rel="noopener noreferrer">{{ $accessory['name'] }}</a>
                                        @else
                                            <span class="snipe-list-title">{{ $accessory['name'] }}</span>
                                        @endif
                                        @if ($accessory['category'])
                                            <div class="snipe-sub">{{ $accessory['category'] }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="rm-cardbox" aria-labelledby="snipeDates">
                        <header class="rm-cardbox-head">
                            <div>
                                <h2 class="rm-cardbox-title" id="snipeDates">{{ __('SnipeIt::snipe.user.sections.dates') }}</h2>
                                <div class="rm-cardbox-sub">{{ __('SnipeIt::snipe.user.fetched', ['at' => $user['at']]) }}</div>
                            </div>
                        </header>
                        @include('SnipeIt::partials.facts', ['facts' => $user['facts']['dates']])
                    </section>
                </div>
            </div>

            <section class="rm-cardbox" aria-labelledby="snipeHistory">
                <header class="rm-cardbox-head">
                    <div>
                        <h2 class="rm-cardbox-title" id="snipeHistory">{{ __('SnipeIt::snipe.user.sections.history') }}</h2>
                        <div class="rm-cardbox-sub">{{ trans_choice('SnipeIt::snipe.user.history_sub', $user['history']['total'], ['count' => $number($user['history']['total'])]) }}</div>
                    </div>
                </header>
                <div class="rm-cardbox-body">
                    @if ($user['history']['items'] === [])
                        <div class="snipe-muted">{{ __('SnipeIt::snipe.user.no_history') }}</div>
                    @else
                        <ol class="rm-timeline" id="snipeTimeline">
                            @foreach ($user['history']['items'] as $event)
                                <li class="rm-timeline-item">
                                    <span @class(['rm-timeline-dot', 'tone-' . $event['tone'] => $event['tone'] !== ''])><i class="ti ti-{{ $event['icon'] }}" aria-hidden="true"></i></span>
                                    <div class="rm-timeline-content">
                                        <div class="rm-timeline-title">
                                            <span>{{ $event['action'] }}</span>
                                            @if ($event['item'])
                                                <span class="snipe-sub">
                                                    @if ($event['link'])
                                                        <a class="snipe-link" href="{{ $event['link'] }}">{{ $event['item'] }}</a>
                                                    @else
                                                        {{ $event['item'] }}
                                                    @endif
                                                    @if ($event['item_kind'])
                                                        ({{ $event['item_kind'] }})
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                        <div class="rm-timeline-meta">{{ $event['at'] ?? '—' }}@if ($event['by']) · {{ __('SnipeIt::snipe.user.by', ['name' => $event['by']]) }}@endif</div>
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
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        @if ($user['history']['total'] > count($user['history']['items']))
                            <div class="snipe-more">{{ __('SnipeIt::snipe.user.history_more', ['shown' => count($user['history']['items']), 'total' => $number($user['history']['total'])]) }}</div>
                        @endif
                    @endif
                </div>
            </section>
        </div>
    @endif
@endsection
