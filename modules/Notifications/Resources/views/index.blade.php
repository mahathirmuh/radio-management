@extends('main.index')
{{-- Every notification of whoever is signed in. The look of an entry (.notif-item, ...) is the bell's: main.navbar.notifications. --}}
@section('page-title', 'Notifikasi')
@section('content')
    <div class="row g-3">
        <div class="col-12 d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div>
                <h2 class="mb-0">Notifikasi</h2>
                <div class="text-secondary small">Pengajuan perubahan workbook, token API yang hampir berakhir, dan ringkasan
                    mingguan register. Yang sudah dibaca dihapus otomatis setelah 90 hari.</div>
            </div>
            @if ($ready && $unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}" id="notifPageReadAll">
                    @csrf
                    @if ($unreadOnly)
                        <input type="hidden" name="show" value="unread">
                    @endif
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-checks me-1"></i> Tandai semua dibaca
                    </button>
                </form>
            @endif
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link {{ $unreadOnly ? '' : 'active' }}" href="{{ route('notifications.index') }}">Semua</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $unreadOnly ? 'active' : '' }}" href="{{ route('notifications.index', ['show' => 'unread']) }}">
                                Belum dibaca
                                @if ($unread > 0)
                                    <span class="badge bg-red text-white ms-1">{{ $unread }}</span>
                                @endif
                            </a>
                        </li>
                    </ul>
                </div>

                @if (! $ready)
                    <div class="card-body text-secondary">
                        Notifikasi belum aktif di database ini: jalankan <code>php artisan migrate</code> lebih dulu.
                    </div>
                @elseif ($notifications->isEmpty())
                    <div class="card-body">
                        <div class="empty py-4">
                            <div class="empty-icon"><i class="ti ti-bell fs-1"></i></div>
                            <p class="empty-title">{{ $unreadOnly ? 'Semua notifikasi sudah dibaca' : 'Belum ada notifikasi' }}</p>
                        </div>
                    </div>
                @else
                    <div class="list-group list-group-flush notif-page-list">
                        @foreach ($notifications as $notification)
                            <a href="{{ route('notifications.open', $notification->id) }}"
                                class="list-group-item list-group-item-action notif-item {{ $notification->read_at ? '' : 'unread' }}">
                                <span class="notif-icon"><i class="ti {{ $notification->icon() }}"></i></span>
                                <span class="notif-text flex-fill">
                                    <span class="notif-title">{{ $notification->title }}</span>
                                    @if ($notification->body)
                                        <span class="notif-body notif-body-full">{{ $notification->body }}</span>
                                    @endif
                                    <span class="notif-time" title="{{ $notification->created_at?->format('d-m-Y H:i') }}">
                                        {{ $notification->created_at?->locale('id')->diffForHumans() }} ·
                                        {{ $notification->created_at?->format('d-m-Y H:i') }}
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                    @if ($notifications->hasPages())
                        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="text-secondary small">{{ $notifications->firstItem() }}–{{ $notifications->lastItem() }}
                                dari {{ $notifications->total() }}</span>
                            <span class="d-flex gap-2">
                                <a class="btn btn-sm {{ $notifications->onFirstPage() ? 'disabled' : '' }}"
                                    href="{{ $notifications->previousPageUrl() ?? '#' }}">&larr; Sebelumnya</a>
                                <a class="btn btn-sm {{ $notifications->hasMorePages() ? '' : 'disabled' }}"
                                    href="{{ $notifications->nextPageUrl() ?? '#' }}">Berikutnya &rarr;</a>
                            </span>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
