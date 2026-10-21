@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', __('RadioManagement::service.title'))

@php
    // What the role may do on "Servis & Perbaikan" (the server checks again).
    $serviceMay = [
        'create' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.service', 'Create'),
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.service', 'Update'),
    ];
    // The next step of a ticket: the drawer's button, the dialog it opens (its words: RadioManagement::service.next).
    $serviceSteps = [];
    foreach (['sent' => 'truck-delivery', 'in_repair' => 'tool', 'ready' => 'package', 'returned' => 'arrow-back-up'] as $step => $icon) {
        $serviceSteps[$step] = __('RadioManagement::service.next.' . $step) + ['icon' => $icon];
    }
@endphp

@section('content')
    <div class="rm-card">
        @unless ($ready)
            <div class="rm-alert warning" id="serviceNotReady">
                <i class="ti ti-database-off" aria-hidden="true"></i>
                <span>{{ \Modules\RadioManagement\Http\Controllers\ServiceTicketController::notReadyText() }}</span>
            </div>
        @endunless

        <div class="rm-svc-stats" id="serviceStats" aria-live="polite">
            @foreach (__('RadioManagement::service.board.stats') as $label)
                <div class="rm-svc-stat">
                    <div class="rm-svc-stat-label">{{ $label }}</div>
                    <div class="rm-svc-stat-value">—</div>
                    <div class="rm-svc-stat-hint">&nbsp;</div>
                </div>
            @endforeach
        </div>

        <div class="rm-toolbar">
            <div class="rm-seg" role="radiogroup" aria-label="{{ __('RadioManagement::service.toolbar.view') }}">
                <input type="radio" class="btn-check" name="serviceView" id="serviceViewBoard" value="board" checked>
                <label for="serviceViewBoard"><i class="ti ti-layout-kanban" aria-hidden="true"></i> {{ __('RadioManagement::service.toolbar.board') }}</label>
                <input type="radio" class="btn-check" name="serviceView" id="serviceViewList" value="list">
                <label for="serviceViewList"><i class="ti ti-list" aria-hidden="true"></i> {{ __('RadioManagement::service.toolbar.list') }}</label>
            </div>
            <label class="rm-search rm-svc-search" for="serviceSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="serviceSearch" autocomplete="off" maxlength="100" placeholder="{{ __('RadioManagement::service.toolbar.search') }}"
                    aria-label="{{ __('RadioManagement::service.toolbar.search_label') }}" />
            </label>
            <div class="rm-field rm-svc-center">
                <select id="serviceCenter" class="form-select" aria-label="{{ __('RadioManagement::service.toolbar.center') }}">
                    <option value="">{{ __('RadioManagement::service.board.all_centers') }}</option>
                </select>
            </div>
            <button type="button" class="rm-svc-clear d-none" id="serviceClear">{{ __('RadioManagement::service.toolbar.clear') }}</button>
            <div class="rm-end">
                <a class="btn-rm secondary" id="serviceExport" href="{{ route('radio-management.service.export') }}">
                    <i class="ti ti-download" aria-hidden="true"></i> {{ __('RadioManagement::service.toolbar.export') }}
                </a>
                @if ($serviceMay['create'])
                    <button type="button" class="btn-rm primary" id="serviceNew"@disabled(! $ready)>
                        <i class="ti ti-plus" aria-hidden="true"></i> {{ __('RadioManagement::service.toolbar.new') }}
                    </button>
                @endif
            </div>
        </div>

        <div class="rm-svc-board" id="serviceBoard" aria-live="polite"></div>

        <div class="d-none" id="serviceListWrap">
            <div class="rm-box">
                <div class="table-responsive">
                    <table class="rm-table rm-svc-table" id="serviceTable">
                        <thead>
                            <tr>
                                <th width="120">{{ __('RadioManagement::service.columns.ticket') }}</th>
                                <th width="130">{{ __('RadioManagement::service.columns.radio') }}</th>
                                <th>{{ __('RadioManagement::service.columns.issue') }}</th>
                                <th>{{ __('RadioManagement::service.columns.reported_by') }}</th>
                                <th width="190">{{ __('RadioManagement::service.columns.center') }}</th>
                                <th width="140">{{ __('RadioManagement::service.columns.status') }}</th>
                                <th width="100" class="text-end">{{ __('RadioManagement::service.columns.days') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="rm-foot" id="serviceListFoot"></div>
            </div>
        </div>
    </div>

    {{-- A ticket: where it is (the steps), what is known of it, what happened. Closed with a click outside or Esc (K11). --}}
    <div class="offcanvas offcanvas-end rm-drawer" tabindex="-1" id="serviceDrawer" aria-labelledby="serviceDrawerTitle">
        <div class="rm-drawer-head">
            <div class="rm-drawer-titles">
                <div class="rm-drawer-no" id="serviceDrawerNo"></div>
                <h2 class="rm-drawer-title" id="serviceDrawerTitle">{{ __('RadioManagement::service.drawer.title') }}</h2>
                <div class="rm-drawer-sub" id="serviceDrawerSub"></div>
            </div>
            <button type="button" class="rm-drawer-close" data-bs-dismiss="offcanvas" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i class="ti ti-x"></i></button>
        </div>
        <div class="rm-drawer-body" id="serviceDrawerBody"></div>
        @if ($serviceMay['update'])
            <div class="rm-drawer-foot">
                <button type="button" class="btn-rm secondary" id="serviceNoteButton"><i class="ti ti-note" aria-hidden="true"></i> {{ __('RadioManagement::service.drawer.add_note') }}</button>
                <button type="button" class="btn-rm primary ms-auto d-none" id="serviceNextButton"></button>
            </div>
        @endif
    </div>

    @if ($serviceMay['create'])
        {{-- A new ticket. Static, as every form (K11): what was typed is not lost by a click beside it. --}}
        <div class="modal fade rm-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="serviceNewModal" tabindex="-1"
            aria-labelledby="serviceNewTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="rm-dialog-head is-ruled">
                        <span class="rm-dialog-icon"><i class="ti ti-tool" aria-hidden="true"></i></span>
                        <div class="rm-dialog-titles">
                            <h3 class="rm-dialog-title modal-title" id="serviceNewTitle">{{ __('RadioManagement::service.new.title') }}</h3>
                            <p class="rm-dialog-sub">{{ __('RadioManagement::service.new.sub') }}</p>
                        </div>
                        <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i class="ti ti-x"></i></button>
                    </div>
                    <div class="rm-dialog-body is-form">
                        <div class="rm-field">
                            <label for="serviceRadioSearch" class="form-label required">{{ __('RadioManagement::service.new.radio') }}</label>
                            <div class="rm-search w-100" id="serviceRadioSearchWrap">
                                <i class="ti ti-search" aria-hidden="true"></i>
                                <input type="search" id="serviceRadioSearch" maxlength="60" autocomplete="off" placeholder="{{ __('RadioManagement::service.new.radio_hint') }}">
                            </div>
                            <div id="serviceRadioMatches" aria-live="polite"></div>
                            <div class="rm-svc-picked d-none" id="serviceRadioPicked"></div>
                        </div>
                        <div class="rm-field">
                            <label for="serviceIssue" class="form-label required">{{ __('RadioManagement::service.new.issue') }}</label>
                            <textarea id="serviceIssue" class="form-control" rows="3" maxlength="500" placeholder="{{ __('RadioManagement::service.new.issue_hint') }}"></textarea>
                        </div>
                        <div class="rm-field">
                            <label for="serviceReporter" class="form-label">{{ __('RadioManagement::service.new.reporter') }}</label>
                            <input type="text" id="serviceReporter" class="form-control" maxlength="150" autocomplete="off" placeholder="{{ __('RadioManagement::service.new.reporter_hint') }}">
                            <div class="rm-hint">{{ __('RadioManagement::service.new.reporter_help') }}</div>
                        </div>
                        <div role="alert">
                            <div class="rm-alert danger d-none" id="serviceNewError">
                                <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                                <span id="serviceNewErrorText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="rm-dialog-foot">
                        <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                        <button type="button" class="btn-rm primary" id="serviceNewSave" disabled><i class="ti ti-check" aria-hidden="true"></i> {{ __('RadioManagement::service.new.save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($serviceMay['update'])
        {{-- The next step of a ticket, with what that step asks for (the fields of the other steps are hidden). --}}
        <div class="modal fade rm-modal rm-svc-move" data-bs-backdrop="static" data-bs-keyboard="false" id="serviceMoveModal" tabindex="-1"
            aria-labelledby="serviceMoveTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="rm-dialog-head is-ruled">
                        <span class="rm-dialog-icon" id="serviceMoveIcon"><i class="ti ti-truck-delivery" aria-hidden="true"></i></span>
                        <div class="rm-dialog-titles">
                            <h3 class="rm-dialog-title modal-title" id="serviceMoveTitle">{{ __('RadioManagement::service.move.title') }}</h3>
                            <p class="rm-dialog-sub" id="serviceMoveSub"></p>
                        </div>
                        <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i class="ti ti-x"></i></button>
                    </div>
                    <div class="rm-dialog-body is-form">
                        <div class="row g-3">
                            <div class="col-12 rm-field svc-field svc-for-sent">
                                <label for="serviceCenterInput" class="form-label required">{{ __('RadioManagement::service.move.center') }}</label>
                                <input type="text" id="serviceCenterInput" class="form-control" maxlength="150" autocomplete="off" list="serviceCenterList"
                                    placeholder="{{ __('RadioManagement::service.move.center_hint') }}">
                                <datalist id="serviceCenterList"></datalist>
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-sent">
                                <label for="serviceSentAt" class="form-label">{{ __('RadioManagement::service.move.sent_at') }}</label>
                                <input type="hidden" id="serviceSentAt">
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-sent">
                                <label for="serviceBa" class="form-label">{{ __('RadioManagement::service.move.ba') }}</label>
                                <input type="text" id="serviceBa" class="form-control radio-mono" maxlength="60" autocomplete="off" placeholder="BA2300418">
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-returned">
                                <label for="serviceReturnedAt" class="form-label">{{ __('RadioManagement::service.move.returned_at') }}</label>
                                <input type="hidden" id="serviceReturnedAt">
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-sent svc-for-in_repair">
                                <label for="serviceExpected" class="form-label">{{ __('RadioManagement::service.move.expected') }}</label>
                                <input type="hidden" id="serviceExpected">
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-sent svc-for-in_repair">
                                <label for="serviceQuote" class="form-label">{{ __('RadioManagement::service.move.quote') }}</label>
                                <input type="number" id="serviceQuote" class="form-control" min="0" step="1000" inputmode="numeric" placeholder="0">
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-returned">
                                <label for="servicePlacement" class="form-label required">{{ __('RadioManagement::service.move.placement') }}</label>
                                <select id="servicePlacement" class="form-select">
                                    @foreach ($placements as $placement)
                                        <option value="{{ $placement['value'] }}">{{ $placement['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-returned">
                                <label for="serviceCondition" class="form-label required">{{ __('RadioManagement::service.move.condition') }}</label>
                                <select id="serviceCondition" class="form-select">
                                    @foreach ($conditions as $condition)
                                        <option value="{{ $condition['value'] }}">{{ $condition['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 rm-field svc-field svc-for-ready svc-for-returned">
                                <label for="serviceCost" class="form-label">{{ __('RadioManagement::service.move.cost') }}</label>
                                <input type="number" id="serviceCost" class="form-control" min="0" step="1000" inputmode="numeric" placeholder="0">
                            </div>
                            <div class="col-12 svc-field svc-for-sent">
                                <div class="rm-alert">
                                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                                    <span>{!! __('RadioManagement::service.move.sent_note') !!}</span>
                                </div>
                            </div>
                            <div class="col-12 svc-field svc-for-returned">
                                <div class="rm-alert">
                                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                                    <span>{{ __('RadioManagement::service.move.returned_note') }}</span>
                                </div>
                            </div>
                        </div>
                        <div role="alert">
                            <div class="rm-alert danger d-none" id="serviceMoveError">
                                <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                                <span id="serviceMoveErrorText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="rm-dialog-foot">
                        <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                        <button type="button" class="btn-rm primary" id="serviceMoveSave"><i class="ti ti-check" aria-hidden="true"></i> {{ __('RadioManagement::common.ui.save') }}</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- A note on the ticket. --}}
        <div class="modal fade rm-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="serviceNoteModal" tabindex="-1"
            aria-labelledby="serviceNoteTitle" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="rm-dialog-head is-ruled">
                        <span class="rm-dialog-icon"><i class="ti ti-note" aria-hidden="true"></i></span>
                        <div class="rm-dialog-titles">
                            <h3 class="rm-dialog-title modal-title" id="serviceNoteTitle">{{ __('RadioManagement::service.note.title') }}</h3>
                            <p class="rm-dialog-sub" id="serviceNoteSub"></p>
                        </div>
                        <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i class="ti ti-x"></i></button>
                    </div>
                    <div class="rm-dialog-body is-form">
                        <div class="rm-field">
                            <label for="serviceNoteText" class="form-label required">{{ __('RadioManagement::service.note.note') }}</label>
                            <textarea id="serviceNoteText" class="form-control" rows="4" maxlength="1000" placeholder="{{ __('RadioManagement::service.note.note_hint') }}"></textarea>
                        </div>
                        <div role="alert">
                            <div class="rm-alert danger d-none" id="serviceNoteError">
                                <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                                <span id="serviceNoteErrorText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="rm-dialog-foot">
                        <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">{{ __('RadioManagement::common.ui.cancel') }}</button>
                        <button type="button" class="btn-rm primary" id="serviceNoteSave" disabled><i class="ti ti-check" aria-hidden="true"></i> {{ __('RadioManagement::service.note.save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('javascript')
    <script>
        const serviceBoard = {
            urls: {
                list: @js(route('radio-management.service.list')),
                show: @js(route('radio-management.service.show', ['ticket' => '__id__'])),
                status: @js(route('radio-management.service.status', ['ticket' => '__id__'])),
                note: @js(route('radio-management.service.note', ['ticket' => '__id__'])),
                radios: @js(route('radio-management.service.radios')),
                store: @js(route('radio-management.service.store')),
                export: @js(route('radio-management.service.export')),
            },
            ready: @js($ready),
            may: @js($serviceMay),
            /** What the script writes, in the language of the page (RadioManagement::service.board). */
            words: @json(__('RadioManagement::service.board')),
            /** The steps, in their order, as the server names them; the board has a column for each but the last. */
            statuses: @js($statuses),
            overdueDays: @js($overdueDays),
            /** The steps in the stepper of the drawer: short words. */
            stepWords: @json(__('RadioManagement::service.steps')),
            tones: { reported: 'gray', sent: 'blue', in_repair: 'orange', ready: 'green', returned: 'gray' },
            /** The next step: the drawer's button, and the dialog it opens. */
            steps: @js($serviceSteps),
            /** Cards a column shows before "N more". */
            LIMIT: 3,

            rows: [],
            stats: null,
            centers: [],
            view: 'board',
            search: '',
            center: '',
            /** The columns opened with "N more". */
            expanded: {},
            /** The keyframe the content faded in with last: the other one starts the animation again. */
            flip: 'a',
            /** The ticket in the drawer, as the server gave it. */
            ticket: null,
            /** The latest requests: an older answer is dropped. */
            asked: 0,
            askedTicket: 0,
            /** A dialog of the ticket is open: the drawer has stepped aside, and comes back when it closes. */
            returning: false,

            esc(value) {
                return RadioUi.esc(value);
            },

            init() {
                const params = new URLSearchParams(location.search);
                serviceBoard.view = params.get('view') === 'list' ? 'list' : 'board';
                $('#serviceViewBoard').prop('checked', serviceBoard.view === 'board');
                $('#serviceViewList').prop('checked', serviceBoard.view === 'list');

                $('input[name="serviceView"]').on('change', function() {
                    serviceBoard.setView($(this).val());
                });
                $('#serviceSearch').on('input', debounce(() => {
                    serviceBoard.search = String($('#serviceSearch').val() || '').trim();
                    serviceBoard.draw();
                }, 150));
                $('#serviceCenter').on('change', () => {
                    serviceBoard.center = String($('#serviceCenter').val() || '');
                    serviceBoard.draw();
                });
                $('#serviceClear').on('click', () => serviceBoard.clear());
                $('#serviceBoard').on('click', '[data-ticket]', function() {
                    serviceBoard.openTicket(Number($(this).data('ticket')));
                });
                $('#serviceBoard').on('click', '[data-more]', function() {
                    const status = String($(this).data('more'));
                    serviceBoard.expanded[status] = !serviceBoard.expanded[status];
                    serviceBoard.draw(false, serviceBoard.expanded[status] ? status : null);
                });
                $('#serviceTable tbody').on('click', 'tr[data-ticket]', function() {
                    serviceBoard.openTicket(Number($(this).data('ticket')));
                });
                $('#serviceNextButton').on('click', () => serviceMove.open());
                $('#serviceNoteButton').on('click', () => serviceNote.open());
                $('#serviceDrawer').on('hidden.bs.offcanvas', () => {
                    // (Stepping aside for a dialog of the ticket, it keeps the ticket.)
                    if (serviceBoard.returning) return;
                    serviceBoard.ticket = null;
                    serviceBoard.remember();
                });
                $('#serviceMoveModal, #serviceNoteModal').on('hidden.bs.modal', () => {
                    if (!serviceBoard.returning) return;
                    serviceBoard.returning = false;
                    if (serviceBoard.ticket) $('#serviceDrawer').offcanvas('show');
                });

                if (!serviceBoard.ready) return;
                serviceBoard.load();
                const ticket = Number(params.get('ticket'));
                if (ticket > 0) serviceBoard.openTicket(ticket);
            },

            load() {
                const mine = ++serviceBoard.asked;
                $.getJSON(serviceBoard.urls.list).done(response => {
                    if (mine !== serviceBoard.asked) return;
                    serviceBoard.rows = Array.isArray(response.data) ? response.data : [];
                    serviceBoard.stats = response.stats || null;
                    serviceBoard.centers = Array.isArray(response.centers) ? response.centers : [];
                    serviceBoard.drawStats();
                    serviceBoard.drawCenters();
                    serviceBoard.draw();
                }).fail(xhr => {
                    if (mine !== serviceBoard.asked) return;
                    $('#serviceBoard').html(`<div class="rm-alert danger"><i class="ti ti-alert-triangle" aria-hidden="true"></i><span>${RadioUi.errors(xhr).join('<br>')}</span></div>`);
                });
            },

            /** A filter is on: the search, or a service center. */
            filtering() {
                return serviceBoard.search !== '' || serviceBoard.center !== '';
            },

            matches(row) {
                if (serviceBoard.center !== '' && row.service_center !== serviceBoard.center) return false;
                const typed = serviceBoard.search.toLowerCase();
                if (typed === '') return true;
                return [row.number, row.radio, row.dmr_id, row.sn, row.type, row.issue, row.reported_by, row.service_center]
                    .some(value => value !== null && value !== undefined && String(value).toLowerCase().includes(typed));
            },

            /** Draws the board or the list; `fade`: the view was switched; `rising`: the column just opened with "N more". */
            draw(fade = false, rising = null) {
                const shown = serviceBoard.rows.filter(serviceBoard.matches);
                const list = serviceBoard.view === 'list';
                $('#serviceClear').toggleClass('d-none', !serviceBoard.filtering());
                $('#serviceExport').attr('href', serviceBoard.exportUrl());
                $('#serviceBoard').toggleClass('d-none', list);
                $('#serviceListWrap').toggleClass('d-none', !list);
                if (list) {
                    serviceBoard.drawList(shown);
                } else {
                    serviceBoard.drawBoard(shown, rising);
                }
                if (fade) {
                    serviceBoard.flip = serviceBoard.flip === 'a' ? 'b' : 'a';
                    $(list ? '#serviceListWrap' : '#serviceBoard').removeClass('rm-fade-a rm-fade-b').addClass(`rm-fade-${serviceBoard.flip}`);
                }
            },

            drawBoard(shown, rising) {
                const esc = serviceBoard.esc;
                const words = serviceBoard.words;
                const filtering = serviceBoard.filtering();
                const columns = Object.keys(serviceBoard.statuses).filter(status => status !== 'returned');
                $('#serviceBoard').html(columns.map(status => {
                    const all = shown.filter(row => row.status === status);
                    const open = !!serviceBoard.expanded[status];
                    const cards = filtering || open ? all : all.slice(0, serviceBoard.LIMIT);
                    const rest = all.length - serviceBoard.LIMIT;
                    const more = !filtering && rest > 0 ?
                        `<button type="button" class="rm-svc-more" data-more="${status}" aria-expanded="${open}">${esc(open ? words.hide : RadioUi.fill(words.more, { count: rest }))}` +
                        `<i class="ti ti-chevron-${open ? 'up' : 'down'}" aria-hidden="true"></i></button>` : '';
                    const body = cards.length ?
                        cards.map((row, index) => serviceBoard.card(row, rising === status && index >= serviceBoard.LIMIT ? index - serviceBoard.LIMIT : null)).join('') :
                        `<div class="rm-svc-none">${esc(filtering ? words.no_match : words.no_tickets)}</div>`;
                    return `<div class="rm-svc-col" data-status="${status}">` +
                        `<div class="rm-svc-col-head"><span class="rm-svc-dot" aria-hidden="true"></span>` +
                        `<span class="rm-svc-col-title">${esc(serviceBoard.statuses[status])}</span><span class="rm-svc-count">${all.length}</span></div>` +
                        `${body}${more}</div>`;
                }).join(''));
            },

            /** A ticket on the board; `extra`: its place among the cards that "N more" has just shown (they rise in turn). */
            card(row, extra = null) {
                const esc = serviceBoard.esc;
                const late = !!row.overdue;
                return `<button type="button" class="rm-svc-card${late ? ' is-late' : ''}${extra !== null ? ' is-extra' : ''}" data-ticket="${Number(row.id)}"${extra !== null ? ` style="--i: ${extra}"` : ''}>` +
                    `<span class="rm-svc-card-top"><span class="rm-svc-radio">${esc(row.radio)}</span><span class="rm-svc-no">${esc(row.number)}</span>` +
                    `${row.type ? `<span class="rm-badge gray">${esc(row.type)}</span>` : ''}</span>` +
                    `<span class="rm-svc-issue">${esc(row.issue)}</span>` +
                    `<span class="rm-svc-who">${esc(row.reported_by || '—')}</span>` +
                    `<span class="rm-svc-foot"><span class="rm-svc-vendor">${esc(row.service_center || serviceBoard.words.not_sent)}</span>` +
                    `<span class="rm-svc-days${late ? ' is-late' : ''}">${serviceBoard.days(row.days)}</span></span>` +
                    '</button>';
            },

            drawList(shown) {
                const esc = serviceBoard.esc;
                const words = serviceBoard.words;
                const rows = shown.map(row => `<tr data-ticket="${Number(row.id)}">` +
                    `<td><button type="button" class="rm-svc-rowlink">${esc(row.number)}</button></td>` +
                    `<td class="rm-svc-two"><b>${esc(row.radio)}</b><span>${esc(row.type || '')}</span></td>` +
                    `<td class="rm-svc-issue-cell">${esc(row.issue)}</td>` +
                    `<td>${RadioUi.dash(row.reported_by)}</td>` +
                    `<td>${row.service_center ? esc(row.service_center) : `<span class="radio-none">${esc(words.not_sent)}</span>`}</td>` +
                    `<td>${RadioUi.badge(row.status_label, serviceBoard.tones[row.status] || 'gray')}</td>` +
                    `<td class="text-end"><span class="rm-badge ${row.overdue ? 'red' : 'gray'}">${serviceBoard.days(row.days)}</span></td>` +
                    '</tr>');
                const filtering = serviceBoard.filtering();
                $('#serviceTable tbody').html(rows.length ? rows.join('') :
                    `<tr><td colspan="7" class="text-center radio-none py-4">${esc(filtering ? words.list_no_match : words.list_none)}</td></tr>`);
                $('#serviceListFoot').text(!shown.length ? (filtering ? words.foot_no_match : words.foot_none) :
                    (filtering ? RadioUi.choice(words.foot_matches, shown.length) : RadioUi.choice(words.foot_all, serviceBoard.rows.length)));
            },

            drawStats() {
                const esc = serviceBoard.esc;
                const words = serviceBoard.words;
                const stats = serviceBoard.stats || {};
                const count = value => rmMotion.number(value || 0);
                const cells = [
                    [words.stats.open, count(stats.open), RadioUi.fill(words.open_hint, { reported: count(stats.reported), away: count(stats.away) }), false],
                    [words.stats.turnaround, stats.turnaround === null || stats.turnaround === undefined ? '—' :
                        RadioUi.choice(words.days, stats.turnaround, { count: count(stats.turnaround) }), words.turnaround_hint, false],
                    [words.stats.overdue, count(stats.overdue), RadioUi.fill(words.overdue_hint, { days: serviceBoard.overdueDays }), Number(stats.overdue) > 0],
                    [words.stats.cost, serviceBoard.money(stats.cost), RadioUi.choice(words.closed_hint, stats.closed || 0, { count: count(stats.closed) }), false],
                ];
                $('#serviceStats').html(cells.map(([label, value, hint, red]) => '<div class="rm-svc-stat">' +
                    `<div class="rm-svc-stat-label">${esc(label)}</div><div class="rm-svc-stat-value${red ? ' is-red' : ''}">${esc(value)}</div>` +
                    `<div class="rm-svc-stat-hint">${esc(hint)}</div></div>`).join(''));
            },

            /** The filter of service centers, and what the field of the dialog offers: those written on tickets so far. */
            drawCenters() {
                const esc = serviceBoard.esc;
                if (!serviceBoard.centers.includes(serviceBoard.center)) serviceBoard.center = '';
                $('#serviceCenter').html([`<option value="">${esc(serviceBoard.words.all_centers)}</option>`]
                    .concat(serviceBoard.centers.map(center => `<option value="${esc(center)}">${esc(center)}</option>`)).join(''))
                    .val(serviceBoard.center);
                $('#serviceCenterList').html(serviceBoard.centers.map(center => `<option value="${esc(center)}"></option>`).join(''));
            },

            setView(view) {
                serviceBoard.view = view === 'list' ? 'list' : 'board';
                serviceBoard.remember();
                serviceBoard.draw(true);
            },

            clear() {
                serviceBoard.search = '';
                serviceBoard.center = '';
                $('#serviceSearch').val('');
                $('#serviceCenter').val('');
                serviceBoard.draw();
            },

            /** The view and the ticket that is open are in the address, so that a link (or a reload) shows them again. */
            remember() {
                const params = new URLSearchParams(location.search);
                if (serviceBoard.view === 'list') params.set('view', 'list');
                else params.delete('view');
                if (serviceBoard.ticket) params.set('ticket', String(serviceBoard.ticket.id));
                else params.delete('ticket');
                const query = params.toString();
                history.replaceState(null, '', location.pathname + (query ? `?${query}` : ''));
            },

            /** The export takes the filters that are on. */
            exportUrl() {
                const filters = {};
                if (serviceBoard.search !== '') filters.q = serviceBoard.search;
                if (serviceBoard.center !== '') filters.center = serviceBoard.center;
                const query = $.param(filters);
                return serviceBoard.urls.export + (query ? `?${query}` : '');
            },

            days(value) {
                return RadioUi.choice(serviceBoard.words.days, Number(value) || 0);
            },

            rupiah(value) {
                return `Rp ${rmMotion.number(value)}`;
            },

            /** "Rp 14,2 jt", "Rp 1,2 M", "Rp 850.000" (in English "Rp 14.2M", "Rp 1.2B", "Rp 850,000"). */
            money(value) {
                const words = serviceBoard.words;
                const amount = Number(value) || 0;
                const short = (number, unit) => RadioUi.fill(words.money, { amount: String(Math.round(number * 10) / 10).replace('.', words.decimal), unit });
                if (amount >= 1e9) return short(amount / 1e9, words.billion);
                if (amount >= 1e6) return short(amount / 1e6, words.million);
                return serviceBoard.rupiah(amount);
            },

            /** "18 Okt 2026, 16:40" of "2026-10-18 16:40". */
            when(at) {
                const [day, time] = String(at || '').split(' ');
                return day ? `${rmPickers.label(day)}${time ? `, ${time}` : ''}` : '';
            },

            /** What went wrong, as text (it is put in with .text()). */
            plain(xhr) {
                const body = xhr.responseJSON || {};
                if (Array.isArray(body.errors)) return body.errors.join(' ');
                if (body.errors && typeof body.errors === 'object') return Object.values(body.errors).flat().join(' ');
                if (xhr.status === 403) return RadioUi.words.no_permission;
                return body.message || RadioUi.words.error;
            },

            /**
             * Opens a dialog of the ticket in the drawer. The drawer steps aside meanwhile: two layers would fight over the
             * focus (Bootstrap's drawer keeps it inside itself), and the dialog is what is being done.
             */
            aside(modal) {
                serviceBoard.returning = true;
                $('#serviceDrawer').offcanvas('hide');
                $(modal).modal('show');
            },

            openTicket(id) {
                if (!(id > 0)) return;
                const mine = ++serviceBoard.askedTicket;
                $.getJSON(serviceBoard.urls.show.replace('__id__', id)).done(response => {
                    if (mine !== serviceBoard.askedTicket) return;
                    serviceBoard.ticket = response.data;
                    serviceBoard.drawTicket();
                    $('#serviceDrawer').offcanvas('show');
                    serviceBoard.remember();
                }).fail(xhr => {
                    if (mine !== serviceBoard.askedTicket) return;
                    rmFeedback.toast(xhr.status === 404 ? serviceBoard.words.not_found : serviceBoard.plain(xhr), 'error');
                });
            },

            drawTicket() {
                const esc = serviceBoard.esc;
                const words = serviceBoard.words;
                const ticket = serviceBoard.ticket;
                if (!ticket) return;
                $('#serviceDrawerNo').text(ticket.number);
                $('#serviceDrawerTitle').text(`Radio ${ticket.radio} · ${ticket.issue}`);
                $('#serviceDrawerSub').text([ticket.type, ticket.sn ? `SN ${ticket.sn}` : '', ticket.reported_by].filter(Boolean).join(' · '));

                const steps = Object.keys(serviceBoard.statuses);
                const closed = ticket.status === 'returned';
                const at = steps.indexOf(ticket.status);
                const stepper = steps.map((status, index) => {
                    const done = closed || index < at;
                    const on = !closed && index === at;
                    return `<div class="rm-step${done ? ' is-done' : ''}${on ? ' is-on' : ''}"${on ? ' aria-current="step"' : ''}>` +
                        `<span class="rm-step-dot">${done ? '<i class="ti ti-check" aria-hidden="true"></i>' : index + 1}</span>` +
                        `<span class="rm-step-label">${esc(serviceBoard.stepWords[status] || status)}</span></div>`;
                }).join('');

                const none = '<span class="radio-none">—</span>';
                const date = iso => (iso ? esc(rmPickers.label(iso)) : none);
                const fact = (name, value, mono = false) => `<dt>${esc(name)}</dt><dd${mono ? ' class="is-mono"' : ''}>${value}</dd>`;
                const radio = ticket.radio_now;
                const radioState = !radio ? words.not_in_register : (radio.gone ? words.deleted :
                    RadioUi.fill(words.radio_state, { placement: radio.placement || '—', condition: radio.condition || '—' }) + (radio.archived ? ` ${words.archived}` : ''));
                const facts = [
                    fact(words.issue, esc(ticket.issue)),
                    fact(words.reported, `${date(ticket.created_at)}${ticket.reported_by ? ` · ${esc(ticket.reported_by)}` : ''}`),
                    fact(words.center, ticket.service_center ? esc(ticket.service_center) : `<span class="radio-none">${esc(words.not_sent)}</span>`),
                    fact(words.sent, ticket.sent_at ? `${date(ticket.sent_at)}${ticket.document_no ? ` · ${esc(ticket.document_no)}` : ''}` : none, !!ticket.sent_at),
                    fact(words.expected, date(ticket.expected_at)),
                    fact(words.quote, ticket.quote !== null && ticket.quote !== undefined ? esc(serviceBoard.rupiah(ticket.quote)) : none),
                    ...(ticket.cost !== null && ticket.cost !== undefined ? [fact(words.cost, esc(serviceBoard.rupiah(ticket.cost)))] : []),
                    ...(ticket.returned_at ? [fact(words.returned, date(ticket.returned_at))] : []),
                    fact(words.radio_status, esc(radioState) + (radio && radio.url ? ` · <a href="${esc(radio.url)}">${esc(words.open_radio)}</a>` : '')),
                ].join('');

                const events = (Array.isArray(ticket.events) ? ticket.events : []).map(event => {
                    const note = event.kind === 'note';
                    const meta = [note ? words.note : '', serviceBoard.when(event.at), event.user || ''].filter(Boolean).join(' · ');
                    return `<div class="rm-svc-act"><span class="rm-svc-act-dot is-${note ? 'note' : esc(event.status)}" aria-hidden="true"></span>` +
                        `<div class="rm-svc-act-text"><div class="rm-svc-act-title${note ? ' is-note' : ''}">${esc(event.note || serviceBoard.statuses[event.status] || '')}</div>` +
                        `<div class="rm-svc-act-meta">${esc(meta)}</div></div></div>`;
                }).join('');

                $('#serviceDrawerBody').html(`<div class="rm-steps">${stepper}</div><dl class="rm-svc-facts">${facts}</dl>` +
                    `<div><div class="rm-svc-acts-title">${esc(words.activity)}</div>${events || `<div class="radio-none">${esc(words.no_activity)}</div>`}</div>`);

                const next = ticket.next && serviceBoard.steps[ticket.next];
                $('#serviceNextButton').toggleClass('d-none', !next)
                    .html(next ? `<i class="ti ti-${next.icon}" aria-hidden="true"></i> ${esc(next.button)}` : '');
            },
        };

        // A new ticket: the radio is looked up in the register (not archived), the issue typed.
        const serviceForm = {
            asked: 0,
            found: [],
            radio: null,

            open() {
                serviceForm.radio = null;
                serviceForm.found = [];
                $('#serviceRadioSearch, #serviceIssue, #serviceReporter').val('');
                $('#serviceRadioMatches').empty();
                $('#serviceRadioPicked').addClass('d-none').empty();
                $('#serviceRadioSearchWrap').removeClass('d-none');
                serviceForm.error('');
                serviceForm.ready();
                $('#serviceNewModal').modal('show');
            },

            error(text) {
                $('#serviceNewErrorText').text(text);
                $('#serviceNewError').toggleClass('d-none', !text);
            },

            /** Saved only with a radio and the issue. */
            ready() {
                const ok = !!serviceForm.radio && String($('#serviceIssue').val() || '').trim() !== '';
                $('#serviceNewSave').prop('disabled', !ok);
                return ok;
            },

            /** "ID 751 · P8668i"; a radio without a Radio ID by its SN. */
            name(radio) {
                return [radio.dmr_id ? `ID ${radio.dmr_id}` : (radio.sn ? `SN ${radio.sn}` : serviceBoard.words.no_id), radio.type].filter(Boolean).join(' · ');
            },

            /** Its SN (when the name is its ID) and who has it. */
            line(radio) {
                return [radio.dmr_id ? (radio.sn ? `SN ${radio.sn}` : serviceBoard.words.no_sn) : '', radio.holder].filter(Boolean).join(' · ');
            },

            lookup() {
                const esc = serviceBoard.esc;
                const typed = String($('#serviceRadioSearch').val() || '').trim();
                const mine = ++serviceForm.asked;
                if (typed === '') {
                    $('#serviceRadioMatches').empty();
                    return;
                }
                $.getJSON(serviceBoard.urls.radios, { q: typed }).done(response => {
                    if (mine !== serviceForm.asked) return;
                    serviceForm.found = Array.isArray(response.data) ? response.data : [];
                    $('#serviceRadioMatches').html(serviceForm.found.length ? `<div class="rm-matches mt-2">${serviceForm.found.map((radio, index) =>
                        `<button type="button" class="rm-match" data-pick="${index}"${radio.open ? ' disabled' : ''}>` +
                        `<span class="rm-match-name">${esc(serviceForm.name(radio))}</span>` +
                        `<span class="rm-match-line">${esc(serviceForm.line(radio))}</span>` +
                        `<span class="rm-match-id">${esc(radio.open ? RadioUi.fill(serviceBoard.words.still_open, { number: radio.open }) : (radio.placement || ''))}</span></button>`
                    ).join('')}</div>` : `<div class="rm-hint mt-2">${esc(serviceBoard.words.no_radio)}</div>`);
                }).fail(xhr => {
                    if (mine !== serviceForm.asked) return;
                    $('#serviceRadioMatches').html(`<div class="rm-hint mt-2">${serviceBoard.esc(serviceBoard.plain(xhr))}</div>`);
                });
            },

            pick(index) {
                const esc = serviceBoard.esc;
                const radio = serviceForm.found[index];
                if (!radio || radio.open) return;
                serviceForm.radio = radio;
                $('#serviceRadioMatches').empty();
                $('#serviceRadioSearchWrap').addClass('d-none');
                $('#serviceRadioPicked').removeClass('d-none').html(
                    `<div><span class="rm-match-name">${esc(serviceForm.name(radio))}</span>` +
                    `<span class="rm-match-line">${esc(serviceForm.line(radio))}</span></div>` +
                    `<button type="button" class="btn-rm sm secondary" data-change="1">${esc(serviceBoard.words.change)}</button>`);
                if (String($('#serviceReporter').val() || '').trim() === '') $('#serviceReporter').val(radio.holder || '');
                serviceForm.ready();
                $('#serviceIssue').trigger('focus');
            },

            change() {
                serviceForm.radio = null;
                $('#serviceRadioPicked').addClass('d-none').empty();
                $('#serviceRadioSearchWrap').removeClass('d-none');
                $('#serviceRadioSearch').val('').trigger('focus');
                serviceForm.ready();
            },

            save() {
                if (!serviceForm.ready()) return;
                serviceForm.error('');
                rmFeedback.busy('#serviceNewSave', true);
                $.ajax({
                    url: serviceBoard.urls.store,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        radio_id: serviceForm.radio.id,
                        issue: String($('#serviceIssue').val() || '').trim(),
                        reported_by: String($('#serviceReporter').val() || '').trim(),
                    },
                }).done(response => {
                    $('#serviceNewModal').modal('hide');
                    rmFeedback.toast(response.message);
                    serviceBoard.load();
                    if (response.data && response.data.id) serviceBoard.openTicket(Number(response.data.id));
                }).fail(xhr => {
                    serviceForm.error(serviceBoard.plain(xhr));
                }).always(() => {
                    rmFeedback.busy('#serviceNewSave', false);
                    serviceForm.ready();
                });
            },
        };

        // The next step of the ticket in the drawer.
        const serviceMove = {
            status: null,

            open() {
                const ticket = serviceBoard.ticket;
                if (!ticket || !ticket.next || !serviceBoard.steps[ticket.next]) return;
                const step = serviceBoard.steps[ticket.next];
                const today = rmPickers.iso(new Date());
                serviceMove.status = ticket.next;
                $('#serviceMoveTitle').text(step.title);
                $('#serviceMoveSub').text(`${ticket.number} · Radio ${ticket.radio}`);
                $('#serviceMoveIcon').html(`<i class="ti ti-${step.icon}" aria-hidden="true"></i>`);
                $('#serviceMoveModal .svc-field').addClass('d-none');
                $(`#serviceMoveModal .svc-for-${ticket.next}`).removeClass('d-none');
                $('#serviceCenterInput').val(ticket.service_center || '');
                $('#serviceSentAt').val(today).trigger('change');
                $('#serviceBa').val(ticket.document_no || '');
                $('#serviceExpected').val(ticket.expected_at || '').trigger('change');
                $('#serviceQuote').val(ticket.quote ?? '');
                $('#serviceCost').val(ticket.cost ?? '');
                $('#serviceReturnedAt').val(today).trigger('change');
                $('#servicePlacement').val(['di_user', 'stok_it'].includes(ticket.placement_before) ? ticket.placement_before : 'di_user');
                $('#serviceCondition').val('layak');
                $('#serviceMoveSave').html(`<i class="ti ti-check" aria-hidden="true"></i> ${serviceBoard.esc(step.save)}`);
                serviceMove.error('');
                serviceBoard.aside('#serviceMoveModal');
            },

            error(text) {
                $('#serviceMoveErrorText').text(text);
                $('#serviceMoveError').toggleClass('d-none', !text);
            },

            save() {
                const ticket = serviceBoard.ticket;
                const status = serviceMove.status;
                if (!ticket || !status) return;
                if (status === 'sent' && String($('#serviceCenterInput').val() || '').trim() === '') {
                    serviceMove.error(serviceBoard.words.center_needed);
                    return;
                }
                const values = { _method: 'PUT', status };
                const take = (key, field) => {
                    const value = String($(field).val() ?? '').trim();
                    if (value !== '') values[key] = value;
                };
                if (status === 'sent') {
                    take('service_center', '#serviceCenterInput');
                    take('sent_at', '#serviceSentAt');
                    take('document_no', '#serviceBa');
                }
                if (status === 'sent' || status === 'in_repair') {
                    take('expected_at', '#serviceExpected');
                    take('quote', '#serviceQuote');
                }
                if (status === 'returned') {
                    take('returned_at', '#serviceReturnedAt');
                    take('placement', '#servicePlacement');
                    take('condition', '#serviceCondition');
                }
                if (status === 'ready' || status === 'returned') take('cost', '#serviceCost');

                serviceMove.error('');
                rmFeedback.busy('#serviceMoveSave', true);
                $.ajax({ url: serviceBoard.urls.status.replace('__id__', ticket.id), type: 'POST', dataType: 'json', data: values })
                    .done(response => {
                        $('#serviceMoveModal').modal('hide');
                        rmFeedback.toast(response.message);
                        serviceBoard.load();
                        serviceBoard.openTicket(ticket.id);
                    })
                    .fail(xhr => serviceMove.error(serviceBoard.plain(xhr)))
                    .always(() => rmFeedback.busy('#serviceMoveSave', false));
            },
        };

        // A note on the ticket in the drawer.
        const serviceNote = {
            open() {
                const ticket = serviceBoard.ticket;
                if (!ticket) return;
                $('#serviceNoteSub').text(`${ticket.number} · Radio ${ticket.radio}`);
                $('#serviceNoteText').val('');
                serviceNote.error('');
                serviceNote.ready();
                serviceBoard.aside('#serviceNoteModal');
            },

            error(text) {
                $('#serviceNoteErrorText').text(text);
                $('#serviceNoteError').toggleClass('d-none', !text);
            },

            ready() {
                const ok = String($('#serviceNoteText').val() || '').trim() !== '';
                $('#serviceNoteSave').prop('disabled', !ok);
                return ok;
            },

            save() {
                const ticket = serviceBoard.ticket;
                if (!ticket || !serviceNote.ready()) return;
                serviceNote.error('');
                rmFeedback.busy('#serviceNoteSave', true);
                $.ajax({
                    url: serviceBoard.urls.note.replace('__id__', ticket.id),
                    type: 'POST',
                    dataType: 'json',
                    data: { _method: 'PUT', note: String($('#serviceNoteText').val() || '').trim() },
                }).done(response => {
                    $('#serviceNoteModal').modal('hide');
                    rmFeedback.toast(response.message);
                    serviceBoard.openTicket(ticket.id);
                }).fail(xhr => {
                    serviceNote.error(serviceBoard.plain(xhr));
                }).always(() => {
                    rmFeedback.busy('#serviceNoteSave', false);
                    serviceNote.ready();
                });
            },
        };

        $(function() {
            serviceBoard.init();

            $('#serviceNew').on('click', () => serviceForm.open());
            $('#serviceRadioSearch').on('input', debounce(() => serviceForm.lookup(), 300));
            $('#serviceRadioMatches').on('click', '[data-pick]', function() {
                serviceForm.pick(Number($(this).data('pick')));
            });
            $('#serviceRadioPicked').on('click', '[data-change]', () => serviceForm.change());
            $('#serviceIssue').on('input', () => serviceForm.ready());
            $('#serviceNewSave').on('click', () => serviceForm.save());

            rmPickers.date('#serviceSentAt', {
                empty: serviceBoard.words.today,
                min: () => (serviceBoard.ticket ? serviceBoard.ticket.created_at : null),
                max: () => rmPickers.iso(new Date()),
            });
            rmPickers.date('#serviceExpected', {
                empty: serviceBoard.words.unknown,
                min: () => (serviceMove.status === 'sent' ? $('#serviceSentAt').val() : (serviceBoard.ticket && serviceBoard.ticket.sent_at)) || null,
                max: () => null,
            });
            rmPickers.date('#serviceReturnedAt', {
                empty: serviceBoard.words.today,
                min: () => (serviceBoard.ticket ? serviceBoard.ticket.sent_at : null),
                max: () => rmPickers.iso(new Date()),
            });
            $('#serviceMoveSave').on('click', () => serviceMove.save());

            $('#serviceNoteText').on('input', () => serviceNote.ready());
            $('#serviceNoteSave').on('click', () => serviceNote.save());
        });
    </script>
@endpush
