@extends('main.index')
@push('style')
    <style>
        /* The trail as a table: what an auditor reads (the change field by field, and the address). */
        #auditTable th,
        #auditTable td {
            padding: .65rem .6rem;
            vertical-align: top;
        }

        #auditTable th:first-child,
        #auditTable td:first-child {
            padding-left: 1rem;
        }

        #auditTable td.font-monospace {
            font-size: .75rem;
        }

        /* An address or a long name may break anywhere: the table has to fit beside the sidebar. */
        #auditTable .audit-label {
            overflow-wrap: anywhere;
        }

        #auditTable th {
            font-weight: 600;
        }

        #auditTable td {
            font-size: .8125rem;
        }

        #auditTable .audit-muted,
        #auditTimeline .audit-muted {
            color: #667382;
        }

        #auditTable .audit-change {
            min-width: 13rem;
            max-width: 26rem;
        }

        #auditTable .audit-change dl {
            margin: 0;
        }

        #auditTable .audit-change dt {
            font-weight: normal;
            color: #667382;
            white-space: nowrap;
        }

        #auditTable .audit-change dd {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .audit-old {
            text-decoration: line-through;
            color: #667382;
        }

        #auditTimeline .audit-stamp {
            font-size: .7rem;
        }
    </style>
@endpush

@section('page-title', 'Audit Log')

