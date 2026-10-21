@extends('main.index')

@php
    $fmt = fn (?int $count) => $count === null ? __('SnipeIt::snipe.overview.unknown') : \App\Services\Language::number($count);
    $assetsUrl = route('snipe-it.assets.index');
    // The colour of a status by its type (as Snipe-IT's own list of statuses has it).
    $tone = fn (string $type) => ['deployable' => 'green', 'pending' => 'yellow', 'undeployable' => 'red', 'archived' => 'gray'][$type] ?? 'gray';
@endphp

@section('page-title', __('SnipeIt::snipe.title') . ' · ' . __('SnipeIt::snipe.groups.overview'))

@push('style')
    <style>
        .snipe-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .snipe-meta {
            color: var(--rm-gray-500);
            font-size: 13px;
            line-height: 20px;
        }

        .snipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .snipe-rows {
            display: flex;
            flex-direction: column;
        }

        .snipe-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 -8px;
            padding: 6px 8px;
            border-radius: 6px;
            color: var(--rm-gray-900);
            font-size: 13px;
            line-height: 20px;
            text-decoration: none;
        }

        a.snipe-row:hover {
            background: var(--rm-gray-50);
            color: var(--rm-gray-900);
        }

        .snipe-row.is-total {
            margin-bottom: 4px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--rm-gray-100);
            border-radius: 6px 6px 0 0;
            font-weight: 600;
        }

        .snipe-name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .snipe-num {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .snipe-more {
            padding-top: 6px;
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
        }
    </style>
@endpush

@section('content')
    <div class="snipe-head">
        <span class="snipe-meta" id="snipeSource">
            @if ($version)
                {{ __('SnipeIt::snipe.overview.version', ['version' => $version]) }}
            @else
                {{ __('SnipeIt::snipe.title') }}
            @endif
            @if ($at)
                · {{ __('SnipeIt::snipe.overview.fetched', ['at' => $at]) }}
            @endif
        </span>
        <span class="d-flex gap-2 ms-auto">
            <a class="btn-rm secondary is-icon" id="snipeRefresh" href="{{ route('snipe-it.overview.index', ['fresh' => 1]) }}"
                data-bs-toggle="tooltip" data-bs-title="{{ __('SnipeIt::snipe.refresh') }}" aria-label="{{ __('SnipeIt::snipe.refresh') }}"><i
                    class="ti ti-refresh" aria-hidden="true"></i></a>
            @if ($web)
                <a class="btn-rm secondary" href="{{ $web }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link"
                        aria-hidden="true"></i> {{ __('SnipeIt::snipe.open') }}</a>
            @endif
        </span>
    </div>

    @if ($error)
        <div class="rm-alert warning mb-3" role="alert" id="snipeError">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    <div class="snipe-grid">
        @if ($statuses)
            <section class="rm-cardbox" aria-labelledby="snipeByStatus">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title" id="snipeByStatus">{{ __('SnipeIt::snipe.overview.by_status') }}</h2>
                    <a class="rm-cardbox-link" href="{{ $assetsUrl }}">{{ __('SnipeIt::snipe.overview.see_all') }}</a>
                </header>
                <div class="rm-cardbox-body snipe-rows">
                    <a class="snipe-row is-total" href="{{ $assetsUrl }}">
                        <span class="snipe-name">{{ __('SnipeIt::snipe.overview.all_assets') }}</span>
                        <span class="snipe-num">{{ $fmt($groups['assets']['assets'] ?? null) }}</span>
                    </a>
                    @foreach ($statuses as $status)
                        <a class="snipe-row" href="{{ $assetsUrl }}?status={{ $status['id'] }}">
                            <span class="snipe-name"><span class="rm-badge {{ $tone($status['type']) }}">{{ $status['name'] }}</span></span>
                            <span class="snipe-num">{{ $fmt($status['assets']) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($categories)
            <section class="rm-cardbox" aria-labelledby="snipeByCategory">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title" id="snipeByCategory">{{ __('SnipeIt::snipe.overview.by_category') }}</h2>
                    <a class="rm-cardbox-link" href="{{ route('snipe-it.catalog.index', ['tab' => 'categories']) }}">{{ __('SnipeIt::snipe.overview.see_all') }}</a>
                </header>
                <div class="rm-cardbox-body snipe-rows">
                    @foreach ($categories as $category)
                        <a class="snipe-row" href="{{ $assetsUrl }}?category={{ $category['id'] }}">
                            <span class="snipe-name">{{ $category['name'] }}</span>
                            <span class="snipe-num">{{ $fmt($category['assets']) }}</span>
                        </a>
                    @endforeach
                    @if ($otherCategories)
                        <div class="snipe-more">{{ trans_choice('SnipeIt::snipe.overview.other_categories', count($otherCategories), ['count' => count($otherCategories)]) }}
                            · {{ $fmt(array_sum(array_column($otherCategories, 'assets'))) }}</div>
                    @endif
                </div>
            </section>
        @endif

        @foreach ($groups as $group => $lists)
            @continue($group === 'assets')
            <section class="rm-cardbox" aria-labelledby="snipeGroup-{{ $group }}">
                <header class="rm-cardbox-head">
                    <h2 class="rm-cardbox-title" id="snipeGroup-{{ $group }}">{{ __('SnipeIt::snipe.groups.' . $group) }}</h2>
                    <a class="rm-cardbox-link" href="{{ route("snipe-it.{$group}.index") }}">{{ __('SnipeIt::snipe.overview.see_all') }}</a>
                </header>
                <div class="rm-cardbox-body snipe-rows">
                    @foreach ($lists as $list => $total)
                        <a class="snipe-row" href="{{ route("snipe-it.{$group}.index", ['tab' => $list]) }}">
                            <span class="snipe-name">{{ __('SnipeIt::snipe.lists.' . $list) }}</span>
                            <span class="snipe-num">{{ $fmt($total) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endsection
