@extends('main.index')
{{-- Every notification of whoever is signed in, grouped by day. The look of a row (.rm-notif-*) is the bell's (radio-ui.css). --}}
@section('page-title', 'Notifikasi')
@php
    // The colour and the word of each kind (the icon is UserNotification::ICONS'): as the bell has them.
    $notifTones = ['edit_requested' => 'orange', 'edit_decided' => 'blue', 'token_expiring' => 'red', 'register_weekly' => 'gray'];
    $notifKinds = ['edit_requested' => 'Persetujuan', 'edit_decided' => 'Pengajuan', 'token_expiring' => 'Token API', 'register_weekly' => 'Register'];
    // The rows of this page by day: today, yesterday, before.
    $notifGroups = $ready ? $notifications->getCollection()->groupBy(function ($notification) {
        $at = $notification->created_at;
        if ($at?->isToday()) {
            return 'Hari ini';
        }
        return $at?->isYesterday() ? 'Kemarin' : 'Sebelumnya';
    }) : collect();
@endphp
@section('content')
    <div class="rm-notif-page">
        <div class="rm-head">
            <div>
                <h1 class="rm-title">Notifikasi</h1>
                <p class="rm-sub" id="notifPageSub">
                    @if ($ready && $unread > 0)
                        {{ $unread }} belum dibaca.
                    @endif
                    Pengajuan perubahan workbook, token API yang hampir berakhir, dan ringkasan mingguan register. Yang sudah
                    dibaca dihapus otomatis setelah 90 hari.
                </p>
            </div>
        </div>

        <div class="rm-card">
            <div class="rm-notif-tabsrow">
                <nav class="rm-tabs" aria-label="Notifikasi yang ditampilkan">
                    <a href="{{ route('notifications.index') }}" @class(['is-on' => ! $unreadOnly]) @if (! $unreadOnly) aria-current="page" @endif>Semua</a>
                    <a href="{{ route('notifications.index', ['show' => 'unread']) }}" @class(['is-on' => $unreadOnly]) @if ($unreadOnly) aria-current="page" @endif>
                        Belum dibaca
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
                            <i class="ti ti-check" aria-hidden="true"></i> Tandai semua dibaca
                        </button>
                    </form>
                @endif
            </div>

            @if (! $ready)
                <div class="rm-alert warning">
                    <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                    <span>Notifikasi belum aktif di database ini: jalankan <code>php artisan migrate</code> lebih dulu.</span>
                </div>
            @else
                <div class="rm-empty-state @unless ($notifications->isEmpty()) d-none @endunless" id="notifPageEmpty">
                    <span class="rm-empty-icon tone-green" aria-hidden="true"><i class="ti ti-circle-check"></i></span>
                    <div class="rm-empty-title">{{ $unreadOnly ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi' }}</div>
                    <div class="rm-empty-text">
                        {{ $unreadOnly ? 'Tidak ada yang menunggu untuk dibaca.' : 'Notifikasi muncul di sini saat ada yang perlu Anda ketahui.' }}
                    </div>
                </div>

                <div id="notifPageList">
                    @foreach ($notifGroups as $label => $items)
                        <div class="rm-notif-group @unless ($loop->first) mt-3 @endunless">
                            <div class="rm-notif-group-label">{{ $label }}</div>
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
                                                {{ $notification->created_at?->locale('id')->diffForHumans() }} ·
                                                {{ $notification->created_at?->format('d-m-Y H:i') }}
                                            </span>
                                        </span>
                                        @unless ($notification->read_at)
                                            <span class="rm-notif-dot" aria-label="Belum dibaca"></span>
                                        @endunless
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($notifications->hasPages())
                    <div class="rm-foot px-0">
                        <span class="rm-foot-text">{{ $notifications->firstItem() }} – {{ $notifications->lastItem() }} dari
                            {{ $notifications->total() }}</span>
                        <span class="rm-end">
                            <a class="btn-rm secondary sm {{ $notifications->onFirstPage() ? 'disabled' : '' }}"
                                href="{{ $notifications->previousPageUrl() ?? '#' }}"><i class="ti ti-chevron-left" aria-hidden="true"></i>
                                Sebelumnya</a>
                            <a class="btn-rm secondary sm {{ $notifications->hasMorePages() ? '' : 'disabled' }}"
                                href="{{ $notifications->nextPageUrl() ?? '#' }}">Berikutnya <i class="ti ti-chevron-right"
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
        // "Tandai semua dibaca" without leaving the page (the form still works without the script): every row is read,
        // the counts go, and on "Belum dibaca" the list is empty.
        const notifPage = {
            url: @json(route('notifications.read-all')),
            unreadOnly: @json($unreadOnly),

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
                        $('#notifPageSub').text('Semua notifikasi sudah dibaca.');
                        if (notifPage.unreadOnly) {
                            $('#notifPageList').empty();
                            $('#notifPageEmpty').removeClass('d-none');
                        }
                        rmFeedback.toast('Semua notifikasi ditandai sudah dibaca.');
                        if (typeof notifBell !== 'undefined') notifBell.load();
                    })
                    .fail(() => {
                        rmFeedback.busy('#notifPageReadAllButton', false);
                        rmFeedback.toast('Notifikasi tidak bisa ditandai dibaca. Coba lagi.', 'error');
                    });
            },
        };

        $(function() {
            $('#notifPageReadAll').on('submit', notifPage.readAll);
        });
    </script>
@endpush
