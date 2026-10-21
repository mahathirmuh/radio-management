<footer class="footer d-print-none rm-footer">
    <span>Copyright &copy; {{ date('Y') }}, Development by <a href="https://merdekacoppergold.com/" target="_blank"
            rel="noopener">Merdeka Group</a></span>
    @if (filled(config('app.version')))
        <button type="button" class="rm-version" id="rmVersion" aria-haspopup="dialog" aria-controls="rmChangelog"
            data-bs-toggle="tooltip" data-bs-title="{{ __('ui.changelog.open') }}">v{{ config('app.version') }}</button>
    @endif
</footer>

@if (filled(config('app.version')))
    {{-- What changed in each version (CHANGELOG.md, without the notes for the server), newest first, as a timeline (the
         design's ChangelogDialog). --}}
    <div class="modal fade rm-modal wide" id="rmChangelog" tabindex="-1" aria-hidden="true" aria-labelledby="rmChangelogTitle">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="rm-dialog-head is-ruled">
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="rmChangelogTitle">{{ __('ui.changelog.title') }}</h3>
                        <p class="rm-dialog-sub">{{ __('ui.changelog.sub', ['version' => config('app.version')]) }}</p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"><i
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
                words: @json(__('ui.changelog')),
                loaded: false,
                loading: false,
                /** The kinds of change in the order of the legend. */
                kinds: ['added', 'changed', 'fixed', 'removed', 'security', 'deprecated'],
                /** The kind that colours a version: the strongest of its own. */
                strongest: ['removed', 'security', 'added', 'fixed', 'changed', 'deprecated'],
                esc(text) {
                    return String(text ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
                },
                /** A kind of change the dialog knows, as the name of its colours; any other is "other" (grey). */
                kind(kind) {
                    return rmChangelog.kinds.includes(kind) ? kind : 'other';
                },
                numbers(version) {
                    return String(version ?? '').split('.').map(Number);
                },
                /** The colour of a version: its strongest kind of change, else "changed". */
                primary(release) {
                    const kinds = (release.sections || []).map(section => section.kind);

                    return rmChangelog.strongest.find(kind => kinds.includes(kind)) || 'changed';
                },
                open() {
                    $('#rmChangelog').modal('show');
                    if (!rmChangelog.loaded && !rmChangelog.loading) rmChangelog.load();
                },
                load() {
                    rmChangelog.loading = true;
                    $('#rmChangelogBody').html(`<div class="rm-changelog-note">${rmChangelog.esc(rmChangelog.words.loading)}</div>`);
                    $.getJSON(rmChangelog.url)
                        .done(data => {
                            rmChangelog.loaded = true;
                            $('#rmChangelogBody').html(rmChangelog.render(data));
                        })
                        .fail(() => {
                            $('#rmChangelogBody').html(`<div class="rm-changelog-note">${rmChangelog.esc(rmChangelog.words.failed)}</div>`);
                        })
                        .always(() => {
                            rmChangelog.loading = false;
                        });
                },
                /**
                 * The versions as a timeline, newest first: a legend, then each series ("1.14") as a node with its minor release
                 * (1.14.0, else its oldest) and its patches below it as branches, newest first; a band "Versi N" where a major
                 * version begins; every version in the colour of its strongest kind of change. The changes are the server's
                 * markup, under a pill per kind.
                 */
                render(data) {
                    const releases = Array.isArray(data?.releases) ? data.releases : [];
                    if (!releases.length) return `<div class="rm-changelog-note">${rmChangelog.esc(rmChangelog.words.empty)}</div>`;

                    const groups = [];
                    releases.forEach(release => {
                        const [major, minor] = rmChangelog.numbers(release.version);
                        const series = `${major}.${minor}`;
                        let group = groups[groups.length - 1];
                        if (!group || group.series !== series) {
                            group = { series, major, minor, list: [] };
                            groups.push(group);
                        }
                        group.list.push(release);
                    });
                    const rows = [];
                    groups.forEach(group => {
                        const head = group.list.find(release => rmChangelog.numbers(release.version)[2] === 0) || group.list[group.list.length - 1];
                        rows.push({ release: head, group, parent: true });
                        group.list.filter(release => release !== head).forEach(release => rows.push({ release, group, parent: false }));
                    });

                    return rmChangelog.legend(releases) + rows.map((row, index) => rmChangelog.row(row, rows[index - 1], rows[index + 1], index)).join('');
                },

                /** The two kinds of release, then each kind of change that the versions have. */
                legend(releases) {
                    const esc = rmChangelog.esc;
                    const words = rmChangelog.words;
                    const present = new Set(releases.flatMap(release => (release.sections || []).map(section => section.kind)));

                    return `<div class="rm-cl-legend">` +
                        `<span class="rm-cl-key"><span class="rm-cl-key-minor" aria-hidden="true"></span>${esc(words.minor)}</span>` +
                        `<span class="rm-cl-key"><span class="rm-cl-key-patch" aria-hidden="true"><span></span></span>${esc(words.patch)}</span>` +
                        `<span class="rm-cl-key-rule" aria-hidden="true"></span>` +
                        rmChangelog.kinds.filter(kind => present.has(kind)).map(kind =>
                            `<span class="rm-cl-key rm-cl-kind-${kind}"><span class="rm-cl-key-dot" aria-hidden="true"></span>${esc(words.kinds[kind])}</span>`).join('') +
                        `</div>`;
                },

                /** One version: the band of its major version when it begins one, the line and the node, its number, date and notes. */
                row(row, previous, next, index) {
                    const esc = rmChangelog.esc;
                    const words = rmChangelog.words;
                    const release = row.release;
                    const major = row.parent && row.group.minor === 0;
                    const classes = ['rm-cl-release', `rm-cl-kind-${rmChangelog.primary(release)}`, row.parent ? 'is-parent' : 'is-patch'];
                    if (major) classes.push('is-major');
                    if (release.current) classes.push('is-current');
                    if (next) classes.push('has-next');

                    const band = !previous || previous.group.major !== row.group.major ?
                        `<div class="rm-cl-era${previous ? ` has-line rm-cl-kind-${rmChangelog.primary(previous.release)}` : ''}">` +
                        `<div class="rm-cl-rail">${previous ? '<span class="rm-cl-era-line"></span>' : ''}</div>` +
                        `<div class="rm-cl-era-label"><span>${esc(rmFill(words.era, { major: row.group.major }))}</span><span class="rm-cl-hair"></span></div></div>` :
                        '';
                    const node = row.parent ? (major ? `${row.group.major}.0` : row.group.series) : '';
                    const rail = (previous ? '<span class="rm-cl-line is-top"></span>' : '') + (next ? '<span class="rm-cl-line is-bottom"></span>' : '') +
                        (row.parent ? '' : '<span class="rm-cl-elbow"></span>') + (release.current ? '<span class="rm-cl-ping"></span>' : '') +
                        `<span class="rm-cl-node">${esc(node)}</span>`;
                    const badges = (major ? `<span class="rm-badge rm-cl-major">${esc(words.major)}</span>` : '') +
                        (release.current ? `<span class="rm-badge blue">${esc(words.current)}</span>` : '');
                    const notes = (release.sections || []).map(section => {
                        const kind = rmChangelog.kind(section.kind);

                        return (section.heading ? `<div class="rm-cl-section rm-cl-kind-${kind}"><span class="rm-cl-pill"><span class="rm-cl-pill-dot"></span>${esc(section.heading)}</span></div>` : '') +
                            `<div class="rm-cl-text rm-cl-kind-${kind}">${String(section.html ?? '')}</div>`;
                    }).join('');

                    return `<div class="${classes.join(' ')}" style="--rm-cl-delay: ${Math.min(index, 14) * 45}ms">${band}` +
                        `<div class="rm-cl-row"><div class="rm-cl-rail" aria-hidden="true">${rail}</div>` +
                        `<div class="rm-cl-content"><div class="rm-cl-head"><span class="rm-cl-version">v${esc(release.version)}</span>` +
                        (release.date_label ? `<span class="rm-cl-date">${esc(release.date_label)}</span>` : '') + badges + `</div>` +
                        `<div class="rm-cl-notes" lang="id">${notes}</div></div></div></div>`;
                },
            };
            $('#rmVersion').on('click', () => rmChangelog.open());
        </script>
    @endpush
@endif
