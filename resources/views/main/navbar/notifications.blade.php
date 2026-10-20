{{-- The bell: the notifications of whoever is signed in (modules/Notifications). The script below fills it on every page,
     and asks again every minute while the page is in view. Its look (.rm-notif-*) is in radio-ui.css; the page "Lihat
     semua" uses the same rows. --}}
<div class="dropdown" id="notifBell">
    <a href="#" class="rm-icon-btn rm-bell" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        data-bs-offset="0,10" aria-expanded="false" aria-label="Notifikasi">
        <i class="ti ti-bell" aria-hidden="true"></i>
        <span id="notifCount" class="rm-bell-count d-none"></span>
    </a>
    <div class="dropdown-menu dropdown-menu-end rm-notif-menu">
        <div class="rm-notif-head">
            <h4 class="rm-notif-head-title">Notifikasi</h4>
            <button type="button" class="rm-notif-readall" id="notifReadAll" hidden>Tandai semua dibaca</button>
        </div>
        <div id="notifList" class="rm-notif-list">
            <div class="rm-notif-empty">Belum ada notifikasi</div>
        </div>
        <a href="{{ route('notifications.index') }}" class="rm-notif-foot">Lihat semua notifikasi</a>
    </div>
</div>

@push('javascript')
    <script>
        // The bell (main.navbar.notifications): how many are unread, the last few, and "mark all read". The texts come
        // from the database and go in as text, never as markup. (notifBell.load: the page of the notifications asks
        // for it again after it has marked them all read.)
        const notifBell = {
            summary: "{{ route('notifications.summary') }}",
            readAll: "{{ route('notifications.read-all') }}",
            busy: false,
            /** The colour of each kind (UserNotification::ICONS has the icon). */
            tones: { edit_requested: 'orange', edit_decided: 'blue', token_expiring: 'red', register_weekly: 'gray' },

            esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
            },

            /** One row: the icon in the colour of its kind, the title and when, the text, and a dot while it is unread. */
            entry(n) {
                const esc = notifBell.esc;
                const tone = Object.prototype.hasOwnProperty.call(notifBell.tones, n.kind) ? notifBell.tones[n.kind] : 'gray';
                const icon = /^ti-[a-z0-9-]+$/.test(n.icon) ? n.icon : 'ti-bell';
                return `<a class="rm-notif-item${n.read ? '' : ' is-unread'}" href="${esc(n.open || '#')}">` +
                    `<span class="rm-notif-disc tone-${tone}" aria-hidden="true"><i class="ti ${icon}"></i></span>` +
                    `<span class="rm-notif-text"><span class="rm-notif-line"><span class="rm-notif-title">${esc(n.title)}</span>` +
                    `<span class="rm-notif-time" title="${esc(n.at)}">${esc(n.when)}</span></span>` +
                    (n.body ? `<span class="rm-notif-body">${esc(n.body)}</span>` : '') + `</span>` +
                    (n.read ? '' : '<span class="rm-notif-dot" aria-label="Belum dibaca"></span>') + `</a>`;
            },

            show(data) {
                const count = data && data.ready ? Number(data.unread) || 0 : 0;
                $('#notifCount').text(count > 99 ? '99+' : String(count)).toggleClass('d-none', count === 0);
                $('#notifReadAll').prop('hidden', count === 0);
                const items = data && Array.isArray(data.items) ? data.items : [];
                $('#notifList').html(items.length ? items.map(n => notifBell.entry(n)).join('') : '<div class="rm-notif-empty">Belum ada notifikasi</div>');
            },

            load() {
                if (notifBell.busy) return;
                notifBell.busy = true;
                $.ajax({ url: notifBell.summary, dataType: 'json', cache: false })
                    .done(notifBell.show)
                    .always(() => { notifBell.busy = false; });
            },
        };

        $(function() {
            $('#notifReadAll').on('click', () => {
                $.ajax({ url: notifBell.readAll, type: 'POST', dataType: 'json' }).always(notifBell.load);
            });
            notifBell.load();
            setInterval(() => {
                if (document.visibilityState === 'visible') notifBell.load();
            }, 60000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') notifBell.load();
            });
        });
    </script>
@endpush
