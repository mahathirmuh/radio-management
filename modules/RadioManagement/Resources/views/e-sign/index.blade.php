@extends('main.index')

@include('RadioManagement::partials.scripts')

@section('page-title', 'E-Sign Master')

@push('style')
    <style>
        /* A signature is dark ink on nothing: it is shown on white, whatever the theme. */
        .esign-preview {
            display: inline-block;
            width: 150px;
            height: 56px;
            border-radius: 6px;
            background: #fff;
            box-shadow: inset 0 0 0 1px var(--rm-gray-200);
        }

        .esign-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* A real border: a ring drawn inside a frame is covered by the page in it. */
        #esignPadHost iframe {
            display: block;
            width: 100%;
            height: 480px;
            border: 1px solid var(--rm-gray-200);
            border-radius: 8px;
            background: #fff;
        }

        /* The dialog in the sizes of production (1.9.0): 720 wide in the middle of the screen, 16 around it, the title
           16px, fields 29 high with text of 0.65rem, buttons 28 high. */
        #esignModal .modal-dialog {
            max-width: 720px;
            margin-top: var(--tblr-modal-margin);
        }

        #esignModal .rm-dialog-head {
            position: relative;
            padding: 16px 48px 0 16px;
        }

        #esignModal .rm-dialog-title {
            font-size: 16px;
            line-height: 1.4286;
        }

        #esignModal .rm-dialog-close {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        #esignModal .rm-dialog-body.is-form {
            padding: 16px 16px 0;
        }

        #esignModal .rm-field .form-label {
            margin-bottom: 8px;
            font-size: .7rem;
        }

        #esignModal .rm-field .form-control,
        #esignModal .rm-field .form-select {
            height: auto;
            padding-top: 7px;
            padding-bottom: 7px;
            font-size: .65rem;
            line-height: 1.4286;
        }

        #esignModal .rm-field .rm-hint {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.4286;
        }

        #esignModal .form-check-label {
            padding-top: .175rem;
            font-size: .65rem;
            line-height: 1.4286;
        }

        #esignModal .rm-dialog-foot {
            padding: 8px 16px 16px;
            box-shadow: none;
        }

        /* While saving, the turning circle takes the place of the icon. */
        #esignModal .btn-rm.is-busy .ti {
            display: none;
        }
    </style>
@endpush

@php
    // What the role may do on "E-Sign Master" (the server checks again).
    $esignMay = [
        'create' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.e-sign', 'Create'),
        'update' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.e-sign', 'Update'),
        'delete' => \Illuminate\Support\Facades\Blade::check('permission', 'radio-management.e-sign', 'Delete'),
    ];
    // What the script of the page says, in the language of the page (RadioManagement::esign).
    $esignText = [
        'list' => __('RadioManagement::esign.list'),
        'dialog' => __('RadioManagement::esign.dialog') + ['pad' => __('RadioManagement::checkout.pad.box')],
        'delete' => __('RadioManagement::esign.delete'),
        'uses' => __('RadioManagement::esign.uses'),
        'messages' => collect(__('RadioManagement::esign.messages'))->only(['choose_owner', 'no_signature'])->all(),
    ];
@endphp

