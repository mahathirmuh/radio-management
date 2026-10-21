@extends('main.index')
{{-- Every notification of whoever is signed in, in tabs (all, unread, each category) and grouped by day, as the design's
     NotificationsPage. The look of a row (.rm-notif-*) is the bell's (radio-ui.css). --}}
@section('page-title', __('ui.topbar.notifications'))
@php
    // The colour of each kind (the icon is UserNotification::ICONS'): as the bell has them.
    $notifTones = ['edit_requested' => 'orange', 'edit_decided' => 'blue', 'token_expiring' => 'red', 'register_weekly' => 'gray'];
    // The rows of this page by day: today, yesterday, before (Notifications::page.groups).
    $notifGroups = $ready ? $notifications->getCollection()->groupBy(function ($notification) {
        $at = $notification->created_at;
        if ($at?->isToday()) {
            return 'today';
        }
        return $at?->isYesterday() ? 'yesterday' : 'earlier';
    }) : collect();
    // When it came: "12 menit yang lalu" today, "Kemarin, 16:40", "17 Okt, 08:00" before (with the year when not this one).
    $notifWhen = function ($notification, string $day): string {
        $at = $notification->created_at;
        if ($at === null) {
            return '';
        }
        return match ($day) {
            'today' => $at->diffForHumans(),
            'yesterday' => __('Notifications::page.yesterday_at', ['time' => $at->format('H:i')]),
            default => $at->translatedFormat($at->year === now()->year ? 'j M, H:i' : 'j M Y, H:i'),
        };
    };
    $notifTabName = fn (string $tab) => match ($tab) {
        'all' => __('Notifications::page.all'),
        'unread' => __('ui.bell.unread'),
        default => __('Notifications::page.categories.' . $tab),
    };
    $notifEmpty = match ($show) {
        'all' => 'none',
        'unread' => 'all_read',
        default => 'none_kind',
    };
    // How many rows of this page each day has: the script takes a group away when its last row goes.
    $notifShown = $notifGroups->map->count();
@endphp
@section('content')
    <div class="rm-notif-page">
        <div class="rm-head">
            <div>
                <h1 class="rm-title">{{ __('ui.topbar.notifications') }}</h1>
            </div>
        </div>

        <div class="rm-card">
            <div class="rm-notif-tabsrow">
                <nav class="rm-tabs" aria-label="{{ __('Notifications::page.tabs') }}">
                    @foreach ($tabs as $tab => $count)
                        <a href="{{ route('notifications.index', $tab === 'all' ? [] : ['show' => $tab]) }}" @class(['is-on' => $show === $tab])
                            @if ($show === $tab) aria-current="page" @endif>
                            {{ $notifTabName($tab) }}
                            <span class="rm-tab-count" @if ($tab === 'unread') id="notifPageCount" @endif>{{ $count }}</span>
                        </a>
                    @endforeach
                </nav>
                @if ($ready && $unread > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" id="notifPageReadAll">
                        @csrf
                        @if ($show !== 'all')
                            <input type="hidden" name="show" value="{{ $show }}">
                        @endif
                        <button type="submit" class="btn-rm secondary" id="notifPageReadAllButton">
                            <i class="ti ti-check" aria-hidden="true"></i> {{ __('ui.bell.read_all') }}
                        </button>
                    </form>
                @endif
            </div>

            @if (! $ready)
                <div class="rm-alert warning">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                    <span>{!! __('Notifications::page.not_ready') !!}</span>
                </div>
            @else
                <div class="rm-empty-state @unless ($notifications->isEmpty()) d-none @endunless" id="notifPageEmpty">
                    <span class="rm-empty-icon tone-green" aria-hidden="true"><i class="ti ti-circle-check"></i></span>
                    <div class="rm-empty-title">{{ __('Notifications::page.empty.' . $notifEmpty) }}</div>
                    <div class="rm-empty-text">{{ __('Notifications::page.empty.' . $notifEmpty . '_text') }}</div>
                </div>

                <div class="rm-notif-groups" id="notifPageList">
                    @foreach ($notifGroups as $day => $items)
                        <div class="rm-notif-group" id="notifGroup-{{ $day }}">
                            <div class="rm-notif-group-label">{{ __('Notifications::page.groups.' . $day) }}</div>
                            <div class="rm-notif-box">
                                @foreach ($items as $notification)
                                    <div @class(['rm-notif-item', 'is-unread' => ! $notification->read_at]) id="notif-{{ $notification->id }}">
                                        <a href="{{ route('notifications.open', $notification->id) }}" class="rm-notif-open">
                                            <span class="rm-notif-disc tone-{{ $notifTones[$notification->kind] ?? 'gray' }}" aria-hidden="true"><i
                                                    class="ti {{ $notification->icon() }}"></i></span>
                                            <span class="rm-notif-text">
                                                <span class="rm-notif-line">
                                                    <span class="rm-notif-title">{{ $notification->title }}</span>
                                                    <span class="rm-badge gray">{{ __('Notifications::page.categories.' . $notification->category()) }}</span>
                                                </span>
                                                @if ($notification->body)
                                                    <span class="rm-notif-body">{{ $notification->body }}</span>
                                                @endif
                                                <span class="rm-notif-when d-block" title="{{ $notification->created_at?->format('d-m-Y H:i') }}">{{ $notifWhen($notification, $day) }}</span>
                                            </span>
                                        </a>
                                        @unless ($notification->read_at)
                                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="rm-notif-mark"
                                                data-id="{{ $notification->id }}" data-group="{{ $day }}">
                                                @csrf
                                                @if ($show !== 'all')
                                                    <input type="hidden" name="show" value="{{ $show }}">
                                                @endif
                                                <button type="submit">{{ __('Notifications::page.mark_read') }}</button>
                                                <span class="rm-notif-dot" role="img" aria-label="{{ __('ui.bell.unread') }}"></span>
                                            </form>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($notifications->hasPages())
                    <div class="rm-foot px-0">
                        <span class="rm-foot-text">{{ __('Notifications::page.range', ['from' => $notifications->firstItem(), 'to' => $notifications->lastItem(), 'total' => $notifications->total()]) }}</span>
                        <span class="rm-end">
                            <a class="btn-rm secondary sm {{ $notifications->onFirstPage() ? 'disabled' : '' }}"
                                href="{{ $notifications->previousPageUrl() ?? '#' }}"><i class="ti ti-chevron-left" aria-hidden="true"></i>
                                {{ __('Notifications::page.previous') }}</a>
                            <a class="btn-rm secondary sm {{ $notifications->hasMorePages() ? '' : 'disabled' }}"
                                href="{{ $notifications->nextPageUrl() ?? '#' }}">{{ __('Notifications::page.next') }} <i class="ti ti-chevron-right"
                                    aria-hidden="true"></i></a>
                        </span>
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        // "Mark all as read" and each row's "Mark as read" without leaving the page (the forms still work without the
        // script): the rows lose their mark, the count of the unread follows, and on the tab of the unread ones a row
        // read goes (and its day, and at the end the list).
        const notifPage = {
            url: @json(route('notifications.read-all')),
            show: @json($show),
            /** How many rows of this page each day has (today, yesterday, earlier). */
            shown: @json($notifShown),
            /** Its words in the language of the page (Notifications::page.script). */
            words: @json(__('Notifications::page.script')),

            readAll(event) {
                event.preventDefault();
                const form = $('#notifPageReadAll');
                rmFeedback.busy('#notifPageReadAllButton', true);
                $.ajax({ url: notifPage.url, type: 'POST', dataType: 'json', data: { _token: form.find('input[name="_token"]').val() } })
                    .done(() => {
                        $('#notifPageList .rm-notif-item').removeClass('is-unread');
                        $('#notifPageList .rm-notif-mark').remove();
                        notifPage.unread(0);
                        if (notifPage.show === 'unread') {
                            $('#notifPageList').empty();
                            $('#notifPageEmpty').removeClass('d-none');
                        }
                        rmFeedback.toast(notifPage.words.marked);
                        if (typeof notifBell !== 'undefined') notifBell.load();
                    })
                    .fail(() => {
                        rmFeedback.busy('#notifPageReadAllButton', false);
                        rmFeedback.toast(notifPage.words.failed, 'error');
                    });
            },

            /** "Mark as read" of one row: it stays where it is, read (on the tab of the unread ones it goes). */
            readOne(event) {
                event.preventDefault();
                const form = $(this);
                const id = Number(form.data('id'));
                const day = form.data('group');
                $.ajax({ url: form.attr('action'), type: 'POST', dataType: 'json', data: { _token: form.find('input[name="_token"]').val() } })
                    .done(response => {
                        $(`#notif-${id}`).removeClass('is-unread');
                        form.remove();
                        notifPage.unread(Number(response.unread) || 0);
                        if (notifPage.show === 'unread') {
                            $(`#notif-${id}`).remove();
                            notifPage.shown[day] = (notifPage.shown[day] || 1) - 1;
                            if (notifPage.shown[day] <= 0) $(`#notifGroup-${day}`).remove();
                            if (Object.values(notifPage.shown).every(count => count <= 0)) $('#notifPageEmpty').removeClass('d-none');
                        }
                        if (typeof notifBell !== 'undefined') notifBell.load();
                    })
                    .fail(() => rmFeedback.toast(notifPage.words.failed_one, 'error'));
            },

            /** The count on the tab of the unread ones; at none, "Mark all as read" goes. */
            unread(count) {
                $('#notifPageCount').text(String(count));
                if (count === 0) $('#notifPageReadAll').remove();
            },
        };

        $(function() {
            $('#notifPageReadAll').on('submit', notifPage.readAll);
            $('#notifPageList').on('submit', '.rm-notif-mark', notifPage.readOne);
        });
    </script>
@endpush
