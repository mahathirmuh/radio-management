@extends('main.index')
@push('style')
    <style>
        /* The filters: the search twice as wide as a list. */
        .audit-filters {
            display: grid;
            grid-template-columns: minmax(0, 2fr) repeat(2, minmax(0, 1fr)) minmax(0, 1.2fr);
            gap: 12px;
            align-items: end;
        }

        .audit-filters .rm-search {
            width: 100%;
        }

        /* The calendar of "Custom range…" opens under the field, to its right edge (the field is the last of the row). */
        .audit-range {
            position: relative;
        }

        .audit-range .rm-cal {
            right: 0;
            left: auto;
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

@section('page-title', __('AuditLog::log.page_title'))
@section('heading', __('AuditLog::log.title'))

@section('content')
    @unless ($ready)
        <div class="rm-alert warning mb-3" role="alert" id="auditNotReady">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span>{!! __('AuditLog::log.not_ready_page') !!}</span>
        </div>
    @endunless

    <div class="rm-card">
        {{-- The table stays the default: only it shows the record, the change and the address side by side. --}}
        <div class="rm-toolbar rm-toolbar-tight">
            <div class="rm-seg" role="group" aria-label="{{ __('AuditLog::log.view') }}">
                <button type="button" @class(['is-on' => $view !== 'timeline']) data-audit-view="table"
                    aria-pressed="{{ $view === 'timeline' ? 'false' : 'true' }}"><i class="ti ti-table me-1"
                        aria-hidden="true"></i>{{ __('AuditLog::log.table') }}</button>
                <button type="button" @class(['is-on' => $view === 'timeline']) data-audit-view="timeline"
                    aria-pressed="{{ $view === 'timeline' ? 'true' : 'false' }}"><i class="ti ti-timeline-event me-1"
                        aria-hidden="true"></i>{{ __('AuditLog::log.timeline') }}</button>
            </div>
        </div>

        <div class="audit-filters">
            <div class="rm-field">
                <label class="form-label" for="auditSearch">{{ __('AuditLog::log.filters.search') }}</label>
                <div class="rm-search">
                    <i class="ti ti-search" aria-hidden="true"></i>
                    <input id="auditSearch" type="search" value="{{ $search }}" autocomplete="off" placeholder="{{ __('AuditLog::log.filters.search_hint') }}">
                </div>
            </div>
            <div class="rm-field">
                <label class="form-label" for="auditAction">{{ __('AuditLog::log.filters.action') }}</label>
                <select id="auditAction" class="form-select">
                    <option value="">{{ __('AuditLog::log.filters.all_actions') }}</option>
                    @foreach ($actions as $option)
                        <option value="{{ $option }}" @selected($option === $action)>{{ $option }}</option>
                    @endforeach
                    {{-- An action named by a link that nothing has been recorded for yet. --}}
                    @if ($action !== '' && ! $actions->contains($action))
                        <option value="{{ $action }}" selected>{{ $action }}</option>
                    @endif
                </select>
            </div>
            <div class="rm-field">
                <label class="form-label" for="auditEntity">{{ __('AuditLog::log.filters.entity') }}</label>
                <select id="auditEntity" class="form-select">
                    <option value="">{{ __('AuditLog::log.filters.all_entities') }}</option>
                    @foreach ($entities as $type => $name)
                        <option value="{{ $type }}" @selected($type === $entity)>{{ $name }}</option>
                    @endforeach
                    {{-- An entity named by a link that nothing has been recorded about yet. --}}
                    @if ($entity !== '' && ! array_key_exists($entity, $entities))
                        <option value="{{ $entity }}" selected>{{ $entity }}</option>
                    @endif
                </select>
            </div>
            <div class="rm-field audit-range" id="auditRangeField">
                <label class="form-label" for="auditRange">{{ __('AuditLog::log.filters.range') }}</label>
                <select id="auditRange" class="form-select">
                    {{-- today, yesterday, 7d, 30d, all --}}
                    @foreach (__('AuditLog::log.filters.ranges') as $value => $name)
                        <option value="{{ $value }}" @selected($value === $range)>{{ $name }}</option>
                    @endforeach
                    {{-- The days chosen on the calendar, named by them (auditRange.show()). --}}
                    <option value="custom" id="auditRangeCustom" @selected($range === 'custom') @if ($range !== 'custom') hidden disabled @endif></option>
                    <option value="pick">{{ __('AuditLog::log.filters.pick') }}</option>
                </select>
                <div class="rm-cal" id="auditCal" role="dialog" aria-label="{{ __('AuditLog::log.filters.calendar') }}" hidden></div>
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
                                        <th scope="col">{{ __('AuditLog::log.columns.time') }}</th>
                                        <th scope="col">{{ __('AuditLog::log.columns.user') }}</th>
                                        <th scope="col" width="120">{{ __('AuditLog::log.columns.action') }}</th>
                                        <th scope="col">{{ __('AuditLog::log.columns.entity') }}</th>
                                        <th scope="col">{{ __('AuditLog::log.columns.summary') }}</th>
                                        <th scope="col" class="text-end">{{ __('AuditLog::log.columns.ip') }}</th>
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
                            <h2 class="rm-cardbox-title">{{ __('AuditLog::log.timeline') }}</h2>
                            <span class="rm-cardbox-sub mt-0">{{ __('AuditLog::log.newest_first') }}</span>
                        </div>
                        <ol class="audit-timeline" id="auditTimeline"></ol>
                    </div>
                </div>
            </div>

            <aside class="rm-cardbox audit-details d-none" id="auditDetails" aria-labelledby="auditDetailsTitle">
                <div class="rm-cardbox-head">
                    <div class="min-w-0">
                        <h2 class="rm-cardbox-title" id="auditDetailsTitle">{{ __('AuditLog::log.details') }}</h2>
                        <div class="rm-cardbox-sub" id="auditDetailsSub"></div>
                    </div>
                    <button type="button" class="rm-dialog-close ms-auto" id="auditDetailsClose" aria-label="{{ __('AuditLog::log.close_details') }}"><i
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
        /** The words of the page's script in the language of the page (AuditLog::log.script). */
        const auditText = @json(__('AuditLog::log.script'));

        const auditLog = {
            url: @json(route('audit-log.list')),
            ready: @json($ready),
            view: @json($view),
            page: 1,
            limit: 25,
            filters: {
                search: @json($search),
                action: @json($action),
                entity: @json($entity),
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

            /**
             * An action for the eye: a word (auditText.kinds), a colour and an icon. The key itself is in the tooltip and the
             * details.
             */
            kinds: {
                'auth.login': ['blue', 'login'],
                'auth.login_failed': ['red', 'lock'],
                'auth.logout': ['gray', 'logout'],
                'auth.session_expired': ['yellow', 'clock'],
                'user.status_changed': ['orange', 'user-check'],
                'user.password_reset': ['orange', 'key'],
                'radio.history_added': ['green', 'plus'],
                'radio.handed_over': ['blue', 'user-check'],
                'radio.imported': ['purple', 'upload'],
                'radio.opname_submitted': ['green', 'barcode'],
                'radio.archived': ['gray', 'archive'],
                'radio.restored': ['green', 'restore'],
                'service.status_changed': ['orange', 'tool'],
                'service.note_added': ['blue', 'notes'],
                'attention.assigned': ['blue', 'user-plus'],
                'attention.dismissed': ['gray', 'eye-off'],
                'esign.used': ['blue', 'signature'],
                'document.edit_requested': ['yellow', 'send'],
                'document.edit_approved': ['green', 'check'],
                'document.edit_rejected': ['red', 'x'],
                'document.edited_directly': ['orange', 'edit'],
                'api.token_revoked': ['red', 'ban'],
            },

            kind(action) {
                const key = String(action ?? '');
                if (Object.prototype.hasOwnProperty.call(auditLog.kinds, key)) {
                    const [tone, icon] = auditLog.kinds[key];
                    return { label: auditText.kinds[key] || key, tone, icon };
                }
                if (/created$/.test(key)) return { label: auditText.generic.created, tone: 'green', icon: 'plus' };
                if (/updated$/.test(key)) return { label: auditText.generic.updated, tone: 'orange', icon: 'edit' };
                if (/deleted$/.test(key)) return { label: auditText.generic.deleted, tone: 'red', icon: 'trash' };
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
                        (r.via ? `<div class="mt-1"><span class="badge bg-purple-lt">${auditLog.esc(rmFill(auditText.via, { name: r.via }))}</span></div>` : '')
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
                    (row.ip ? `<span>${esc(rmFill(auditText.from, { ip: row.ip }))}</span>` : '') +
                    (row.via ? `<span>${esc(rmFill(auditText.via, { name: row.via }))}</span>` : '') +
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
                const terms = auditText.meta;
                const meta = [
                    [terms.time, esc(row.at), false],
                    [terms.user, esc(row.user) + (row.email && row.email !== row.user ? `<div class="rm-line2">${esc(row.email)}</div>` : ''), false],
                    [terms.action, esc(row.action), true],
                ];
                if (row.entity) meta.push([terms.entity, esc(row.entity), false]);
                if (row.label) meta.push([terms.record, esc(row.label), false]);
                meta.push([terms.ip, esc(row.ip || '—'), true]);
                if (row.browser) meta.push([terms.browser, esc(row.browser), false]);
                if (row.via) meta.push([terms.via, esc(row.via), false]);
                $('#auditDetailsMeta').html(meta.map(([term, value, mono]) =>
                    `<dt>${esc(term)}</dt><dd${mono ? ' class="font-monospace"' : ''}>${value}</dd>`).join(''));

                const lines = (row.changes || []).flatMap(change => change.old !== null ?
                    [['is-old', `- ${change.field}: ${change.old}`], ['is-new', `+ ${change.field}: ${change.new}`]] :
                    [['is-new', `+ ${change.field}: ${change.new}`]]);
                $('#auditDetailsDiff').html(lines.length ?
                    lines.map(([tone, text]) => `<div class="${tone}">${esc(text)}</div>`).join('') :
                    `<div class="is-none">${esc(auditText.no_change)}</div>`);

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
                const states = auditText.states;
                const message = {
                    loading: states.loading,
                    failed: states.failed,
                    off: states.off,
                } [auditLog.state] || states.empty;
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
                    ...auditRange.days(),
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

            /**
             * The address of the page says what is shown, so it can be passed on: ?view=timeline&action=auth.login_failed&q=budi. A
             * named range stays a name (?range=7d: the last seven days of whenever the link is opened), chosen days are days.
             */
            remember() {
                const params = new URLSearchParams();
                if (auditLog.view !== 'table') params.set('view', auditLog.view);
                if (auditLog.filters.action) params.set('action', auditLog.filters.action);
                if (auditLog.filters.entity) params.set('entity', auditLog.filters.entity);
                if (auditLog.filters.search) params.set('q', auditLog.filters.search);
                if (auditRange.chosen === 'custom') {
                    params.set('from', auditRange.from);
                    params.set('to', auditRange.to);
                } else if (auditRange.chosen !== 'all') {
                    params.set('range', auditRange.chosen);
                }
                const query = params.toString();
                history.replaceState(null, '', location.pathname + (query ? '?' + query : ''));
            },

            apply() {
                auditLog.filters = {
                    search: String($('#auditSearch').val() || '').trim(),
                    action: String($('#auditAction').val() || ''),
                    entity: String($('#auditEntity').val() || ''),
                };
                auditLog.page = 1;
                auditLog.remember();
                auditLog.load();
            },
        };

        /**
         * "Date range": a range the page names (Today, Last 7 days, …), or days chosen on the calendar under the field: a click on
         * the first day and one on the last (the earlier of the two is the first), then Apply. The browser turns a name into its
         * days each time the list is asked for.
         */
        const auditRange = {
            /** The names of the months and days, and the words of the calendar, in the language of the page (lang/<code>/ui.php). */
            words: @json(__('ui.pickers')),
            months: @json(__('ui.pickers.months')),
            shortMonths: @json(__('ui.pickers.short_months')),
            weekdays: @json(__('ui.pickers.weekdays')),
            /** How many days before today the first and the last day of a named range are. */
            back: {
                today: [0, 0],
                yesterday: [1, 1],
                '7d': [6, 0],
                '30d': [29, 0],
            },
            /** What is applied: a name of back, 'custom' (the days from and to) or 'all'. */
            chosen: @json($range),
            from: @json($from),
            to: @json($to),
            /** While the calendar is open: the days clicked, the month shown, the day under the pointer. */
            isOpen: false,
            first: '',
            last: '',
            month: null,
            hover: '',

            today() {
                return new Date();
            },
            /** YYYY-MM-DD of a date. */
            iso(date) {
                return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            },
            parse(text) {
                const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(text || ''));
                return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
            },
            /** "12 Oct 2026". */
            label(text) {
                const date = auditRange.parse(text);
                return date ? `${String(date.getDate()).padStart(2, '0')} ${auditRange.shortMonths[date.getMonth()]} ${date.getFullYear()}` : '';
            },
            /** Days as short as they can be named: "12 – 19 Oct 2026", "28 Sep – 04 Oct 2026", "28 Dec 2025 – 03 Jan 2026", "19 Oct 2026". */
            name(from, to) {
                const [a, b] = [auditRange.parse(from), auditRange.parse(to)];
                if (!a || !b) return '';
                if (from === to) return auditRange.label(from);
                if (a.getFullYear() !== b.getFullYear()) return `${auditRange.label(from)} – ${auditRange.label(to)}`;
                const day = date => String(date.getDate()).padStart(2, '0');
                const month = date => auditRange.shortMonths[date.getMonth()];
                if (a.getMonth() !== b.getMonth()) return `${day(a)} ${month(a)} – ${day(b)} ${month(b)} ${b.getFullYear()}`;

                return `${day(a)} – ${day(b)} ${month(b)} ${b.getFullYear()}`;
            },
            /** The days of what is applied, as the list is asked for them ({ from, to }), or nothing: all time. */
            days() {
                if (auditRange.chosen === 'custom') return auditRange.from && auditRange.to ? { from: auditRange.from, to: auditRange.to } : {};
                if (!Object.prototype.hasOwnProperty.call(auditRange.back, auditRange.chosen)) return {};
                const [first, last] = auditRange.back[auditRange.chosen];
                const today = auditRange.today();
                const day = before => auditRange.iso(new Date(today.getFullYear(), today.getMonth(), today.getDate() - before));

                return { from: day(first), to: day(last) };
            },

            /** The select was changed: a name (or the days chosen before) is applied at once, "Custom range…" opens the calendar. */
            change(value) {
                if (value === 'pick') return auditRange.open();
                if (auditRange.isOpen) auditRange.close();
                auditRange.chosen = value === 'custom' && auditRange.from && auditRange.to ? 'custom' :
                    (Object.prototype.hasOwnProperty.call(auditRange.back, value) ? value : 'all');
                auditLog.apply();
            },

            /** The days chosen on the calendar stay in the select, named by them, to be chosen again; the select says what is applied. */
            show() {
                const named = auditRange.name(auditRange.from, auditRange.to);
                $('#auditRangeCustom').text(named).prop('hidden', !named).prop('disabled', !named);
                $('#auditRange').val(auditRange.chosen);
            },

            open() {
                const custom = auditRange.chosen === 'custom';
                auditRange.first = custom ? auditRange.from : '';
                auditRange.last = custom ? auditRange.to : '';
                auditRange.hover = '';
                const shown = auditRange.parse(auditRange.last) || auditRange.today();
                auditRange.month = new Date(shown.getFullYear(), shown.getMonth(), 1);
                auditRange.isOpen = true;
                auditRange.draw();
                $('#auditCal').removeAttr('hidden');
                // A press anywhere else gives it up.
                $(document).on('mousedown.auditCal', event => {
                    const field = document.getElementById('auditRangeField');
                    if (field && field.contains && !field.contains(event.target)) auditRange.cancel();
                });
                // Into the calendar: the last day chosen, or today (both in the month shown).
                $(`#auditCal [data-date="${auditRange.last || auditRange.iso(auditRange.today())}"]`).trigger('focus');
            },

            close() {
                auditRange.isOpen = false;
                $('#auditCal').attr('hidden', 'hidden');
                $(document).off('mousedown.auditCal');
            },

            /** Nothing changes: the select says again what is applied. */
            cancel() {
                auditRange.close();
                auditRange.show();
                $('#auditRange').trigger('focus');
            },

            apply() {
                if (!auditRange.first || !auditRange.last) return;
                [auditRange.from, auditRange.to, auditRange.chosen] = [auditRange.first, auditRange.last, 'custom'];
                auditRange.close();
                auditRange.show();
                $('#auditRange').trigger('focus');
                auditLog.apply();
            },

            /** A day clicked: the first, else the last. */
            pick(iso) {
                if (!auditRange.first || auditRange.last) {
                    [auditRange.first, auditRange.last] = [iso, ''];
                } else if (iso < auditRange.first) {
                    [auditRange.first, auditRange.last] = [iso, auditRange.first];
                } else {
                    auditRange.last = iso;
                }
                auditRange.hover = '';
                auditRange.draw();
                $(`#auditCal [data-date="${iso}"]`).trigger('focus');
            },

            /** The pointer over a day while only the first is chosen: the range it would make is shown. */
            point(iso) {
                if (iso === auditRange.hover || !auditRange.first || auditRange.last) return;
                auditRange.hover = iso;
                auditRange.draw();
            },

            step(months) {
                auditRange.month = new Date(auditRange.month.getFullYear(), auditRange.month.getMonth() + months, 1);
                auditRange.draw();
            },

            /** The month: the band between the two days (or the first and the day under the pointer), no day after today. */
            draw() {
                const today = auditRange.iso(auditRange.today());
                const month = auditRange.month;
                const lead = (month.getDay() + 6) % 7;
                const count = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
                const [first, last, hover] = [auditRange.first, auditRange.last, auditRange.hover];
                let [a, b] = [first, last];
                if (first && !last && hover) [a, b] = hover < first ? [hover, first] : [first, hover];
                const cells = [];
                for (let i = 0; i < 42; i++) {
                    const day = i - lead + 1;
                    if (day < 1 || day > count) {
                        cells.push('<span class="rm-cal-cell"></span>');
                        continue;
                    }
                    const iso = auditRange.iso(new Date(month.getFullYear(), month.getMonth(), day));
                    const band = a && b && a !== b ? (iso > a && iso < b ? ' is-band' : (iso === a ? ' is-start' : (iso === b ? ' is-end' : ''))) : '';
                    const on = iso === first || iso === last;
                    const classes = ['rm-cal-day', on ? 'is-on' : '', !on && first && !last && iso === hover ? 'is-preview' : '', !on && iso === today ? 'is-today' : '']
                        .filter(Boolean).join(' ');
                    cells.push(`<span class="rm-cal-cell${band}"><button type="button" class="${classes}" data-date="${iso}" ` +
                        `aria-label="${day} ${auditRange.months[month.getMonth()]} ${month.getFullYear()}"${on ? ' aria-pressed="true"' : ''}` +
                        `${iso > today ? ' disabled' : ''}>${day}</button></span>`);
                }
                const now = auditRange.today();
                const latest = month.getFullYear() * 12 + month.getMonth() >= now.getFullYear() * 12 + now.getMonth();
                const summary = first && last ? (first === last ? auditRange.label(first) : `${auditRange.label(first)} – ${auditRange.label(last)}`) :
                    (first ? rmFill(auditText.pick_end, { first: auditRange.label(first) }) : auditText.pick_start);
                // The day that has the focus keeps it in the month drawn again.
                const focused = document.activeElement && document.activeElement.getAttribute ? document.activeElement.getAttribute('data-date') : null;
                $('#auditCal').html(
                    '<div class="rm-cal-head">' +
                    `<button type="button" class="rm-cal-nav" data-nav="-1" aria-label="${auditLog.esc(auditRange.words.previous_month)}"><i class="ti ti-chevron-left"></i></button>` +
                    `<div class="rm-cal-title">${auditRange.months[month.getMonth()]} ${month.getFullYear()}</div>` +
                    `<button type="button" class="rm-cal-nav" data-nav="1" aria-label="${auditLog.esc(auditRange.words.next_month)}"${latest ? ' disabled' : ''}><i class="ti ti-chevron-right"></i></button>` +
                    '</div>' +
                    `<div class="rm-cal-week">${auditRange.weekdays.map(name => `<span>${name}</span>`).join('')}</div>` +
                    `<div class="rm-cal-grid">${cells.join('')}</div>` +
                    `<div class="rm-cal-sum"><span class="rm-cal-sum-text">${auditLog.esc(summary)}</span>` +
                    `<button type="button" class="btn-rm secondary sm" data-cal="cancel">${auditLog.esc(auditText.cancel)}</button>` +
                    `<button type="button" class="btn-rm primary sm" data-cal="apply"${first && last ? '' : ' disabled'}>${auditLog.esc(auditText.apply)}</button></div>`);
                if (focused) $(`#auditCal [data-date="${focused}"]`).trigger('focus');
            },
        };

        $(function() {
            $('#auditSearch').on('input', debounce(() => auditLog.apply(), 300)).on('keydown', e => {
                if (e.key === 'Enter') auditLog.apply();
            });
            $('#auditAction').on('change', () => auditLog.apply());
            $('#auditEntity').on('change', () => auditLog.apply());
            $('#auditRange').on('change', () => auditRange.change(String($('#auditRange').val() || '')));
            $('#auditCal').on('click', '[data-nav]', function() {
                auditRange.step(Number($(this).attr('data-nav')));
            });
            $('#auditCal').on('click', '[data-date]', function() {
                auditRange.pick(String($(this).attr('data-date')));
            });
            $('#auditCal').on('mouseover', '[data-date]', function() {
                auditRange.point(String($(this).attr('data-date')));
            });
            $('#auditCal').on('click', '[data-cal]', function() {
                if ($(this).attr('data-cal') === 'apply') {
                    auditRange.apply();
                } else {
                    auditRange.cancel();
                }
            });
            // Esc gives the calendar up (and only that: a dialog round it, there is none, keeps it).
            $('#auditRangeField').on('keydown', e => {
                if (e.key !== 'Escape' || !auditRange.isOpen) return;
                e.preventDefault();
                e.stopPropagation();
                auditRange.cancel();
            });
            auditRange.show();
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
