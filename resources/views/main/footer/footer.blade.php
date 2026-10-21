<footer class="footer d-print-none rm-footer">
    <span>Copyright &copy; {{ date('Y') }}, Development by <a href="https://merdekacoppergold.com/" target="_blank"
            rel="noopener">Merdeka Group</a></span>
    @if (filled(config('app.version')))
        <button type="button" class="rm-version" id="rmVersion" aria-haspopup="dialog" aria-controls="rmChangelog"
            data-bs-toggle="tooltip" data-bs-title="Lihat perubahan tiap versi">v{{ config('app.version') }}</button>
    @endif
</footer>

@if (filled(config('app.version')))
    {{-- What changed in each version (CHANGELOG.md, without the notes for the server), newest first. --}}
    <div class="modal fade rm-modal wide" id="rmChangelog" tabindex="-1" aria-hidden="true" aria-labelledby="rmChangelogTitle">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="rm-dialog-head is-ruled">
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="rmChangelogTitle">Riwayat versi</h3>
                        <p class="rm-dialog-sub">Versi yang berjalan: v{{ config('app.version') }}. Yang terbaru di atas.</p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body rm-changelog" id="rmChangelogBody" aria-live="polite"></div>
            </div>
        </div>
    </div>

    @push('javascript')
        <script>
            // The version in the footer: what changed in each version, asked of the server the first time the dialog opens.
            const rmChangelog = {
                url: @json(route('changelog')),
                loaded: false,
                loading: false,
                esc(text) {
                    return String(text ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
                },
                open() {
                    $('#rmChangelog').modal('show');
                    if (!rmChangelog.loaded && !rmChangelog.loading) rmChangelog.load();
                },
                load() {
                    rmChangelog.loading = true;
                    $('#rmChangelogBody').html('<div class="rm-changelog-note">Memuat riwayat versi…</div>');
                    $.getJSON(rmChangelog.url)
                        .done(data => {
                            rmChangelog.loaded = true;
                            $('#rmChangelogBody').html(rmChangelog.render(data));
                        })
                        .fail(() => {
                            $('#rmChangelogBody').html('<div class="rm-changelog-note">Riwayat versi tidak bisa dimuat. Tutup lalu buka lagi untuk mencoba sekali lagi.</div>');
                        })
                        .always(() => {
                            rmChangelog.loading = false;
                        });
                },
                /** One block per version: its number, its date and, for the version this server runs, "Versi ini"; then its changes (markup made by the server). */
                render(data) {
                    const releases = Array.isArray(data?.releases) ? data.releases : [];
                    if (!releases.length) return '<div class="rm-changelog-note">Belum ada catatan perubahan.</div>';

                    return releases.map(release => `<div class="rm-release${release.current ? ' is-current' : ''}">
                        <div class="rm-release-head"><span class="rm-release-version">v${rmChangelog.esc(release.version)}</span>${release.date_label ? `<span class="rm-release-date">${rmChangelog.esc(release.date_label)}</span>` : ''}${release.current ? '<span class="rm-badge blue">Versi ini</span>' : ''}</div>
                        <div class="rm-release-body">${String(release.html ?? '')}</div>
                    </div>`).join('');
                },
            };
            $('#rmVersion').on('click', () => rmChangelog.open());
        </script>
    @endpush
@endif
