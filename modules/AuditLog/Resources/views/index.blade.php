@extends('main.index')
@push('style')
    <style>
        /* The filters: the search twice as wide as a list (room is kept for the lists still to come). */
        .audit-filters {
            display: grid;
            grid-template-columns: minmax(0, 2fr) repeat(3, minmax(0, 1fr)) minmax(0, 1.2fr);
            gap: 12px;
            align-items: end;
        }

        .audit-filters .rm-search {
            width: 100%;
        }

        @media (max-width: 767.98px) {
            .audit-filters {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        /* The list, and the details of the entry that is open beside it (under it, on a narrower window). */
        .audit-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        @media (min-width: 1440px) {
            .audit-body.has-details {
                grid-template-columns: minmax(480px, 1fr) 360px;
            }
        }

        .audit-body > * {
            min-width: 0;
        }

        #auditTable tbody tr[data-index] {
            cursor: pointer;
        }

        #auditTable tbody tr[data-index]:focus-visible {
            outline: 2px solid var(--rm-brand-600);
            outline-offset: -2px;
        }

        #auditTable td {
            vertical-align: top;
        }

        /* Long names, records and changes wrap (the table has to fit beside the details); the change shows two lines at
           most, all of it is in the details. */
        #auditTable td.audit-user {
            min-width: 7rem;
            overflow-wrap: anywhere;
        }

        /* (A badge stays on one line elsewhere; the name of an API token may be long.) */
        #auditTable td.audit-user .badge {
            white-space: normal;
            text-align: left;
        }

        #auditTable .audit-label {
            min-width: 6rem;
            max-width: 16rem;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        #auditTable td.audit-summary {
            min-width: 9rem;
        }

        #auditTable .audit-clamp {
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow-wrap: anywhere;
        }

        #auditTable td.audit-ip {
            color: var(--rm-gray-500);
        }

        /* Log activity: a disc in the colour of the action, a line down to the next one. */
        .audit-timeline {
            margin: 0;
            padding: 20px 20px 0;
            list-style: none;
        }

        .audit-event {
            position: relative;
            display: grid;
            grid-template-columns: 32px minmax(0, 1fr);
            column-gap: 14px;
            border-radius: 8px;
            cursor: pointer;
        }

        .audit-event:not(:last-child)::before {
            content: "";
            position: absolute;
            top: 38px;
            bottom: 6px;
            left: 15px;
            width: 2px;
            border-radius: 1px;
            background: var(--rm-gray-200);
        }

        .audit-event:focus-visible {
            outline: 2px solid var(--rm-brand-600);
            outline-offset: 2px;
        }

        .audit-disc {
            display: inline-grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: var(--rm-gray-100);
            color: var(--rm-gray-600);
            font-size: 16px;
        }

        .audit-disc.tone-blue {
            background: var(--rm-brand-100);
            color: var(--rm-brand-700);
        }

        .audit-disc.tone-green {
            background: var(--rm-success-100);
            color: var(--rm-success-700);
        }

        .audit-disc.tone-orange {
            background: var(--rm-orange-100);
            color: var(--rm-orange-700);
        }

        .audit-disc.tone-red {
            background: var(--rm-error-100);
            color: var(--rm-error-700);
        }

        .audit-disc.tone-purple {
            background: var(--rm-purple-100);
            color: var(--rm-purple-700);
        }

        .audit-disc.tone-yellow {
            background: var(--rm-warning-100);
            color: var(--rm-warning-700);
        }

        .audit-event-body {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
            padding: 6px 0 22px;
        }

        .audit-sentence {
            color: var(--rm-gray-700);
            font-size: 14px;
            line-height: 20px;
        }

        .audit-sentence b {
            color: var(--rm-brand-700);
            font-weight: 600;
        }

        .audit-event.is-open .audit-sentence {
            text-decoration: underline;
            text-decoration-color: var(--rm-brand-300);
            text-underline-offset: 3px;
        }

        .audit-sentence .audit-dot {
            color: var(--rm-gray-400);
        }

        .audit-sentence .audit-ago {
            color: var(--rm-gray-500);
        }

        .audit-changes {
            width: fit-content;
            max-width: 100%;
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--rm-gray-50);
            box-shadow: inset 0 0 0 1px var(--rm-gray-200);
            font-family: var(--rm-mono);
            font-size: 12px;
            line-height: 18px;
        }

        .audit-changes > div {
            display: flex;
            flex-wrap: wrap;
            gap: 0 6px;
            overflow-wrap: anywhere;
        }

        .audit-changes .audit-field,
        .audit-changes s {
            color: var(--rm-gray-500);
        }

        .audit-changes .audit-to {
            color: var(--rm-gray-400);
        }

        .audit-stamp {
            display: flex;
            flex-wrap: wrap;
            gap: 2px 12px;
            color: var(--rm-gray-500);
            font-size: 12px;
            line-height: 16px;
        }

        .audit-stamp .audit-key {
            font-family: var(--rm-mono);
        }

        .audit-empty {
            padding: 40px 0 48px;
            color: var(--rm-gray-500);
            font-size: 14px;
            text-align: center;
        }

        /* The details of one entry. */
        .audit-details .rm-cardbox-head {
            box-shadow: inset 0 -1px 0 var(--rm-gray-200);
            margin-bottom: 14px;
            padding-bottom: 14px;
        }

        .audit-meta {
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr);
            gap: 8px 12px;
            margin: 0 0 14px;
            font-size: 13px;
            line-height: 18px;
        }

        .audit-meta dt {
            color: var(--rm-gray-500);
            font-weight: 400;
        }

        .audit-meta dd {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .audit-diff {
            overflow: hidden;
            border: 1px solid var(--rm-gray-200);
            border-radius: 8px;
            font-family: var(--rm-mono);
            font-size: 12px;
            line-height: 18px;
        }

        .audit-diff > div {
            padding: 3px 10px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .audit-diff .is-old {
            background: var(--rm-error-50);
            color: var(--rm-error-700);
        }

        .audit-diff .is-new {
            background: var(--rm-success-50);
            color: var(--rm-success-700);
        }

        .audit-diff .is-none {
            color: var(--rm-gray-500);
            font-family: var(--tblr-font-sans-serif);
        }
    </style>
@endpush

@section('page-title', 'Audit Log')

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">Audit log</h1>
            <p class="rm-sub">Every create, update, delete, sign-in and import, with who did it and from where.</p>
        </div>
    </div>

    @unless ($ready)
        <div class="rm-alert warning mb-3" role="alert" id="auditNotReady">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span>The audit log is not switched on yet: its migration has not been run on this database
                (<code>php artisan migrate</code>). Nothing is recorded until then.</span>
        </div>
    @endunless

    <div class="rm-card">
        {{-- The table stays the default: only it shows the record, the change and the address side by side. --}}
        <div class="rm-toolbar rm-toolbar-tight">
            <div class="rm-seg" role="group" aria-label="View">
                <button type="button" @class(['is-on' => $view !== 'timeline']) data-audit-view="table"
                    aria-pressed="{{ $view === 'timeline' ? 'false' : 'true' }}"><i class="ti ti-table me-1"
                        aria-hidden="true"></i>Table</button>
                <button type="button" @class(['is-on' => $view === 'timeline']) data-audit-view="timeline"
                    aria-pressed="{{ $view === 'timeline' ? 'true' : 'false' }}"><i class="ti ti-timeline-event me-1"
                        aria-hidden="true"></i>Log activity</button>
            </div>
        </div>

        <div class="audit-filters">
            <div class="rm-field">
                <label class="form-label" for="auditSearch">User email</label>
                <div class="rm-search">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input id="auditSearch" type="search" value="{{ $search }}" autocomplete="off" placeholder="Search by email">
                </div>
            </div>
            <div class="rm-field">
                <label class="form-label" for="auditAction">Action</label>
                <select id="auditAction" class="form-select">
                    <option value="">All actions</option>
                    @foreach ($actions as $option)
                        <option value="{{ $option }}" @selected($option === $action)>{{ $option }}</option>
                    @endforeach
                    {{-- An action named by a link that nothing has been recorded for yet. --}}
                    @if ($action !== '' && ! $actions->contains($action))
                        <option value="{{ $action }}" selected>{{ $action }}</option>
                    @endif
                </select>
            </div>
        </div>

        <div class="audit-body" id="auditBody">
            <div>
                <div id="auditTableView" @class(['d-none' => $view === 'timeline'])>
                    <div class="rm-box" id="auditTableBox">
                        <div class="table-responsive">
                            <table class="rm-table" id="auditTable">
                                <thead>
                                    <tr>
                                        <th scope="col">Time</th>
                                        <th scope="col">User</th>
                                        <th scope="col" width="120">Action</th>
                                        <th scope="col">Entity</th>
                                        <th scope="col">Summary</th>
                                        <th scope="col" class="text-end">IP</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="pagination-container" id="auditPagination"></div>
                    </div>
                </div>

                {{-- Log activity: one sentence per entry, the newest first. --}}
                <div id="auditTimelineView" @class(['d-none' => $view !== 'timeline'])>
                    <div class="rm-cardbox" id="auditTimelineBox">
                        <div class="rm-cardbox-head align-items-baseline">
                            <h2 class="rm-cardbox-title">Log activity</h2>
                            <span class="rm-cardbox-sub mt-0">Newest first</span>
                        </div>
                        <ol class="audit-timeline" id="auditTimeline"></ol>
                    </div>
                </div>
            </div>

            <aside class="rm-cardbox audit-details d-none" id="auditDetails" aria-labelledby="auditDetailsTitle">
                <div class="rm-cardbox-head">
                    <div class="min-w-0">
                        <h2 class="rm-cardbox-title" id="auditDetailsTitle">Event details</h2>
                        <div class="rm-cardbox-sub" id="auditDetailsSub"></div>
                    </div>
                    <button type="button" class="rm-dialog-close ms-auto" id="auditDetailsClose" aria-label="Close the details"><i
                            class="ti ti-x" aria-hidden="true"></i></button>
                </div>
                <div class="rm-cardbox-body">
                    <dl class="audit-meta" id="auditDetailsMeta"></dl>
                    <div class="audit-diff" id="auditDetailsDiff"></div>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const auditLog = {
            url: @json(route('audit-log.list')),
            ready: @json($ready),
            view: @json($view),
            page: 1,
            limit: 25,
            filters: {
                search: @json($search),
                action: @json($action),
            },
            rows: [],
            meta: null,
            state: 'loading',
            request: null,
            /** The entry whose details are open (its place in rows), or null. */
            open: null,

            esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[c]);
            },

            /** An action for the eye: a word, a colour and an icon. The key itself is in the tooltip and the details. */
            kinds: {
                'auth.login': ['Signed in', 'blue', 'login'],
                'auth.login_failed': ['Sign-in failed', 'red', 'lock'],
                'auth.logout': ['Signed out', 'gray', 'logout'],
                'auth.session_expired': ['Session expired', 'yellow', 'clock'],
                'user.status_changed': ['Status changed', 'orange', 'user-check'],
                'user.password_reset': ['Password set', 'orange', 'key'],
                'radio.history_added': ['Created', 'green', 'plus'],
                'radio.handed_over': ['Handed over', 'blue', 'user-check'],
                'radio.imported': ['Imported', 'purple', 'upload'],
                'radio.archived': ['Archived', 'gray', 'archive'],
                'radio.restored': ['Restored', 'green', 'restore'],
                'service.status_changed': ['Moved', 'orange', 'tool'],
                'service.note_added': ['Note', 'blue', 'notes'],
                'attention.assigned': ['Assigned', 'blue', 'user-plus'],
                'attention.dismissed': ['Dismissed', 'gray', 'eye-off'],
                'esign.used': ['Signed', 'blue', 'signature'],
                'document.edit_requested': ['Requested', 'yellow', 'send'],
                'document.edit_approved': ['Approved', 'green', 'check'],
                'document.edit_rejected': ['Rejected', 'red', 'x'],
                'api.token_revoked': ['Revoked', 'red', 'ban'],
            },

            kind(action) {
                const key = String(action ?? '');
                if (Object.prototype.hasOwnProperty.call(auditLog.kinds, key)) {
                    const [label, tone, icon] = auditLog.kinds[key];
                    return { label, tone, icon };
                }
                if (/created$/.test(key)) return { label: 'Created', tone: 'green', icon: 'plus' };
                if (/updated$/.test(key)) return { label: 'Updated', tone: 'orange', icon: 'edit' };
                if (/deleted$/.test(key)) return { label: 'Deleted', tone: 'red', icon: 'trash' };
                // An action without a word here shows its key, so a new one is not quietly read as something else.
                return { label: key || '—', tone: 'gray', icon: 'activity' };
            },

            badge(action) {
                const kind = auditLog.kind(action);
                const tone = kind.tone === 'gray' ? 'secondary' : kind.tone;
                return `<span class="badge bg-${tone}-lt" title="${auditLog.esc(action)}">${auditLog.esc(kind.label)}</span>`;
            },

            /** The change in one line: "Status: Active → Inactive · Department: Geology → Mining". */
            summary(row) {
                return (row.changes || []).map(change =>
                    `${change.field}: ${change.old !== null ? change.old + ' → ' : ''}${change.new}`).join(' · ');
            },

            columns: [{
                    key: 'at',
                    className: 'text-nowrap',
                    // The day, and the time to the minute under it; the seconds are in the details.
                    render: r => `<div>${auditLog.esc(String(r.at ?? '').slice(0, 10))}</div><div class="rm-line2">${auditLog.esc(String(r.at ?? '').slice(11, 16))}</div>`
                },
                {
                    key: 'user',
                    className: 'audit-user',
                    // The name; the address is in its tooltip and in the details.
                    render: r => `<div class="rm-strong"${r.email ? ` title="${auditLog.esc(r.email)}"` : ''}>${auditLog.esc(r.user)}</div>` +
                        (r.via ? `<div class="mt-1"><span class="badge bg-purple-lt">via ${auditLog.esc(r.via)}</span></div>` : '')
                },
                {
                    key: 'action',
                    className: 'text-nowrap',
                    render: r => auditLog.badge(r.action)
                },
                {
                    key: 'entity',
                    render: r => {
                        if (!r.entity && !r.label) return '<span class="rm-none">—</span>';
                        return (r.label ? `<div class="audit-label">${auditLog.esc(r.label)}</div>` : '') +
                            (r.entity ? `<div class="${r.label ? 'rm-line2 ' : ''}text-nowrap">${auditLog.esc(r.entity)}</div>` : '');
                    }
                },
                {
                    key: 'summary',
                    className: 'audit-summary',
                    render: r => {
                        const text = auditLog.summary(r);
                        return text ? `<span class="audit-clamp" title="${auditLog.esc(text)}">${auditLog.esc(text)}</span>` : '<span class="rm-none">—</span>';
                    }
                },
                {
                    key: 'ip',
                    className: 'text-nowrap text-end rm-code audit-ip',
                    render: r => r.ip ? auditLog.esc(r.ip) : '<span class="rm-none">—</span>'
                },
            ],

            /** One entry of Log activity: "<who> <did what> <to what> • 2 hours ago", the change, then time, key and address. */
            event(row, index) {
                const esc = auditLog.esc;
                const kind = auditLog.kind(row.action);
                const what = row.label || row.entity;
                const lines = (row.changes || []).map(change =>
                    `<div><span class="audit-field">${esc(change.field)}:</span>` +
                    (change.old !== null ? `<s>${esc(change.old)}</s><span class="audit-to">→</span>` : '') +
                    `<span>${esc(change.new)}</span></div>`
                ).join('');

                return `<li class="audit-event${auditLog.open === index ? ' is-open' : ''}" data-index="${index}" tabindex="0" style="--i: ${index}">` +
                    `<span class="audit-disc tone-${kind.tone}" aria-hidden="true"><i class="ti ti-${kind.icon}"></i></span>` +
                    `<div class="audit-event-body">` +
                    `<div class="audit-sentence"><b>${esc(row.user)}</b> ${esc(row.activity)} ${what ? `<b>${esc(what)}</b> ` : ''}` +
                    `<span class="audit-dot">•</span> <span class="audit-ago">${esc(row.ago)}</span></div>` +
                    (lines ? `<div class="audit-changes">${lines}</div>` : '') +
                    `<div class="audit-stamp"><span>${esc(row.at)}</span><span class="audit-key">${esc(row.action)}</span>` +
                    (row.ip ? `<span>from ${esc(row.ip)}</span>` : '') + (row.via ? `<span>via ${esc(row.via)}</span>` : '') +
                    `</div></div></li>`;
            },

            /** The details of one entry beside the list: when, who, what, from where, and the change line by line. */
            details(index) {
                const row = auditLog.rows[index];
                if (!row) return auditLog.close();
                const esc = auditLog.esc;
                const kind = auditLog.kind(row.action);
                const what = row.label || row.entity;
                auditLog.open = index;

                $('#auditDetailsSub').text(kind.label + (what ? ' · ' + what : ''));
                const meta = [
                    ['Time', esc(row.at), false],
                    ['User', esc(row.user) + (row.email && row.email !== row.user ? `<div class="rm-line2">${esc(row.email)}</div>` : ''), false],
                    ['Action', esc(row.action), true],
                ];
                if (row.entity) meta.push(['Entity', esc(row.entity), false]);
                if (row.label) meta.push(['Record', esc(row.label), false]);
                meta.push(['IP', esc(row.ip || '—'), true]);
                if (row.via) meta.push(['Via', esc(row.via), false]);
                $('#auditDetailsMeta').html(meta.map(([term, value, mono]) =>
                    `<dt>${term}</dt><dd${mono ? ' class="font-monospace"' : ''}>${value}</dd>`).join(''));

                const lines = (row.changes || []).flatMap(change => change.old !== null ?
                    [['is-old', `- ${change.field}: ${change.old}`], ['is-new', `+ ${change.field}: ${change.new}`]] :
                    [['is-new', `+ ${change.field}: ${change.new}`]]);
                $('#auditDetailsDiff').html(lines.length ?
                    lines.map(([tone, text]) => `<div class="${tone}">${esc(text)}</div>`).join('') :
                    '<div class="is-none">No field was changed.</div>');

                $('#auditDetails').removeClass('d-none');
                $('#auditBody').addClass('has-details');
                auditLog.mark();
                // Under the list on a narrower window: brought into view.
                const card = document.getElementById('auditDetails');
                if (card && window.innerWidth < 1440 && card.scrollIntoView) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            },

            close() {
                auditLog.open = null;
                $('#auditDetails').addClass('d-none');
                $('#auditBody').removeClass('has-details');
                auditLog.mark();
            },

            /** The row (or the entry of Log activity) whose details are open is marked. */
            mark() {
                $('#auditTable tbody tr').removeClass('is-selected');
                $('#auditTimeline .audit-event').removeClass('is-open');
                if (auditLog.open === null) return;
                $(`#auditTable tbody tr[data-index="${auditLog.open}"]`).addClass('is-selected');
                $(`#auditTimeline .audit-event[data-index="${auditLog.open}"]`).addClass('is-open');
            },

            /** A click (or Enter) on a row opens its details; on the open one, closes them. */
            toggle(index) {
                if (auditLog.open === index) return auditLog.close();
                auditLog.details(index);
            },

            render() {
                const message = {
                    loading: 'Loading...',
                    failed: 'The audit log could not be loaded.',
                    off: 'The audit log is not switched on yet.',
                } [auditLog.state] || 'No audit entries yet.';
                const timeline = auditLog.view === 'timeline';

                $('#auditTableView').toggleClass('d-none', timeline);
                $('#auditTimelineView').toggleClass('d-none', !timeline);
                $('[data-audit-view="table"]').toggleClass('is-on', !timeline).attr('aria-pressed', timeline ? 'false' : 'true');
                $('[data-audit-view="timeline"]').toggleClass('is-on', timeline).attr('aria-pressed', timeline ? 'true' : 'false');
                // One foot with the pages, under the view that is shown.
                $('#auditPagination').appendTo(timeline ? '#auditTimelineBox' : '#auditTableBox');

                const rows = auditLog.state === 'done' ? auditLog.rows : [];
                if (timeline) {
                    $('#auditTimeline').html(rows.length ?
                        rows.map((row, index) => auditLog.event(row, index)).join('') :
                        `<li class="audit-empty">${auditLog.esc(message)}</li>`);
                } else {
                    renderTable({
                        container: '#auditTable',
                        data: rows,
                        columns: auditLog.columns,
                        currentPage: auditLog.page,
                        limit: auditLog.limit,
                        emptyText: auditLog.esc(message),
                        rowAttrs: (row, index) => `data-index="${index}" tabindex="0"`,
                    });
                }
                auditLog.mark();

                if (auditLog.state === 'done' && auditLog.meta) {
                    renderPagination({
                        meta: auditLog.meta,
                        limit: auditLog.limit,
                        container: '#auditPagination',
                        onPageChange: page => {
                            auditLog.page = page;
                            auditLog.load();
                        },
                        onLimitChange: limit => {
                            auditLog.limit = limit;
                            auditLog.page = 1;
                            auditLog.load();
                        },
                    });
                } else {
                    $('#auditPagination').hide();
                }
            },

            load() {
                // Other entries come: the details of one of the old ones close.
                auditLog.close();
                if (!auditLog.ready) {
                    auditLog.state = 'off';
                    return auditLog.render();
                }
                auditLog.request?.abort();
                auditLog.state = 'loading';
                auditLog.render();
                auditLog.request = $.getJSON(auditLog.url, {
                    page: auditLog.page,
                    paginated: auditLog.limit,
                    ...auditLog.filters,
                }).done(response => {
                    auditLog.rows = response.data || [];
                    auditLog.meta = response.meta || null;
                    auditLog.state = 'done';
                    auditLog.render();
                }).fail((xhr, status) => {
                    if (status === 'abort') return;
                    auditLog.state = xhr.status === 503 ? 'off' : 'failed';
                    auditLog.render();
                });
            },

            /** The address of the page says what is shown, so it can be passed on: ?view=timeline&action=auth.login_failed&q=budi */
            remember() {
                const params = new URLSearchParams();
                if (auditLog.view !== 'table') params.set('view', auditLog.view);
                if (auditLog.filters.action) params.set('action', auditLog.filters.action);
                if (auditLog.filters.search) params.set('q', auditLog.filters.search);
                const query = params.toString();
                history.replaceState(null, '', location.pathname + (query ? '?' + query : ''));
            },

            apply() {
                auditLog.filters = {
                    search: String($('#auditSearch').val() || '').trim(),
                    action: String($('#auditAction').val() || ''),
                };
                auditLog.page = 1;
                auditLog.remember();
                auditLog.load();
            },
        };

        $(function() {
            $('#auditSearch').on('input', debounce(() => auditLog.apply(), 300)).on('keydown', e => {
                if (e.key === 'Enter') auditLog.apply();
            });
            $('#auditAction').on('change', () => auditLog.apply());
            $('[data-audit-view]').on('click', function() {
                auditLog.view = $(this).data('audit-view') === 'timeline' ? 'timeline' : 'table';
                auditLog.remember();
                auditLog.render();
            });
            $('#auditTable').on('click', 'tbody tr[data-index]', function() {
                auditLog.toggle(Number($(this).attr('data-index')));
            });
            $('#auditTimeline').on('click', '.audit-event', function() {
                auditLog.toggle(Number($(this).attr('data-index')));
            });
            $('#auditTable, #auditTimeline').on('keydown', '[data-index]', function(e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                auditLog.toggle(Number($(this).attr('data-index')));
            });
            $('#auditDetailsClose').on('click', () => auditLog.close());
            auditLog.load();
        });
    </script>
@endpush
