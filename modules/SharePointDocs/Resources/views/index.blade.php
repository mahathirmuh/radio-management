@extends('main.index')

@section('page-title', $title)

@push('style')
    <style>
        .docs-muted {
            color: var(--tblr-secondary);
            font-size: 0.7rem;
        }

        .docs-code {
            font-family: var(--tblr-font-monospace);
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .docs-name {
            word-break: break-word;
        }

        /* With the columns of the sheet (Masterlist, Catatan) the name would be left almost nothing at a laptop's width: the table scrolls sideways instead. */
        #docsTable.docs-register .docs-name {
            min-width: 13rem;
        }

        .docs-kind {
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* The note of the row of the sheet: a few lines, the whole of it in the tooltip. */
        .docs-note {
            display: -webkit-box;
            max-width: 16rem;
            overflow: hidden;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
            line-clamp: 4;
        }

        /* The file viewer: a wide dialog whose body is the file itself. */
        .docs-viewer-dialog {
            max-width: min(1200px, 96vw);
        }

        .docs-viewer-title {
            min-width: 0;
        }

        .docs-viewer-body {
            position: relative;
            height: calc(100vh - 9rem);
            min-height: 320px;
            background: #f6f8fb;
        }

        /* Behind the file: seen while it loads, and when the browser shows nothing in the frame. */
        .docs-viewer-wait {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
        }

        .docs-viewer-body iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>
@endpush

@section('content')
    <div class="rm-docs" id="docsPage">
        <div class="rm-head">
            <div>
                <h1 class="rm-title">{{ $title }}</h1>
            </div>
        </div>
        <div class="rm-card">
            @if (! $configured)
                <div class="alert alert-warning mb-0" id="docsNotConfigured">
                    <b>{{ __('SharePointDocs::scans.not_configured_title', ['title' => $title]) }}</b>
                    {!! __('SharePointDocs::scans.not_configured') !!}
                </div>
            @else
                @if ($register)
                    {{-- How the files stand against the sheet that lists them (K12): filled from the answer of the list by
                         spDocs.summary(), hidden while the sheet cannot be set against the files. --}}
                    <div class="rm-docs-summary" id="docsSummary">
                        <div class="rm-docs-stats">
                            <div class="rm-docs-stat">
                                <div class="rm-docs-stat-label">{{ __('SharePointDocs::scans.synced') }}</div>
                                <div class="rm-docs-stat-value" id="docsSynced">-</div>
                                <div class="rm-docs-stat-hint" id="docsSyncedHint">&nbsp;</div>
                            </div>
                            <div class="rm-docs-stat">
                                <div class="rm-docs-stat-label">{{ __('SharePointDocs::scans.linked') }}</div>
                                <div class="rm-docs-stat-value is-linked" id="docsLinked">-</div>
                                <div class="rm-docs-stat-hint">{{ __('SharePointDocs::scans.linked_by', ['what' => $split ? $split['code'] : __('SharePointDocs::scans.document_number')]) }}</div>
                            </div>
                            <div class="rm-docs-stat">
                                <div class="rm-docs-stat-label">{{ __('SharePointDocs::scans.unmatched') }}</div>
                                <div class="rm-docs-stat-value is-unmatched" id="docsUnmatched">-</div>
                                <div class="rm-docs-stat-hint">{{ __('SharePointDocs::scans.unmatched_hint') }}</div>
                            </div>
                        </div>
                        <div class="rm-alert warning is-bar d-none" id="docsUnmatchedBar">
                            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
                            <span class="flex-fill"><b id="docsUnmatchedText"></b> {{ __('SharePointDocs::scans.unmatched_bar') }}</span>
                            <button type="button" class="btn-rm secondary sm" id="docsReview">{{ __('SharePointDocs::scans.review') }}</button>
                        </div>
                    </div>
                @endif
                <div class="rm-toolbar rm-toolbar-tight">
                    <label class="rm-search rm-search-360" for="docsSearch">
                        <i class="ti ti-search" aria-hidden="true"></i>
                        <input type="search" id="docsSearch" autocomplete="off" maxlength="100" aria-label="{{ __('SharePointDocs::scans.search') }}"
                            placeholder="{{ $split ? __('SharePointDocs::scans.search_split', ['code' => $split['code'], 'label' => mb_strtolower($split['label'])]) : __('SharePointDocs::scans.search_name') }}">
                    </label>
                    @if ($register)
                        <div class="rm-seg" role="radiogroup" aria-label="{{ __('SharePointDocs::scans.status_label') }}" id="docsStatus">
                            <input type="radio" class="btn-check" name="docsStatus" id="docsStatusAll" value="" checked>
                            <label for="docsStatusAll">{{ __('SharePointDocs::scans.all') }} <span class="rm-tab-count" id="docsCountAll">-</span></label>
                            <input type="radio" class="btn-check" name="docsStatus" id="docsStatusLinked" value="linked">
                            <label for="docsStatusLinked">{{ __('SharePointDocs::scans.status_linked') }} <span class="rm-tab-count" id="docsCountLinked">-</span></label>
                            <input type="radio" class="btn-check" name="docsStatus" id="docsStatusUnmatched" value="unmatched">
                            <label for="docsStatusUnmatched">{{ __('SharePointDocs::scans.status_unmatched') }} <span class="rm-tab-count" id="docsCountUnmatched">-</span></label>
                        </div>
                    @endif
                    <button type="button" class="btn-rm secondary" onclick="spDocs.reset()" data-bs-toggle="tooltip"
                        data-bs-title="{{ __('SharePointDocs::scans.reset_hint') }}"><i class="ti ti-restore" aria-hidden="true"></i> {{ __('SharePointDocs::common.reset') }}</button>
                    <button type="button" class="btn-rm secondary" id="docsReload" onclick="spDocs.load(true)">
                        <i class="ti ti-refresh" aria-hidden="true"></i> {{ __('SharePointDocs::common.reload') }}
                    </button>
                    {{-- Hidden while the folder being looked at has no known address in SharePoint. --}}
                    <span class="ms-auto {{ $sharePointUrl ? '' : 'd-none' }}" id="docsOpenWrap">
                        <a class="btn-rm secondary" id="docsOpen" href="{{ $sharePointUrl ?? '#' }}" target="_blank"
                            rel="noopener noreferrer"><i class="ti ti-external-link" aria-hidden="true"></i> {{ __('SharePointDocs::scans.open_sharepoint') }}</a>
                    </span>
                </div>

                <div class="rm-docs-help">
                    {!! __('SharePointDocs::scans.help') !!}
                    @if ($register)
                        {{ __('SharePointDocs::scans.help_register', ['sheet' => $register['sheet'], 'workbook' => $register['title']]) }}
                    @endif
                </div>
                <div class="small d-none" id="docsCrumbs"></div>
                <div class="docs-muted" id="docsInfo"></div>
                <div id="docsNotice"></div>
                {{-- Said when a link from the page of the workbook asked for the files of one document number only. (The live
                     region is always there, so that what appears in it is read out; a region that appears with its text may not be.) --}}
                <div role="status">
                    <div class="alert alert-info py-2 small mt-2 mb-0 text-break d-none" id="docsCodeBar">
                        <span id="docsCodeText"></span>
                        <a href="#" id="docsCodeClear" class="ms-1">{{ __('SharePointDocs::scans.show_all') }}</a>
                    </div>
                </div>

                <div class="rm-box">
                    <div class="table-responsive">
                        <table class="rm-table{{ $register ? ' docs-register' : '' }}" id="docsTable">
                            <thead>
                                <tr>
                                    <th class="text-center" width="44">{{ __('SharePointDocs::scans.columns.no') }}</th>
                                    @if ($split)
                                        <th data-key="name" width="130">{{ $split['code'] }}</th>
                                        <th data-key="label">{{ $split['label'] }}</th>
                                    @else
                                        <th data-key="name">{{ __('SharePointDocs::scans.columns.name') }}</th>
                                    @endif
                                    @if ($register)
                                        <th data-key="masterlist" width="190">{{ __('SharePointDocs::scans.columns.masterlist') }}</th>
                                        <th width="200">{{ __('SharePointDocs::scans.columns.note') }}</th>
                                    @endif
                                    <th data-key="extension" width="80">{{ __('SharePointDocs::scans.columns.type') }}</th>
                                    <th data-key="modified" width="150">{{ __('SharePointDocs::scans.columns.modified') }}</th>
                                    <th>{{ __('SharePointDocs::scans.columns.by') }}</th>
                                    <th data-key="size" width="100">{{ __('SharePointDocs::scans.columns.size') }}</th>
                                    <th class="text-center" width="60">{{ __('SharePointDocs::scans.columns.view') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="pagination-container"></div>
                </div>
            @endif
        </div>
    </div>

    @if ($configured)
        {{-- The file viewer. The file is put into the body as an <iframe> when one is opened (spViewer). --}}
        <div class="modal fade" id="docsViewer" tabindex="-1" aria-hidden="true" aria-labelledby="docsViewerTitle">
            <div class="modal-dialog modal-xl modal-dialog-centered docs-viewer-dialog">
                <div class="modal-content">
                    {{-- Wraps on a narrow screen: the buttons go below the title instead of squeezing it away. --}}
                    <div class="modal-header py-2 gap-2 flex-wrap">
                        <h3 class="modal-title fs-4 text-truncate flex-fill docs-viewer-title" id="docsViewerTitle"></h3>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="docsViewerPrevious"
                                title="{{ __('SharePointDocs::scans.viewer.previous') }}" aria-label="{{ __('SharePointDocs::scans.viewer.previous') }}"><i class="ti ti-chevron-left"></i></button>
                            <span class="docs-muted text-nowrap" id="docsViewerPlace"></span>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="docsViewerNext"
                                title="{{ __('SharePointDocs::scans.viewer.next') }}" aria-label="{{ __('SharePointDocs::scans.viewer.next') }}"><i class="ti ti-chevron-right"></i></button>
                            <a class="btn btn-sm btn-icon btn-outline-secondary" id="docsViewerTab" href="#" target="_blank"
                                rel="noopener" title="{{ __('SharePointDocs::scans.viewer.new_tab') }}" aria-label="{{ __('SharePointDocs::scans.viewer.new_tab') }}"><i
                                    class="ti ti-arrows-maximize"></i></a>
                            <a class="btn btn-sm btn-outline-blue text-nowrap" id="docsViewerSharePoint" href="#" target="_blank"
                                rel="noopener noreferrer" title="{{ __('SharePointDocs::scans.open_sharepoint') }}"><i class="ti ti-external-link me-1"></i>SharePoint</a>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-dismiss="modal"
                                title="{{ __('SharePointDocs::common.close') }}" aria-label="{{ __('SharePointDocs::common.close') }}"><i class="ti ti-x"></i></button>
                        </div>
                    </div>
                    <div class="modal-body p-0 docs-viewer-body" id="docsViewerBody">
                        <div class="docs-viewer-wait" id="docsViewerWait">
                            <div class="spinner-border text-blue mb-2" role="status" id="docsViewerSpinner"></div>
                            <div class="docs-muted" id="docsViewerHint">{{ __('SharePointDocs::scans.viewer.loading') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($configured)
    @push('javascript')
        @php
            // The words of the page's script in its language: its own (SharePointDocs::scans.script) and the shared ones.
            $docsWords = __('SharePointDocs::scans.script') + ['session_ended' => __('SharePointDocs::common.session_ended')];
        @endphp
        <script>
            /** What the script says, in the language of the page. */
            const spText = @json($docsWords);

            const spUi = {
                icons: {
                    pdf: 'file-type-pdf',
                    doc: 'file-type-doc',
                    docx: 'file-type-docx',
                    xls: 'file-type-xls',
                    xlsx: 'file-type-xls',
                    csv: 'file-type-csv',
                    ppt: 'file-type-ppt',
                    pptx: 'file-type-ppt',
                    jpg: 'photo',
                    jpeg: 'photo',
                    png: 'photo',
                    gif: 'photo',
                    webp: 'photo',
                    zip: 'file-zip',
                    txt: 'file-text',
                },
                /** Badge colour of the "Tipe" column. */
                colors: {
                    pdf: 'red',
                    doc: 'blue',
                    docx: 'blue',
                    xls: 'green',
                    xlsx: 'green',
                    csv: 'green',
                    ppt: 'orange',
                    pptx: 'orange',
                    jpg: 'purple',
                    jpeg: 'purple',
                    png: 'purple',
                    gif: 'purple',
                    webp: 'purple',
                    zip: 'yellow',
                },
                /** A value of one of the maps above for a file extension; fallback for any other. */
                pick(map, extension, fallback) {
                    return Object.prototype.hasOwnProperty.call(map, extension) ? map[extension] : fallback;
                },
                esc(value) {
                    if (value === null || value === undefined) return '';
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
                dash(value) {
                    return value === null || value === undefined || value === '' ?
                        '<span class="docs-muted">-</span>' : spUi.esc(value);
                },
                isLink(url) {
                    return typeof url === 'string' && url.startsWith('https://');
                },
                /** Names and links come from SharePoint: everything is escaped, only https is linked. */
                link(url, html, title) {
                    if (!spUi.isLink(url)) return html;
                    return `<a href="${spUi.esc(url)}" target="_blank" rel="noopener noreferrer" title="${spUi.esc(title)}">${html}</a>`;
                },
                name(row, text) {
                    const label = spUi.esc(text || row.name);
                    if (row.type === 'folder') {
                        const icon = '<i class="ti ti-folder me-1"></i>';
                        return row.path === null ?
                            spUi.link(row.url, icon + label, spText.open_folder) :
                            `<a href="#" class="docs-folder" data-path="${spUi.esc(row.path)}">${icon}${label}</a>`;
                    }
                    const icon = `<i class="ti ti-${spUi.pick(spUi.icons, row.extension, 'file')} me-1"></i>`;
                    return spUi.link(row.url, icon + label, row.name);
                },
                /** The "Tipe" column: the file's extension, or "Folder". */
                kind(row) {
                    if (row.type === 'folder') return `<span class="badge bg-yellow-lt docs-kind">${spUi.esc(spText.folder)}</span>`;
                    if (!row.extension) return spUi.dash('');
                    const color = spUi.pick(spUi.colors, row.extension, 'secondary');
                    return `<span class="badge bg-${color}-lt docs-kind">${spUi.esc(String(row.extension).toUpperCase())}</span>`;
                },
                /**
                 * An address on this site (what the server gives for another page of the application): "/…", never "//…" nor
                 * "/\…" (a browser reads both as another host), and no blank in it ("/<tab>/…" is read the same way).
                 */
                isPath(url) {
                    return typeof url === 'string' && /^\/(?![\/\\])[^\s\\]*$/.test(url);
                },
                /**
                 * The "Masterlist" column: the row of the sheet that names the file (a way to it on the page of the workbook), a row that
                 * only has its number, or none. Said as the page's own words; the row number is a number.
                 */
                masterlist(row) {
                    if (row.type === 'folder') return '';
                    const own = row.masterlist;
                    if (!own) return spUi.dash('');
                    const line = Number.isInteger(own.line) && own.line > 0 ? own.line : '?';
                    const others = Number(own.more) > 0 ? Number(own.more) : 0;
                    const more = others > 0 ? ` +${others}` : '';
                    const andMore = others > 0 ? ' ' + rmChoice(spText.and_more, others) : '';
                    const goes = spUi.isPath(own.url);
                    // [colour, words, what it means]: the words are the page's own, the numbers are numbers.
                    const kinds = {
                        linked: ['green', rmFill(spText.row, { line }) + more, spText.row_hint + andMore],
                        differs: ['orange', rmFill(spText.differs, { line }) + more, spText.differs_hint + andMore],
                        unlinked: ['red', spText.unlinked, spText.unlinked_hint],
                    };
                    if (!Object.prototype.hasOwnProperty.call(kinds, own.status)) return spUi.dash('');
                    const [tone, text, hint] = kinds[own.status];
                    // The way to the row is told in the tooltip of the badge (the badge is all there is to point at) and, for a screen reader, in the name of the link.
                    const way = goes ? '. ' + rmFill(spText.click_row, { workbook: spDocs.register.title }) : '';
                    const badge = `<span class="badge bg-${tone}-lt docs-kind" title="${spUi.esc(hint + way)}">${spUi.esc(text)}</span>`;

                    return goes ?
                        `<a href="${spUi.esc(own.url)}" class="text-decoration-none" aria-label="${spUi.esc(rmFill(spText.see_row, { text, workbook: spDocs.register.title }))}">${badge}</a>` :
                        badge;
                },
                /** The "Catatan" column: the note of the row, up to four lines with the whole of it in the tooltip. */
                note(row) {
                    if (row.type === 'folder') return '';
                    return row.note ? `<span class="docs-note" title="${spUi.esc(row.note)}">${spUi.esc(row.note)}</span>` : spUi.dash('');
                },
                /**
                 * The "Lihat" column: the eye shows the file here; a file that cannot be shown here
                 * (not a PDF or an image, or too large) gets a link to SharePoint's own viewer.
                 */
                view(row, index) {
                    if (row.type === 'folder') return '';
                    if (row.preview) {
                        return `<button type="button" class="btn btn-sm btn-icon btn-outline-blue docs-view" data-index="${Number(index)}" title="${spUi.esc(spText.view_file)}" aria-label="${spUi.esc(spText.view_file)}"><i class="ti ti-eye"></i></button>`;
                    }
                    if (!spUi.isLink(row.url)) return '';
                    return `<a class="btn btn-sm btn-icon btn-outline-secondary" href="${spUi.esc(row.url)}" target="_blank" rel="noopener noreferrer" title="${spUi.esc(spText.not_here)}" aria-label="${spUi.esc(spText.open_sharepoint)}"><i class="ti ti-external-link"></i></a>`;
                },
            };

            const spDocs = {
                url: "{{ route('documents.' . $folderKey . '.list') }}",
                fileUrl: "{{ route('documents.' . $folderKey . '.file') }}",
                title: @json($title),
                split: @json($split !== null),
                home: @json($sharePointUrl),
                /** The sheet that lists these files (title of the workbook and of the sheet), when the reader may open it; else null. */
                register: @json($register),
                page: 1,
                limit: 25,
                search: '',
                sort: 'modified',
                dir: 'desc',
                path: '',
                /** The document number that a link from the page of the workbook asked for (only the files with it are shown); '' : all. */
                code: '',
                /** The files of one status against the sheet that are shown ('linked', 'unmatched'); '' : all. Its radio by status. */
                status: '',
                statuses: { '': '#docsStatusAll', linked: '#docsStatusLinked', unmatched: '#docsStatusUnmatched' },
                request: 0,
                columns: [],
                /** The rows on screen, in table order: what "Lihat" opens and steps through. */
                rows: [],

                build() {
                    const name = spDocs.split ? [{
                        key: 'name',
                        sortable: true,
                        headerClassName: '',
                        render: r => r.code ? `<span class="docs-code">${spUi.esc(r.code)}</span>` : spUi.dash('')
                    }, {
                        key: 'label',
                        sortable: true,
                        headerClassName: '',
                        className: 'docs-name',
                        render: r => spUi.name(r, r.label)
                    }] : [{
                        key: 'name',
                        sortable: true,
                        headerClassName: '',
                        className: 'docs-name',
                        render: r => spUi.name(r, r.name)
                    }];

                    const register = spDocs.register ? [{
                        key: 'masterlist',
                        sortable: true,
                        headerClassName: '',
                        className: 'text-nowrap',
                        render: r => spUi.masterlist(r)
                    }, {
                        key: 'note',
                        render: r => spUi.note(r)
                    }] : [];

                    spDocs.columns = ['index', ...name, ...register, {
                        key: 'extension',
                        sortable: true,
                        headerClassName: '',
                        render: r => spUi.kind(r)
                    }, {
                        key: 'modified',
                        sortable: true,
                        headerClassName: '',
                        className: 'text-nowrap',
                        render: r => spUi.dash(r.modified)
                    }, {
                        key: 'modified_by',
                        render: r => spUi.dash(r.modified_by)
                    }, {
                        key: 'size',
                        sortable: true,
                        headerClassName: '',
                        className: 'text-nowrap',
                        render: r => spUi.dash(r.size)
                    }, {
                        key: 'view',
                        className: 'text-center',
                        render: (r, index) => spUi.view(r, index)
                    }];
                },

                render(extra = {}) {
                    spDocs.rows = extra.data || [];
                    renderTableWithFeatures({
                        container: '#docsTable',
                        columns: spDocs.columns,
                        currentPage: spDocs.page,
                        limit: spDocs.limit,
                        emptyText: spText.no_documents,
                        currentSortKey: spDocs.sort,
                        currentSortDir: spDocs.dir,
                        onPageChange: page => {
                            spDocs.page = page;
                            spDocs.load();
                        },
                        onLimitChange: limit => {
                            spDocs.limit = limit;
                            spDocs.page = 1;
                            spDocs.load();
                        },
                        onSortChange: (key, dir) => {
                            spDocs.sort = key;
                            spDocs.dir = dir;
                            spDocs.page = 1;
                            spDocs.load();
                        },
                        ...extra,
                    });
                },

                /** Only the answer to the latest request is shown: typing fast sends several. */
                load(refresh = false) {
                    const request = ++spDocs.request;
                    spDocs.render({ loading: true });
                    $('#docsReload').prop('disabled', true);

                    $.getJSON(spDocs.url, {
                        page: spDocs.page,
                        paginated: spDocs.limit,
                        search: spDocs.search,
                        sort: spDocs.sort,
                        dir: spDocs.dir,
                        path: spDocs.path,
                        ...(spDocs.code !== '' ? { code: spDocs.code } : {}),
                        ...(spDocs.status !== '' ? { status: spDocs.status } : {}),
                        ...(refresh ? { refresh: 1 } : {}),
                    }).done(response => {
                        if (request !== spDocs.request) return;
                        spDocs.page = response.meta.current_page;
                        // The document number and the status that the answer is limited to (the server says what it applied).
                        spDocs.code = typeof response.folder.code === 'string' ? response.folder.code : '';
                        spDocs.status = typeof response.folder.status === 'string' ? response.folder.status : '';
                        spDocs.codeBar();
                        spDocs.render({
                            data: response.data,
                            meta: response.meta,
                            emptyText: spDocs.empty(),
                        });
                        spDocs.describe(response.folder, response.register);
                    }).fail(xhr => {
                        if (request !== spDocs.request) return;
                        spDocs.render({ emptyText: spText.load_failed });
                        $('#docsPage .pagination-container').hide();
                        $('#docsInfo').text('');
                        spDocs.summary(null, null);
                        spDocs.notice([{ kind: 'danger', text: spDocs.failure(xhr) }]);
                    }).always(() => {
                        if (request === spDocs.request) $('#docsReload').prop('disabled', false);
                    });
                },

                /** A link from the page of the workbook asked for the files of one document number: said, with a way back to all of them. */
                codeBar() {
                    // The link that gives the number up goes with its bar: when it had the focus (used with the keyboard), the search box gets it.
                    const focused = document.activeElement;
                    const had = !!focused && focused.id === 'docsCodeClear';
                    $('#docsCodeBar').toggleClass('d-none', spDocs.code === '');
                    $('#docsCodeText').text(spDocs.code === '' ? '' : rmFill(spText.only_code, { code: spDocs.code }));
                    if (had && spDocs.code === '') $('#docsSearch').trigger('focus');
                },

                /** What the table says when it shows nothing, in the words of what was asked for (put in as markup: the number is escaped). */
                empty() {
                    const among = spDocs.code !== '' || spDocs.search !== '';
                    if (spDocs.status === 'linked') return among ? spText.none_linked_among : spText.none_linked;
                    if (spDocs.status === 'unmatched') return among ? spText.none_unmatched_among : spText.none_unmatched;
                    if (spDocs.code !== '') return rmFill(spDocs.search ? spText.none_code_search : spText.none_code, { code: spUi.esc(spDocs.code) });

                    return spDocs.search ? spText.none_search : spText.empty_folder;
                },

                /**
                 * The three cells over the list, the warning about the files without their own row and the counts of the filter (K12):
                 * only when the sheet could be set against the files of the folder.
                 */
                summary(folder, register) {
                    const counted = !!folder && !!register && Number.isInteger(register.linked) && Number.isInteger(register.unmatched);
                    $('#docsSummary').toggleClass('d-none', !counted);
                    $('#docsStatus').toggleClass('d-none', !counted);
                    if (!counted) return;
                    const files = Number(folder.files) || 0;
                    const synced = folder.synced || {};
                    $('#docsSynced').text(synced.time || '-');
                    $('#docsSyncedHint').text(rmChoice(spText.synced_hint, files, { day: synced.day || folder.fetched_at }));
                    $('#docsLinked').text(register.linked);
                    $('#docsUnmatched').text(register.unmatched);
                    $('#docsCountAll').text(files);
                    $('#docsCountLinked').text(register.linked);
                    $('#docsCountUnmatched').text(register.unmatched);
                    $('#docsUnmatchedBar').toggleClass('d-none', register.unmatched === 0);
                    $('#docsUnmatchedText').text(rmChoice(spText.unmatched_text, register.unmatched, { workbook: register.title }));
                    spDocs.mark();
                },

                /** Ticks the radio of the status that is shown. */
                mark() {
                    $(spDocs.statuses[spDocs.status] || spDocs.statuses['']).prop('checked', true);
                },

                /** Shows the files of one status ('' : all): from the radios, and from "Tinjau" in the warning. */
                filter(status) {
                    spDocs.status = Object.prototype.hasOwnProperty.call(spDocs.statuses, status) ? status : '';
                    spDocs.mark();
                    spDocs.page = 1;
                    spDocs.load();
                },

                failure(xhr) {
                    if (xhr.status === 401) return spText.session_ended;
                    if (xhr.status === 403) return spText.no_read;
                    const body = xhr.responseJSON || {};
                    return [404, 422, 502, 503].includes(xhr.status) && typeof body.message === 'string' && body.message ?
                        body.message : spText.load_failed_retry;
                },

                describe(folder, register) {
                    const parts = [];
                    if (folder.folders) parts.push(rmChoice(spText.folders, folder.folders));
                    parts.push(rmChoice(spText.files, folder.files));
                    if (folder.files) parts.push(folder.size);
                    parts.push(rmFill(spText.data_at, { time: folder.fetched_at }));
                    if (register && register.fetched_at) parts.push(rmFill(spText.book_at, { workbook: register.title, time: register.fetched_at }));
                    $('#docsInfo').text(parts.join(' · '));

                    const notices = [];
                    if (folder.stale) {
                        notices.push({ kind: 'warning', text: rmFill(spText.stale, { time: folder.fetched_at }) });
                    }
                    if (folder.truncated) {
                        notices.push({ kind: 'info', text: spText.truncated });
                    }
                    if (register && register.unavailable) {
                        notices.push({ kind: 'info', text: register.unavailable });
                    }
                    if (register && register.stale) {
                        notices.push({ kind: 'warning', text: rmFill(spText.register_stale, { workbook: register.title, time: register.fetched_at }) });
                    }
                    spDocs.notice(notices);
                    spDocs.target(folder.web_url);
                    spDocs.summary(folder, register);
                },

                notice(notices) {
                    $('#docsNotice').html(notices.map(notice =>
                        `<div class="alert alert-${spUi.esc(notice.kind)} py-2 small mt-2 mb-0">${spUi.esc(notice.text)}</div>`
                    ).join(''));
                },

                /** Where "Buka di SharePoint" goes: the folder being looked at. */
                target(url) {
                    const link = spUi.isLink(url) ? url : (spDocs.path === '' ? spDocs.home : null);
                    $('#docsOpen').attr('href', spUi.isLink(link) ? link : '#');
                    $('#docsOpenWrap').toggleClass('d-none', !spUi.isLink(link));
                },

                crumbs() {
                    const $crumbs = $('#docsCrumbs');
                    if (spDocs.path === '') {
                        $crumbs.addClass('d-none').empty();
                        return;
                    }
                    const segments = spDocs.path.split('/');
                    const links = [`<a href="#" class="docs-folder" data-path="">${spUi.esc(spDocs.title)}</a>`];
                    segments.forEach((segment, index) => {
                        const path = segments.slice(0, index + 1).join('/');
                        links.push(index === segments.length - 1 ?
                            `<b>${spUi.esc(segment)}</b>` :
                            `<a href="#" class="docs-folder" data-path="${spUi.esc(path)}">${spUi.esc(segment)}</a>`);
                    });
                    $crumbs.html('<i class="ti ti-folder-open me-1"></i>' + links.join(' <span class="docs-muted">/</span> '))
                        .removeClass('d-none');
                },

                open(path) {
                    spDocs.path = path;
                    spDocs.page = 1;
                    spDocs.search = '';
                    spDocs.code = '';
                    spDocs.status = '';
                    spDocs.mark();
                    spDocs.codeBar();
                    $('#docsSearch').val('');
                    spDocs.crumbs();
                    spDocs.target(null);
                    spDocs.load();
                },

                apply() {
                    spDocs.search = $('#docsSearch').val().trim();
                    spDocs.page = 1;
                    spDocs.load();
                },

                reset() {
                    $('#docsSearch').val('');
                    spDocs.sort = 'modified';
                    spDocs.dir = 'desc';
                    spDocs.code = '';
                    spDocs.status = '';
                    spDocs.mark();
                    spDocs.codeBar();
                    spDocs.apply();
                },
            };

            /** The pop-up that shows a file of the list: a PDF or an image, in the browser's own viewer. */
            const spViewer = {
                /**
                 * The list the pop-up was opened from, and the folder it belongs to. Kept apart from
                 * spDocs: a search that redraws the table behind the pop-up must not change what
                 * "berikutnya" means.
                 */
                rows: [],
                path: '',
                /** Position in rows of the file being shown, -1 when closed. */
                at: -1,
                timer: null,

                /** Positions of the rows that can be shown here. */
                shown() {
                    return spViewer.rows.map((row, index) => row.preview ? index : -1).filter(index => index >= 0);
                },

                /** Opens the pop-up on a row of the table as it is now. */
                open(index) {
                    const row = spDocs.rows[index];
                    if (!row || !row.preview) return;
                    spViewer.rows = spDocs.rows;
                    spViewer.path = spDocs.path;
                    spViewer.show(index);
                    $('#docsViewer').modal('show');
                },

                show(index) {
                    const row = spViewer.rows[index];
                    spViewer.at = index;

                    const shown = spViewer.shown();
                    const place = shown.indexOf(index);
                    $('#docsViewerTitle').text([row.code, row.label].filter(Boolean).join(' - ') || row.name).attr('title', row.name);
                    $('#docsViewerPlace').text(`${place + 1} / ${shown.length}`);
                    $('#docsViewerPrevious').prop('disabled', place <= 0);
                    $('#docsViewerNext').prop('disabled', place >= shown.length - 1);

                    const address = spDocs.fileUrl + '?' + $.param({ path: spViewer.path, id: row.id });
                    $('#docsViewerTab').attr('href', address);
                    $('#docsViewerSharePoint').attr('href', spUi.isLink(row.url) ? row.url : '#').toggleClass('d-none', !spUi.isLink(row.url));

                    // A new frame for every file: changing the address of one frame would put each
                    // file into the Back button's history.
                    spViewer.waiting(true);
                    const frame = document.createElement('iframe');
                    frame.id = 'docsViewerFrame';
                    frame.title = spText.frame;
                    frame.addEventListener('load', () => spViewer.loaded(frame));
                    frame.src = address;
                    $('#docsViewerFrame').remove();
                    document.getElementById('docsViewerBody').appendChild(frame);
                },

                /**
                 * The frame has something to show. When that is no longer the file route, the session
                 * has ended and the sign-in page came back, which cannot work inside a frame: the
                 * pop-up says what to do instead of showing it.
                 */
                loaded(frame) {
                    let elsewhere = false;
                    try {
                        elsewhere = frame.contentWindow.location.pathname !== new URL(spDocs.fileUrl, location.href).pathname;
                    } catch (error) {
                        // Not readable from here: the browser's own viewer. That is the file.
                    }
                    if (!elsewhere) {
                        spViewer.waiting(false);
                        return;
                    }
                    $('#docsViewerFrame').remove();
                    spViewer.waiting(true, spText.session_ended);
                },

                /**
                 * The notice behind the frame; the file covers it once it is shown. Without a message
                 * it is "loading". A browser that is set to download PDFs shows nothing in the frame
                 * and never says so, while a slow line just takes long: after a while the notice
                 * therefore also points to the two buttons that work either way.
                 */
                waiting(on, message = null) {
                    clearTimeout(spViewer.timer);
                    $('#docsViewerWait').toggleClass('d-none', !on);
                    $('#docsViewerSpinner').toggleClass('d-none', message !== null);
                    $('#docsViewerHint').text(message || spText.loading);
                    if (on && message === null) {
                        spViewer.timer = setTimeout(() => {
                            $('#docsViewerHint').text(spText.still_loading);
                        }, 12000);
                    }
                },

                step(by) {
                    const shown = spViewer.shown();
                    const next = shown[shown.indexOf(spViewer.at) + by];
                    if (next !== undefined) spViewer.show(next);
                },

                closed() {
                    clearTimeout(spViewer.timer);
                    $('#docsViewerFrame').remove();
                    spViewer.rows = [];
                    spViewer.at = -1;
                },
            };

            $(function() {
                spDocs.build();
                // Enter searches at once; the pause after typing then has nothing new to ask for.
                $('#docsSearch').on('input', debounce(() => {
                    if ($('#docsSearch').val().trim() !== spDocs.search) spDocs.apply();
                })).on('keydown', e => {
                    if (e.key === 'Enter') spDocs.apply();
                });
                // attr(), not data(): a folder called "123" or "true" must stay text.
                $('#docsPage').on('click', 'a.docs-folder', function(e) {
                    e.preventDefault();
                    spDocs.open($(this).attr('data-path'));
                });
                $('#docsTable').on('click', 'button.docs-view', function() {
                    spViewer.open(Number($(this).attr('data-index')));
                });
                $('#docsStatus').on('change', 'input[name="docsStatus"]', function() {
                    spDocs.filter(String($(this).val()));
                });
                $('#docsReview').on('click', () => spDocs.filter('unmatched'));
                // A link from the page of the workbook ("?code=ICTCOF123" shows the files with that number, "?search=" searches the names) opens the folder so.
                const query = new URLSearchParams(location.search);
                // Cut by characters, not by the halves of one: a character outside the basic plane cut in two cannot be put into an address.
                const cut = (text, most) => Array.from(text).slice(0, most).join('');
                const asked = cut((query.get('search') || '').trim(), 100);
                if (asked !== '') {
                    spDocs.search = asked;
                    $('#docsSearch').val(asked);
                }
                // Any text up to the length the server takes (400, the longest name a file can have): it decides whether that is a document
                // number, and says in its answer whether it applied it.
                spDocs.code = cut((query.get('code') || '').trim(), 400);
                $('#docsCodeClear').on('click', function(e) {
                    e.preventDefault();
                    spDocs.code = '';
                    spDocs.codeBar();
                    spDocs.page = 1;
                    spDocs.load();
                });
                $('#docsViewerPrevious').on('click', () => spViewer.step(-1));
                $('#docsViewerNext').on('click', () => spViewer.step(1));
                $('#docsViewer').on('hidden.bs.modal', () => spViewer.closed());
                spDocs.load();
            });
        </script>
    @endpush
@endif