@section('content')
    <div class="rm-head">
        <div>
            <h1 class="rm-title">E-Sign Master</h1>
            <p class="rm-sub">{{ __('RadioManagement::esign.sub') }}</p>
        </div>
    </div>

    <div class="rm-card">
        <div class="rm-toolbar rm-toolbar-tight">
            <label class="rm-search" for="esignSearch">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="esignSearch" autocomplete="off" maxlength="100" placeholder="{{ __('RadioManagement::esign.search') }}"
                    aria-label="{{ __('RadioManagement::esign.search_label') }}" />
            </label>
            <button type="button" class="rm-icon-btn is-ringed rm-icon-btn-lg" id="esignSearchButton" data-bs-toggle="tooltip"
                data-bs-title="{{ __('RadioManagement::esign.search_button') }}" aria-label="{{ __('RadioManagement::esign.search_button') }}"><i class="ti ti-search"></i></button>
            <div class="rm-seg ms-auto" role="radiogroup" aria-label="{{ __('RadioManagement::esign.view') }}">
                <input type="radio" class="btn-check" name="esignView" id="esignViewGrid" value="grid" checked>
                <label for="esignViewGrid"><i class="ti ti-layout-grid" aria-hidden="true"></i> {{ __('RadioManagement::esign.view_grid') }}</label>
                <input type="radio" class="btn-check" name="esignView" id="esignViewList" value="list">
                <label for="esignViewList"><i class="ti ti-list" aria-hidden="true"></i> {{ __('RadioManagement::esign.view_list') }}</label>
            </div>
            @if ($esignMay['create'])
                <button type="button" class="btn-rm primary" id="esignAdd" onclick="esignForm.open()">
                    <i class="ti ti-plus" aria-hidden="true"></i> {{ __('RadioManagement::esign.add') }}
                </button>
            @endif
        </div>

        <div class="rm-banner">
            <i class="ti ti-lock" aria-hidden="true"></i>
            <span>{!! __('RadioManagement::esign.banner') !!}</span>
        </div>
        <div id="esignNotice"></div>

        <div class="rm-esign-grid" id="esignGrid" aria-live="polite"></div>

        <div class="rm-box d-none" id="esignListBox">
            <div class="table-responsive">
                <table class="rm-table" id="esignTable">
                    <thead>
                        <tr>
                            <th class="text-center" width="30">{{ __('RadioManagement::esign.columns.no') }}</th>
                            <th>{{ __('RadioManagement::esign.columns.account') }}</th>
                            <th>{{ __('RadioManagement::esign.columns.name') }}</th>
                            <th>{{ __('RadioManagement::esign.columns.signature') }}</th>
                            <th class="text-center">{{ __('RadioManagement::esign.columns.status') }}</th>
                            <th>{{ __('RadioManagement::esign.columns.uses') }}</th>
                            <th class="text-end"><span class="visually-hidden">{{ __('RadioManagement::esign.columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="pagination-container"></div>
        </div>
    </div>

    {{-- Registers a signature, or changes one. Static: what was signed is lost when the dialog closes. --}}
    <div class="modal fade rm-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="esignModal" aria-hidden="true"
        aria-labelledby="esignTitle">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="rm-dialog-head">
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="esignTitle">{{ __('RadioManagement::esign.dialog.add') }}</h3>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body is-form">
                    <div class="row g-2">
                        <div class="col-md-6 rm-field">
                            <label for="esign_user" class="form-label required">{{ __('RadioManagement::esign.dialog.owner') }}</label>
                            <select id="esign_user" class="form-select"></select>
                            <div class="rm-hint" id="esignUserHint">{{ __('RadioManagement::esign.dialog.owner_hint') }}</div>
                        </div>
                        <div class="col-md-6 rm-field">
                            <label for="esign_name" class="form-label">{{ __('RadioManagement::esign.dialog.name') }}</label>
                            <input type="text" id="esign_name" class="form-control" maxlength="150" autocomplete="off"
                                placeholder="{{ __('RadioManagement::esign.dialog.name_hint') }}">
                        </div>
                        <div class="col-12 d-none" id="esignActiveWrap">
                            <label class="form-check form-switch mt-1 mb-0">
                                <input class="form-check-input" type="checkbox" id="esign_active" checked>
                                <span class="form-check-label">{{ __('RadioManagement::esign.dialog.active') }}</span>
                            </label>
                        </div>
                        <div class="col-12 rm-field d-none" id="esignCurrentWrap">
                            <span class="form-label mb-1">{{ __('RadioManagement::esign.dialog.current') }}</span>
                            <span class="esign-preview" id="esignCurrent"></span>
                        </div>
                        <div class="col-12 rm-field">
                            <span class="form-label mb-1" id="esignPadLabel">{{ __('RadioManagement::esign.dialog.signature') }}</span>
                            <div id="esignPadHost"></div>
                        </div>
                        <div class="col-12" role="alert">
                            <div class="rm-alert danger d-none" id="esignError"></div>
                        </div>
                    </div>
                </div>
                <div class="rm-dialog-foot">
                    <button type="button" class="btn-rm sm secondary" data-bs-dismiss="modal"><i class="ti ti-x" aria-hidden="true"></i>{{ __('RadioManagement::common.ui.cancel') }}</button>
                    <button type="button" class="btn-rm sm primary" id="esignSave" onclick="esignForm.submit()"><i class="ti ti-device-floppy"
                            aria-hidden="true"></i>{{ __('RadioManagement::common.ui.save') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Where a signature was put on a form. --}}
    <div class="modal fade rm-modal" id="esignUsesModal" aria-hidden="true" aria-labelledby="esignUsesTitle">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="rm-dialog-head">
                    <span class="rm-dialog-icon"><i class="ti ti-history" aria-hidden="true"></i></span>
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title" id="esignUsesTitle">{{ __('RadioManagement::esign.uses.title') }}</h3>
                        <p class="rm-dialog-sub" id="esignUsesLabel"></p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="{{ __('RadioManagement::common.ui.close') }}"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body">
                    <div id="esignUsesList"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        const esignMay = @json($esignMay);
        /** The words of the page in its language (RadioManagement::esign). */
        const esignText = @json($esignText);
        const esignUrls = {
            list: "{{ route('radio-management.e-sign.list') }}",
            pad: "{{ route('radio-management.e-sign.pad') }}",
            uses: "{{ route('radio-management.e-sign.uses', ':id') }}",
            store: "{{ route('radio-management.e-sign.store') }}",
            update: "{{ route('radio-management.e-sign.update', ':id') }}",
            destroy: "{{ route('radio-management.e-sign.destroy', ':id') }}",
        };

        const esignUi = {
            /** A picture is shown only when it is what the server keeps: a PNG inside the page, never an address somewhere else. */
            isPicture(url) {
                return typeof url === 'string' && /^data:image\/png;base64,[A-Za-z0-9+\/=]+$/.test(url);
            },
            /** A word of the list (esignText.list), escaped for the HTML it goes into. */
            say(key) {
                return RadioUi.esc(esignText.list[key]);
            },
            preview(url, name) {
                return esignUi.isPicture(url) ?
                    `<span class="esign-preview"><img src="${url}" alt="${RadioUi.esc(RadioUi.fill(esignText.list.signature_of, { name: name || '' }))}"></span>` :
                    '<span class="radio-muted">-</span>';
            },
        };

        const esignTable = {
            page: 1,
            limit: 25,
            search: '',
            request: 0,
            /** The rows on screen by id, and the accounts a signature can still be registered for. */
            rows: {},
            candidates: [],
            columns: [
                'index',
                {
                    key: 'account',
                    render: row => `<div>${RadioUi.dash(row.account)}${row.account_gone ? ' ' + RadioUi.badge(esignText.list.account_gone, 'red') : ''}</div>` +
                        `<div class="radio-muted">${RadioUi.esc(row.email)}</div>`
                },
                {
                    key: 'display_name',
                    render: row => RadioUi.dash(row.display_name)
                },
                {
                    key: 'image',
                    render: row => esignUi.preview(row.image, row.display_name)
                },
                {
                    key: 'active',
                    className: 'text-center',
                    render: row => row.active && !row.account_gone ? RadioUi.badge(esignText.list.active, 'green') : RadioUi.badge(esignText.list.inactive, 'secondary')
                },
                {
                    key: 'uses',
                    render: row => `<div>${RadioUi.esc(RadioUi.choice(esignText.list.times, Number(row.uses) || 0))}</div>` +
                        (row.last_used ? `<div class="radio-muted">${RadioUi.esc(RadioUi.fill(esignText.list.last, { at: row.last_used }))}</div>` : '')
                },
                {
                    key: 'actions',
                    className: 'text-end text-nowrap',
                    render: row => `<div class="rm-acts">
                        ${esignMay.update ? `<button type="button" class="rm-act" data-action="edit" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="${esignUi.say('edit')}" aria-label="${esignUi.say('edit')}"><i class="ti ti-edit"></i></button>` : ''}
                        <button type="button" class="rm-act" data-action="uses" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="${esignUi.say('uses')}" aria-label="${esignUi.say('uses')}"><i class="ti ti-history"></i></button>
                        ${esignMay.delete ? `<button type="button" class="rm-act is-danger" data-action="delete" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="${esignUi.say('delete')}" aria-label="${esignUi.say('delete')}"><i class="ti ti-trash"></i></button>` : ''}
                    </div>`
                },
            ],

            render(extra = {}) {
                renderTableWithFeatures({
                    container: '#esignTable',
                    columns: esignTable.columns,
                    currentPage: esignTable.page,
                    limit: esignTable.limit,
                    emptyText: RadioUi.esc(esignText.list.empty),
                    onPageChange: page => {
                        esignTable.page = page;
                        esignTable.load();
                    },
                    onLimitChange: limit => {
                        esignTable.limit = limit;
                        esignTable.page = 1;
                        esignTable.load();
                    },
                    ...extra,
                });
            },

            /** Only the answer to the latest request is shown. */
            load() {
                const request = ++esignTable.request;
                $('[data-bs-toggle="tooltip"]').tooltip('hide');
                esignTable.render({ loading: true });
                $.getJSON(esignUrls.list, {
                    page: esignTable.page,
                    paginated: esignTable.limit,
                    search: esignTable.search,
                }).done(response => {
                    if (request !== esignTable.request) return;
                    const page = RadioUi.pageAfter(response, esignTable.page);
                    if (page) {
                        esignTable.page = page;
                        return esignTable.load();
                    }
                    esignTable.rows = {};
                    response.data.forEach(row => {
                        esignTable.rows[row.id] = row;
                    });
                    esignTable.candidates = Array.isArray(response.candidates) ? response.candidates : [];
                    $('#esignNotice').html('');
                    esignTable.render({ data: response.data, meta: response.meta });
                    esignGrid.render(response.data);
                    RadioUi.tooltips('#esignTable');
                }).fail(xhr => {
                    if (request !== esignTable.request) return;
                    esignTable.render({ emptyText: RadioUi.esc(esignText.list.load_failed) });
                    esignGrid.render([]);
                    $('#esignNotice').html(`<div class="rm-alert danger"><i class="ti ti-alert-triangle"></i><span>${RadioUi.errors(xhr).join('<br>')}</span></div>`);
                });
            },
        };

        /** The signatures as cards (the design's Grid): the same rows as the list, and a tile to register one more. */
        const esignGrid = {
            /** The signature just saved: { id } when it was changed, { user } when it is new; it pulses when drawn. */
            fresh: null,

            isFresh(row) {
                const fresh = esignGrid.fresh;
                if (!fresh) return false;
                return fresh.id ? Number(row.id) === Number(fresh.id) : Number(row.user_id) === Number(fresh.user);
            },

            initials(name) {
                return String(name || '?').trim().split(/\s+/).slice(0, 2).map(word => word.charAt(0).toUpperCase()).join('') || '?';
            },
            card(row) {
                const name = row.display_name || row.account || '';
                const active = row.active && !row.account_gone;
                return `<div class="rm-esign-card${active ? '' : ' is-off'}${esignGrid.isFresh(row) ? ' is-new' : ''}">
                    <div class="rm-esign-head">
                        <span class="rm-avatar-ini">${RadioUi.esc(esignGrid.initials(name))}</span>
                        <div class="rm-esign-who">
                            <div class="rm-esign-name">${RadioUi.dash(name)}</div>
                            <div class="rm-line2">${RadioUi.esc(row.email || '')}</div>
                        </div>
                        ${active ? RadioUi.badge(esignText.list.active, 'green') : RadioUi.badge(esignText.list.inactive, 'secondary')}
                    </div>
                    <div class="rm-esign-sheet">${esignUi.isPicture(row.image) ? `<img src="${row.image}" alt="${RadioUi.esc(RadioUi.fill(esignText.list.signature_of, { name }))}">` : `<span class="radio-muted">${esignUi.say('no_picture')}</span>`}</div>
                    <div class="rm-esign-foot">
                        <span class="rm-line2">${RadioUi.esc(RadioUi.choice(esignText.list.updated, Number(row.uses) || 0, { at: row.updated_at || '-' }))}</span>
                        <span class="rm-acts">
                            <button type="button" class="rm-act" data-action="uses" data-id="${Number(row.id)}" data-bs-toggle="tooltip" data-bs-title="${esignUi.say('uses')}" aria-label="${esignUi.say('uses')}"><i class="ti ti-history"></i></button>
                            ${esignMay.update ? `<button type="button" class="btn-rm sm secondary" data-action="edit" data-id="${Number(row.id)}">${esignUi.say('change')}</button>` : ''}
                            ${esignMay.delete ? `<button type="button" class="btn-rm sm secondary rm-text-danger" data-action="delete" data-id="${Number(row.id)}">${esignUi.say('delete')}</button>` : ''}
                        </span>
                    </div>
                </div>`;
            },
            render(rows) {
                const cards = (rows || []).map(esignGrid.card).join('');
                const add = esignMay.create ? `<button type="button" class="rm-esign-add" onclick="esignForm.open()"><i class="ti ti-plus"></i><span>${RadioUi.esc(esignText.dialog.add)}</span></button>` : '';
                $('#esignGrid').html(cards + add || `<div class="radio-muted">${esignUi.say('empty')}</div>`);
                // (Once: the next time the cards are drawn nothing is new.)
                esignGrid.fresh = null;
                RadioUi.tooltips('#esignGrid');
            },
        };

        const esignView = {
            key: 'esignView',
            /** Grid or List; the choice is kept in the browser. */
            set(view, keep = true) {
                const list = view === 'list';
                $('#esignGrid').toggleClass('d-none', list);
                $('#esignListBox').toggleClass('d-none', !list);
                $(list ? '#esignViewList' : '#esignViewGrid').prop('checked', true);
                if (!keep) return;
                try {
                    window.localStorage.setItem(esignView.key, list ? 'list' : 'grid');
                } catch (e) {
                    // No storage: the choice lasts for this page.
                }
            },
            read() {
                try {
                    return window.localStorage.getItem(esignView.key) === 'list' ? 'list' : 'grid';
                } catch (e) {
                    return 'grid';
                }
            },
        };

        const esignForm = {
            /** The signature that is being changed; null: a new one is registered. */
            id: null,

            open(row = null) {
                esignForm.id = row ? row.id : null;
                const words = esignText.dialog;
                $('#esignTitle').text(row ? words.edit : words.add);
                const select = $('#esign_user');
                if (row) {
                    // The owner of a signature is not changed: another account gets a signature of its own.
                    select.html(`<option value="${Number(row.user_id)}">${RadioUi.esc(row.account || words.account_gone)}${row.email ? ' · ' + RadioUi.esc(row.email) : ''}</option>`);
                } else {
                    select.html(`<option value="">${RadioUi.esc(words.choose)}</option>` + esignTable.candidates.map(user =>
                        `<option value="${Number(user.id)}">${RadioUi.esc(user.name)} · ${RadioUi.esc(user.email)}</option>`).join(''));
                }
                select.prop('disabled', !!row);
                $('#esignUserHint').text(row ? words.owner_fixed : words.owner_hint);
                $('#esign_name').val(row ? (row.display_name ?? '') : '');
                $('#esign_active').prop('checked', row ? !!row.active : true);
                $('#esignActiveWrap').toggleClass('d-none', !row);
                $('#esignCurrentWrap').toggleClass('d-none', !row);
                $('#esignCurrent').html(row && esignUi.isPicture(row.image) ? `<img src="${row.image}" alt="${RadioUi.esc(words.current)}">` : '');
                $('#esignPadLabel').text(row ? words.signature_new : words.signature);
                esignForm.error('');
                // A pad of its own for every time the dialog opens: nothing of the signature before is left in it.
                $('#esignPadFrame').remove();
                const frame = document.createElement('iframe');
                frame.id = 'esignPadFrame';
                frame.title = words.pad;
                frame.src = esignUrls.pad;
                document.getElementById('esignPadHost').appendChild(frame);
                $('#esignModal').modal('show');
            },

            error(text) {
                $('#esignError').toggleClass('d-none', text === '').text(text);
            },

            /** What the pad of the frame holds: the picture that was taken, whether the pad is still open (ink that was not taken yet). */
            pad() {
                const frame = document.getElementById('esignPadFrame');
                try {
                    const page = frame.contentDocument;
                    const overlay = page.getElementById('signOverlay');
                    const image = page.querySelector('.sig-img');

                    return { open: !!overlay && !overlay.hidden, image: image ? image.getAttribute('src') : null };
                } catch (error) {
                    return { open: false, image: null };
                }
            },

            submit() {
                const pad = esignForm.pad();
                if (pad.open) {
                    esignForm.error(esignText.dialog.pad_open);
                    return;
                }
                if (!esignForm.id && !$('#esign_user').val()) {
                    esignForm.error(esignText.messages.choose_owner);
                    return;
                }
                if (!esignForm.id && pad.image === null) {
                    esignForm.error(esignText.messages.no_signature);
                    return;
                }
                esignForm.error('');
                const data = { display_name: $('#esign_name').val() };
                if (pad.image !== null) data.image = pad.image;
                if (esignForm.id) {
                    data._method = 'PUT';
                    data.active = $('#esign_active').is(':checked') ? 1 : 0;
                } else {
                    data.user_id = $('#esign_user').val();
                }
                RadioUi.send('#esignSave', {
                    url: esignForm.id ? esignUrls.update.replace(':id', esignForm.id) : esignUrls.store,
                    type: 'POST',
                    dataType: 'json',
                    data,
                })?.done(response => {
                    esignGrid.fresh = esignForm.id ? { id: esignForm.id } : { user: data.user_id };
                    $('#esignModal').modal('hide');
                    rmFeedback.toast(response.message);
                    esignTable.load();
                }).fail(xhr => esignForm.error(esignForm.messages(xhr).join(' ')));
            },

            /** What a refusal says, as plain text (it is written into the dialog as text). */
            messages(xhr) {
                const body = xhr.responseJSON || {};
                if (Array.isArray(body.errors)) return body.errors.map(String);
                if (body.errors && typeof body.errors === 'object') return Object.values(body.errors).flat().map(String);
                if (xhr.status === 401 || xhr.status === 419) return [esignText.dialog.session];
                if (xhr.status === 403) return [RadioUi.words.no_permission];
                return [typeof body.message === 'string' && body.message ? body.message : RadioUi.words.error];
            },

            remove(row) {
                rmDialog.confirm({
                    tone: 'red',
                    icon: 'trash',
                    title: esignText.delete.title,
                    html: `${RadioUi.esc(row.display_name)}<br><span class="text-muted small">${RadioUi.esc(esignText.delete.text)}</span>`,
                    confirmText: esignText.delete.confirm,
                    confirmKind: 'destructive',
                }).then(result => {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: esignUrls.destroy.replace(':id', row.id),
                        type: 'POST',
                        dataType: 'json',
                        data: { _method: 'DELETE' },
                    }).done(response => {
                        rmFeedback.toast(response.message);
                        esignTable.load();
                    }).fail(RadioUi.fail);
                });
            },

            uses(row) {
                $('#esignUsesLabel').text(row.display_name || '');
                const words = esignText.uses;
                $('#esignUsesList').html(`<div class="radio-muted">${RadioUi.esc(RadioUi.words.loading)}</div>`);
                $('#esignUsesModal').modal('show');
                $.getJSON(esignUrls.uses.replace(':id', row.id)).done(response => {
                    const lines = response.data.map(use =>
                        `<tr><td class="text-nowrap">${RadioUi.esc(use.at)}</td><td>${RadioUi.dash(use.radio)}</td><td>${RadioUi.dash(use.user)}</td></tr>`).join('');
                    const total = Number(response.total) || 0;
                    const counted = total > response.data.length ?
                        RadioUi.fill(words.total_shown, { count: total, shown: response.data.length }) : RadioUi.choice(words.total, total);
                    $('#esignUsesList').html(response.data.length ?
                        `<div class="rm-box mb-2"><table class="rm-table compact"><thead><tr><th>${RadioUi.esc(words.at)}</th><th>${RadioUi.esc(words.radio)}</th><th>${RadioUi.esc(words.user)}</th></tr></thead><tbody>${lines}</tbody></table></div>` +
                        `<div class="radio-muted">${RadioUi.esc(counted)}</div>` :
                        `<div class="radio-muted">${RadioUi.esc(words.never)}</div>`);
                }).fail(xhr => {
                    $('#esignUsesList').html(`<div class="rm-alert danger"><i class="ti ti-alert-triangle"></i><span>${RadioUi.errors(xhr).join('<br>')}</span></div>`);
                });
            },
        };

        $(function() {
            esignTable.load();
            $('#esignSearchButton').on('click', () => {
                esignTable.search = $('#esignSearch').val().trim();
                esignTable.page = 1;
                esignTable.load();
            });
            $('#esignSearch').on('keydown', e => {
                if (e.key === 'Enter') $('#esignSearchButton').trigger('click');
            });
            // The buttons of a row of the list and of a card of the grid do the same.
            const act = function() {
                const row = esignTable.rows[Number($(this).attr('data-id'))];
                $(this).tooltip('hide');
                if (!row) return;
                const action = $(this).attr('data-action');
                if (action === 'delete') esignForm.remove(row);
                else if (action === 'uses') esignForm.uses(row);
                else esignForm.open(row);
            };
            $('#esignTable').on('click', 'button[data-action]', act);
            $('#esignGrid').on('click', 'button[data-action]', act);
            esignView.set(esignView.read(), false);
            $('input[name="esignView"]').on('change', function() {
                esignView.set($(this).val());
            });
            // The pad goes with the dialog: a signature that was drawn and not saved is not kept anywhere.
            $('#esignModal').on('hidden.bs.modal', () => $('#esignPadFrame').remove());
        });
    </script>
@endpush