@section('content')
    {{-- The layout of trilingual-docon's Audit log: title, and the switch between the table and the timeline. --}}
    <div class="page-header d-print-none mb-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Audit log</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    {{-- The table stays the default: only it shows the record, the change field by field and the address. --}}
                    <div class="btn-group" role="group" aria-label="View">
                        <button type="button" class="btn {{ $view === 'timeline' ? 'btn-outline-secondary' : 'btn-primary' }}"
                            data-audit-view="table" aria-pressed="{{ $view === 'timeline' ? 'false' : 'true' }}">
                            <i class="ti ti-table me-1"></i>Table
                        </button>
                        <button type="button" class="btn {{ $view === 'timeline' ? 'btn-primary' : 'btn-outline-secondary' }}"
                            data-audit-view="timeline" aria-pressed="{{ $view === 'timeline' ? 'true' : 'false' }}">
                            <i class="ti ti-timeline-event me-1"></i>Log Activity
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @unless ($ready)
        <div class="alert alert-warning" role="alert" id="auditNotReady">
            The audit log is not switched on yet: its migration has not been run on this database
            (<code>php artisan migrate</code>). Nothing is recorded until then.
        </div>
    @endunless

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="auditSearch">User email</label>
                    <input id="auditSearch" type="search" class="form-control" value="{{ $search }}" autocomplete="off"
                        placeholder="Search by email">
                </div>
                <div class="col-md-6">
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
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="auditTableView" @class(['d-none' => $view === 'timeline'])>
                <div class="card overflow-hidden mdka-border-gray-200">
                    <div class="card-body table-responsive p-0">
                        <table class="table table-vcenter card-table mdka-border-gray-100 mb-0" id="auditTable">
                            <thead>
                                <tr class="mdka-bg-gray-50">
                                    <th scope="col" class="fs-5">When</th>
                                    <th scope="col" class="fs-5">User</th>
                                    <th scope="col" class="fs-5">Action</th>
                                    <th scope="col" class="fs-5">Entity</th>
                                    <th scope="col" class="fs-5">Change</th>
                                    <th scope="col" class="fs-5">IP</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Log Activity: Base-Apps-Merdeka's timeline (components/global/log-activity-canvas). --}}
            <div id="auditTimelineView" @class(['d-none' => $view !== 'timeline'])>
                <h4 class="mb-3">Log Activity</h4>
                <ul class="timeline mb-0" id="auditTimeline"></ul>
            </div>

            <div class="pagination-container mt-3" id="auditPagination"></div>
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

            esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[c]);
            },

            /** The change, one field per line: "Status: <s>Active</s> → Inactive". */
            changes(row) {
                const esc = auditLog.esc;
                if (!row.changes || row.changes.length === 0) {
                    return '<span class="audit-muted">—</span>';
                }
                return '<dl>' + row.changes.map(change => `
                    <div class="d-flex gap-1">
                        <dt>${esc(change.field)}:</dt>
                        <dd>${change.old !== null ? `<span class="audit-old">${esc(change.old)}</span> &rarr; ` : ''}${esc(change.new)}</dd>
                    </div>`).join('') + '</dl>';
            },

            columns: [{
                    key: 'at',
                    className: 'text-nowrap audit-muted',
                    render: r => auditLog.esc(r.at)
                },
                {
                    key: 'user',
                    render: r => `<div>${auditLog.esc(r.user)}</div>` +
                        (r.email ? `<div class="audit-muted">${auditLog.esc(r.email)}</div>` : '') +
                        (r.via ? `<div class="mt-1"><span class="badge bg-purple-lt">via ${auditLog.esc(r.via)}</span></div>` : '')
                },
                {
                    key: 'action',
                    className: 'text-nowrap font-monospace',
                    render: r => auditLog.esc(r.action)
                },
                {
                    key: 'entity',
                    className: 'audit-muted',
                    render: r => {
                        if (!r.entity && !r.label) return '—';
                        return (r.entity ? `<div class="text-nowrap">${auditLog.esc(r.entity)}</div>` : '') +
                            (r.label ? `<div class="small audit-label">${auditLog.esc(r.label)}</div>` : '');
                    }
                },
                {
                    key: 'changes',
                    className: 'audit-change',
                    render: r => auditLog.changes(r)
                },
                {
                    key: 'ip',
                    className: 'text-nowrap audit-muted',
                    render: r => auditLog.esc(r.ip || '—')
                },
            ],

            /** One entry on the timeline: "<who> <did what> <to what> • 2 hours ago", the change, then stamp, key and address. */
            event(row, index) {
                const esc = auditLog.esc;
                const newest = auditLog.page === 1 && index === 0;
                const what = row.label || row.entity;
                const lines = (row.changes || []).map(change =>
                    `<div class="font-monospace">${esc(change.field)}: ${change.old !== null ? esc(change.old) + ' → ' : ''}${esc(change.new)}</div>`
                ).join('');

                return `
                    <li class="timeline-event">
                        <div class="timeline-event-icon ${newest ? 'bg-green' : 'bg-red'} text-white fw-bold rounded-pill max-w-1"
                            style="width: 2rem; height: 2rem; left: 0.3rem; top: 0.25rem">
                            <i class="ti ti-sun"></i>
                        </div>
                        <div class="card timeline-event-card border-0 shadow-none">
                            <div class="card-body p-0">
                                <h4>
                                    <span class="text-facebook">${esc(row.user)}</span>
                                    ${esc(row.activity)}
                                    ${what ? `<span class="text-facebook">${esc(what)}</span>` : ''}
                                    &bull; ${esc(row.ago)}
                                </h4>
                                ${lines ? `<div class="card border-0 rounded"><div class="card-body p-2 mdka-bg-gray-50">${lines}</div></div>` : ''}
                                <div class="audit-muted audit-stamp mt-1">
                                    ${esc(row.at)}
                                    <span class="font-monospace ms-2">${esc(row.action)}</span>
                                    ${row.ip ? `<span class="ms-2">from ${esc(row.ip)}</span>` : ''}
                                    ${row.via ? `<span class="ms-2">via ${esc(row.via)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </li>`;
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
                $('[data-audit-view]').each(function() {
                    const on = $(this).data('audit-view') === auditLog.view;
                    $(this).toggleClass('btn-primary', on).toggleClass('btn-outline-secondary', !on).attr('aria-pressed', on ? 'true' : 'false');
                });

                const rows = auditLog.state === 'done' ? auditLog.rows : [];
                if (timeline) {
                    $('#auditTimeline').html(rows.length ?
                        rows.map((row, index) => auditLog.event(row, index)).join('') :
                        `<li class="list-unstyled text-center text-secondary py-5">${auditLog.esc(message)}</li>`);
                } else {
                    renderTable({
                        container: '#auditTable',
                        data: rows,
                        columns: auditLog.columns,
                        currentPage: auditLog.page,
                        limit: auditLog.limit,
                        emptyText: auditLog.esc(message),
                    });
                }

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
            auditLog.load();
        });
    </script>
@endpush
