{{-- The bell: the notifications of whoever is signed in (modules/Notifications). The script below fills it on every page,
     and asks again every minute while the page is in view. (The style is here, not in the "style" stack: the layout writes
     that stack in the head, before the navbar is drawn. The page "See all" uses the same classes.) --}}
<style>
    #notifBell .notif-menu {
        width: 23rem;
        max-width: calc(100vw - 2rem);
    }

    #notifBell #notifList {
        max-height: 24rem;
        overflow-y: auto;
    }

    .notif-item {
        display: flex;
        gap: .7rem;
        align-items: flex-start;
        padding: .7rem .9rem;
    }

    .notif-item.unread {
        background: rgba(32, 107, 196, .06);
    }

    .notif-icon {
        flex: 0 0 auto;
        width: 2rem;
        height: 2rem;
        border-radius: .5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(32, 107, 196, .1);
        color: #206bc4;
        font-size: 1.05rem;
    }

    .notif-text {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .15rem;
    }

    .notif-title {
        color: var(--tblr-body-color, #1d273b);
        font-weight: 500;
        line-height: 1.3;
    }

    .notif-item.unread .notif-title {
        font-weight: 700;
    }

    .notif-body {
        color: var(--tblr-secondary, #667382);
        font-size: .8rem;
        line-height: 1.35;
        overflow-wrap: anywhere;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notif-body.notif-body-full {
        display: block;
        overflow: visible;
    }

    .notif-time {
        color: var(--tblr-secondary, #667382);
        font-size: .72rem;
    }
</style>
<div class="dropdown" id="notifBell">
    <a href="#" class="rm-icon-btn rm-bell" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        data-bs-offset="0,10" aria-expanded="false" aria-label="Notifikasi">
        <i class="ti ti-bell" aria-hidden="true"></i>
        <span id="notifCount" class="rm-bell-count d-none"></span>
    </a>
    <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card notif-menu">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2 py-2 px-3">
                <h4 class="mb-0 me-auto">Notifikasi</h4>
                <button type="button" class="btn btn-link btn-sm px-0" id="notifReadAll" hidden>Tandai semua dibaca</button>
            </div>
            <div id="notifList" class="list-group list-group-flush list-group-hoverable">
                <div class="list-group-item p-3 text-secondary">Belum ada notifikasi</div>
            </div>
            <div class="card-footer border-top text-center py-2">
                <a href="{{ route('notifications.index') }}" class="small">Lihat semua notifikasi</a>
            </div>
        </div>
    </div>
</div>

@push('javascript')
    <script>
        // The bell (main.navbar.notifications): how many are unread, the last few, and "mark all read". The texts come
        // from the database and go in as text, never as markup.
        $(function() {
            const bell = {
                summary: "{{ route('notifications.summary') }}",
                readAll: "{{ route('notifications.read-all') }}",
                busy: false,
            };

            const entry = (n) => {
                const link = $('<a class="list-group-item list-group-item-action notif-item"></a>')
                    .attr('href', String(n.open || '#')).toggleClass('unread', !n.read);
                const icon = $('<span class="notif-icon"><i class="ti"></i></span>');
                icon.find('i').addClass(/^ti-[a-z0-9-]+$/.test(n.icon) ? n.icon : 'ti-bell');
                const text = $('<span class="notif-text"></span>');
                text.append($('<span class="notif-title"></span>').text(n.title || ''));
                if (n.body) text.append($('<span class="notif-body"></span>').text(n.body));
                text.append($('<span class="notif-time"></span>').text(n.when || '').attr('title', n.at || ''));
                return link.append(icon, text);
            };

            const show = (data) => {
                const count = data && data.ready ? Number(data.unread) || 0 : 0;
                $('#notifCount').text(count > 99 ? '99+' : String(count)).toggleClass('d-none', count === 0);
                $('#notifReadAll').prop('hidden', count === 0);
                const list = $('#notifList').empty();
                const items = data && Array.isArray(data.items) ? data.items : [];
                if (!items.length) {
                    list.append($('<div class="list-group-item p-3 text-secondary"></div>').text('Belum ada notifikasi'));
                    return;
                }
                items.forEach(n => list.append(entry(n)));
            };

            const load = () => {
                if (bell.busy) return;
                bell.busy = true;
                $.ajax({ url: bell.summary, dataType: 'json', cache: false })
                    .done(show)
                    .always(() => { bell.busy = false; });
            };

            $('#notifReadAll').on('click', () => {
                $.ajax({ url: bell.readAll, type: 'POST', dataType: 'json' }).always(load);
            });
            load();
            setInterval(() => {
                if (document.visibilityState === 'visible') load();
            }, 60000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') load();
            });
        });
    </script>
@endpush
