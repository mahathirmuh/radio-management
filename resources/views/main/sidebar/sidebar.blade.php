<!-- Sidebar -->
<aside class="navbar navbar-vertical navbar-expand-lg rm-sidebar d-print-none" data-bs-theme="light" aria-label="Menu utama">
    <div class="container-fluid overflow-x-hidden">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
            aria-controls="sidebar-menu" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a href="/" class="rm-brand" aria-label="{{ config('app.name') }}">
            <img src="{{ asset('assets/img/branding/mcg-logo.png') }}" alt="Merdeka Copper Gold" class="rm-brand-full">
            <img src="{{ asset('assets/img/branding/mbm-logo.png') }}" alt="Merdeka Battery Materials" class="rm-brand-full">
            {{-- In the icon rail: the two marks side by side, as before the design refresh. --}}
            <span class="rm-brand-rail">
                <img src="{{ asset('assets/img/branding/favicon.ico') }}" alt="Merdeka Copper Gold">
                <img src="{{ asset('assets/img/branding/mbm-logo-small.png') }}" alt="Merdeka Battery Materials">
            </span>
        </a>
        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav rm-nav">
                @include('main.sidebar.menu')
                {{-- The top bar with the user menu is hidden below 992px: keep a way to sign out. --}}
                <li class="rm-nav-item d-lg-none">
                    <a class="rm-nav-link" href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();">
                        <span class="rm-nav-icon"><i class="ti ti-logout"></i></span>
                        <span class="rm-nav-label">Keluar ({{ Auth::user()->name }})</span>
                    </a>
                    <form id="logout-form-sidebar" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
            </ul>
        </div>
        <div class="rm-sidebar-mark" aria-hidden="true">
            <img src="{{ asset('assets/img/branding/sidebar-background.png') }}" alt="">
        </div>
    </div>
</aside>
<div class="rm-nav-tip" id="rmNavTip" role="tooltip" hidden></div>

@push('javascript')
    <script>
        // The sidebar (main.sidebar.sidebar): full width or the icon rail, the groups that fold, and the name of an icon in
        // the rail. The choice of width is kept in localStorage ("sidebarState", as before); the head of the page applies it
        // before anything is drawn (main.index), this script only changes it.
        const sidebarUi = {
            key: 'sidebarState',
            rail: false,
            read() {
                try {
                    return window.localStorage.getItem(this.key) === 'collapsed';
                } catch (e) {
                    return false;
                }
            },
            write(rail) {
                try {
                    window.localStorage.setItem(this.key, rail ? 'collapsed' : 'expanded');
                } catch (e) {
                    // A private window, or storage turned off: the choice lasts for this page only.
                }
            },
            apply(rail) {
                this.rail = rail;
                $('html').toggleClass('rm-rail', rail);
                $('#sidebarControl').attr('aria-pressed', rail ? 'true' : 'false');
                if (!rail) this.hideTip();
            },
            toggle() {
                this.apply(!this.rail);
                this.write(this.rail);
            },
            /** A group opens or closes; in the rail its icon goes to its first page instead. */
            group(id, first) {
                if (this.rail) {
                    if (first) window.location.href = first;
                    return;
                }
                const button = $(`[data-rm-group="${id}"]`);
                const open = button.attr('aria-expanded') !== 'true';
                button.attr('aria-expanded', open ? 'true' : 'false');
                $(`#rm-navgroup-${id}`).toggleClass('is-open', open);
            },
            rectOf: element => element.getBoundingClientRect(),
            /** The name of an icon, to the right of it (only the rail hides the names). */
            showTip(text, rect) {
                if (!this.rail || !text) return;
                $('#rmNavTip').text(text).css({ left: `${rect.right + 10}px`, top: `${rect.top + rect.height / 2}px` }).removeAttr('hidden');
            },
            hideTip() {
                $('#rmNavTip').attr('hidden', 'hidden');
            },
        };

        sidebarUi.apply(sidebarUi.read());
        $('#sidebarControl').on('click', () => sidebarUi.toggle());
        $(document).on('click', '[data-rm-group]', function() {
            sidebarUi.group($(this).attr('data-rm-group'), $(this).attr('data-rm-first'));
        });
        $(document).on('mouseenter focusin', '[data-rm-tip]', function() {
            sidebarUi.showTip($(this).attr('data-rm-tip'), sidebarUi.rectOf(this));
        });
        $(document).on('mouseleave focusout', '[data-rm-tip]', () => sidebarUi.hideTip());
        $('.rm-sidebar').on('scroll', () => sidebarUi.hideTip());
    </script>
@endpush
