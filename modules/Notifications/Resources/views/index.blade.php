@extends('main.index')
{{-- Every notification of whoever is signed in, grouped by day. The look of a row (.rm-notif-*) is the bell's (radio-ui.css). --}}
@section('page-title', __('ui.topbar.notifications'))
@php
    // The colour and the word of each kind (the icon is UserNotification::ICONS'): as the bell has them.
    $notifTones = ['edit_requested' => 'orange', 'edit_decided' => 'blue', 'token_expiring' => 'red', 'register_weekly' => 'gray'];
    $notifKinds = __('Notifications::page.kinds');
    // The rows of this page by day: today, yesterday, before (Notifications::page.groups).
    $notifGroups = $ready ? $notifications->getCollection()->groupBy(function ($notification) {
        $at = $notification->created_at;
        if ($at?->isToday()) {
            return 'today';
        }
        return $at?->isYesterday() ? 'yesterday' : 'earlier';
    }) : collect();
@endphp
@section('content')
    <div class="rm-notif-page">
        <div class="rm-head">
            <div>
                <h1 class="rm-title">{{ __('ui.topbar.notifications') }}</h1>
                <p class="rm-sub" id="notifPageSub">
                    @if ($ready && $unread > 0)
                        {{ __('Notifications::page.unread_count', ['count' => $unread]) }}
                    @endif
                    {{ __('Notifications::page.sub') }}
                </p>
            </div>
        </div>

        <div class="rm-card">
            <div class="rm-notif-tabsrow">
                <nav class="rm-tabs" aria-label="{{ __('Notifications::page.tabs') }}">
                    <a href="{{ route('notifications.index') }}" @class(['is-on' => ! $unreadOnly]) @if (! $unreadOnly) aria-current="page" @endif>{{ __('Notifications::page.all') }}</a>
                    <a href="{{ route('notifications.index', ['show' => 'unread']) }}" @class(['is-on' => $unreadOnly]) @if ($unreadOnly) aria-current="page" @endif>
                        {{ __('ui.bell.unread') }}
                        @if ($unread > 0)
                            <span class="rm-tab-count" id="notifPageCount">{{ $unread }}</span>
                        @endif
                    </a>
                </nav>
                @if ($ready && $unread > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" id="notifPageReadAll">
                        @csrf
                        @if ($unreadOnly)
                            <input type="hidden" name="show" value="unread">
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
                    <div class="rm-empty-title">{{ __($unreadOnly ? 'Notifications::page.empty.all_read' : 'Notifications::page.empty.none') }}</div>
                    <div class="rm-empty-text">
                        {{ __($unreadOnly ? 'Notifications::page.empty.all_read_text' : 'Notifications::page.empty.none_text') }}
                    </div>
                </div>

                <div id="notifPageList">
                    @foreach ($notifGroups as $day => $items)
                        <div class="rm-notif-group @unless ($loop->first) mt-3 @endunless">
                            <div class="rm-notif-group-label">{{ __('Notifications::page.groups.' . $day) }}</div>
                            <div class="rm-notif-box">
                                @foreach ($items as $notification)
                                    <a href="{{ route('notifications.open', $notification->id) }}"
                                        @class(['rm-notif-item', 'is-unread' => ! $notification->read_at])>
                                        <span class="rm-notif-disc tone-{{ $notifTones[$notification->kind] ?? 'gray' }}" aria-hidden="true"><i
                                                class="ti {{ $notification->icon() }}"></i></span>
                                        <span class="rm-notif-text">
                                            <span class="rm-notif-line">
                                                <span class="rm-notif-title">{{ $notification->title }}</span>
                                                @isset($notifKinds[$notification->kind])
                                                    <span class="badge bg-secondary-lt">{{ $notifKinds[$notification->kind] }}</span>
                                                @endisset
                                            </span>
                                            @if ($notification->body)
                                                <span class="rm-notif-body">{{ $notification->body }}</span>
                                            @endif
                                            <span class="rm-notif-when d-block" title="{{ $notification->created_at?->format('d-m-Y H:i') }}">
                                                {{ $notification->created_at?->diffForHumans() }} ·
                                                {{ $notification->created_at?->format('d-m-Y H:i') }}
                                            </span>
                                        </span>
                                        @unless ($notification->read_at)
                                            <span class="rm-notif-dot" aria-label="{{ __('ui.bell.unread') }}"></span>
                                        @endunless
                                    </a>
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
        // "Mark all as read" without leaving the page (the form still works without the script): every row is read, the
        // counts go, and on the tab of the unread ones the list is empty.
        const notifPage = {
            url: @json(route('notifications.read-all')),
            unreadOnly: @json($unreadOnly),
            /** Its words in the language of the page (Notifications::page.script). */
            words: @json(__('Notifications::page.script')),

            readAll(event) {
                event.preventDefault();
                const form = $('#notifPageReadAll');
                rmFeedback.busy('#notifPageReadAllButton', true);
                $.ajax({ url: notifPage.url, type: 'POST', dataType: 'json', data: { _token: form.find('input[name="_token"]').val() } })
                    .done(() => {
                        $('#notifPageList .rm-notif-item').removeClass('is-unread');
                        $('#notifPageList .rm-notif-dot').remove();
                        $('#notifPageCount').remove();
                        form.remove();
                        $('#notifPageSub').text(notifPage.words.all_read);
                        if (notifPage.unreadOnly) {
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
        };

        $(function() {
            $('#notifPageReadAll').on('submit', notifPage.readAll);
        });
    </script>
@endpush
