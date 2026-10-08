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

        .docs-kind {
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
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
    <div class="row g-2" id="docsPage">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    @if (! $configured)
                        <div class="alert alert-warning mb-0" id="docsNotConfigured">
                            <b>{{ $title }} belum bisa ditampilkan.</b>
                            Alamat folder SharePoint atau akun aplikasi Microsoft belum diisi di file <code>.env</code>
                            (lihat README, bagian "Dokumen SharePoint").
                        </div>
                    @else
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="docsSearch" class="form-label">Cari</label>
                                <input type="text" id="docsSearch" class="form-control" autocomplete="off" maxlength="100"
                                    placeholder="{{ $split ? $split['code'] . ' atau ' . mb_strtolower($split['label']) . '…' : 'Nama file…' }}">
                            </div>
                            <div class="col-md-7 d-flex gap-2 flex-wrap">
                                <x-global.btn-detail label="" color="red" size="sm" outline="true" onclick="spDocs.reset()"
                                    data-bs-toggle="tooltip" data-bs-title="Reset pencarian dan urutan">
                                    <x-slot:icon>
                                        <i class="ti ti-restore"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <x-global.btn-detail label="Muat ulang" color="blue" size="sm" id="docsReload"
                                    onclick="spDocs.load(true)">
                                    <x-slot:icon>
                                        <i class="ti ti-refresh"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                {{-- Hidden while the folder being looked at has no known address in SharePoint. --}}
                                <span class="ms-md-auto {{ $sharePointUrl ? '' : 'd-none' }}" id="docsOpenWrap">
                                    {{-- Bound (:href), not echoed: the component escapes it, and twice would break the "&" of the link. --}}
                                    <x-global.btn-detail label="Buka di SharePoint" color="blue" size="sm" outline="true"
                                        id="docsOpen" :href="$sharePointUrl ?? '#'" target="_blank"
                                        rel="noopener noreferrer">
                                        <x-slot:icon>
                                            <i class="ti ti-external-link"></i>
                                        </x-slot:icon>
                                    </x-global.btn-detail>
                                </span>
                            </div>

                            <div class="col-12">
                                <div class="docs-muted">
                                    @if ($subtitle)
                                        {{ $subtitle }}.
                                    @endif
                                    Daftar dibaca dari SharePoint dan diperbarui tiap {{ $cacheMinutes }} menit. Tombol
                                    <i class="ti ti-eye"></i> menampilkan file di sini; klik namanya untuk membukanya di SharePoint
                                    dengan akun Microsoft Anda.
                                </div>
                                <div class="small mt-1 d-none" id="docsCrumbs"></div>
                                <div class="docs-muted" id="docsInfo"></div>
                                <div id="docsNotice"></div>
                            </div>

                            <div class="col-12 mt-2">
                                <div class="card overflow-hidden mdka-border-gray-200">
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-vcenter mdka-border-gray-100 mb-0" id="docsTable">
                                            <thead>
                                                <tr class="mdka-bg-gray-50">
                                                    <th class="text-capitalize fs-5 py-3 text-center" width="30">No</th>
                                                    @if ($split)
                                                        <th class="text-capitalize fs-5 py-3" data-key="name" width="130">{{ $split['code'] }}</th>
                                                        <th class="text-capitalize fs-5 py-3" data-key="label">{{ $split['label'] }}</th>
                                                    @else
                                                        <th class="text-capitalize fs-5 py-3" data-key="name">Nama</th>
                                                    @endif
                                                    <th class="text-capitalize fs-5 py-3" data-key="extension" width="80">Tipe</th>
                                                    <th class="text-capitalize fs-5 py-3" data-key="modified" width="150">Diubah</th>
                                                    <th class="text-capitalize fs-5 py-3">Oleh</th>
                                                    <th class="text-capitalize fs-5 py-3" data-key="size" width="100">Ukuran</th>
                                                    <th class="text-capitalize fs-5 py-3 text-center" width="60">Lihat</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="pagination-container mt-2"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
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
                                title="File sebelumnya" aria-label="File sebelumnya"><i class="ti ti-chevron-left"></i></button>
                            <span class="docs-muted text-nowrap" id="docsViewerPlace"></span>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="docsViewerNext"
                                title="File berikutnya" aria-label="File berikutnya"><i class="ti ti-chevron-right"></i></button>
                            <a class="btn btn-sm btn-icon btn-outline-secondary" id="docsViewerTab" href="#" target="_blank"
                                rel="noopener" title="Buka di tab baru" aria-label="Buka di tab baru"><i
                                    class="ti ti-arrows-maximize"></i></a>
                            <a class="btn btn-sm btn-outline-blue text-nowrap" id="docsViewerSharePoint" href="#" target="_blank"
                                rel="noopener noreferrer" title="Buka di SharePoint"><i class="ti ti-external-link me-1"></i>SharePoint</a>
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-bs-dismiss="modal"
                                title="Tutup" aria-label="Tutup"><i class="ti ti-x"></i></button>
                        </div>
                    </div>
                    <div class="modal-body p-0 docs-viewer-body" id="docsViewerBody">
                        <div class="docs-viewer-wait" id="docsViewerWait">
                            <div class="spinner-border text-blue mb-2" role="status" id="docsViewerSpinner"></div>
                            <div class="docs-muted" id="docsViewerHint">Memuat file…</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($configured)
    @push('javascript')
        <script>
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
                            spUi.link(row.url, icon + label, 'Buka folder di SharePoint') :
                            `<a href="#" class="docs-folder" data-path="${spUi.esc(row.path)}">${icon}${label}</a>`;
                    }
                    const icon = `<i class="ti ti-${spUi.pick(spUi.icons, row.extension, 'file')} me-1"></i>`;
                    return spUi.link(row.url, icon + label, row.name);
                },
                /** The "Tipe" column: the file's extension, or "Folder". */
                kind(row) {
                    if (row.type === 'folder') return '<span class="badge bg-yellow-lt docs-kind">Folder</span>';
                    if (!row.extension) return spUi.dash('');
                    const color = spUi.pick(spUi.colors, row.extension, 'secondary');
                    return `<span class="badge bg-${color}-lt docs-kind">${spUi.esc(String(row.extension).toUpperCase())}</span>`;
                },
                /**
                 * The "Lihat" column: the eye shows the file here; a file that cannot be shown here
                 * (not a PDF or an image, or too large) gets a link to SharePoint's own viewer.
                 */
                view(row, index) {
                    if (row.type === 'folder') return '';
                    if (row.preview) {
                        return `<button type="button" class="btn btn-sm btn-icon btn-outline-blue docs-view" data-index="${Number(index)}" title="Lihat file" aria-label="Lihat file"><i class="ti ti-eye"></i></button>`;
                    }
                    if (!spUi.isLink(row.url)) return '';
                    return `<a class="btn btn-sm btn-icon btn-outline-secondary" href="${spUi.esc(row.url)}" target="_blank" rel="noopener noreferrer" title="Tidak bisa ditampilkan di sini: buka di SharePoint" aria-label="Buka di SharePoint"><i class="ti ti-external-link"></i></a>`;
                },
            };

            const spDocs = {
                url: "{{ route('documents.' . $folderKey . '.list') }}",
                fileUrl: "{{ route('documents.' . $folderKey . '.file') }}",
                title: @json($title),
                split: @json($split !== null),
                home: @json($sharePointUrl),
                page: 1,
                limit: 25,
                search: '',
                sort: 'modified',
                dir: 'desc',
                path: '',
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

                    spDocs.columns = ['index', ...name, {
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
                        emptyText: 'Tidak ada dokumen',
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
                        ...(refresh ? { refresh: 1 } : {}),
                    }).done(response => {
                        if (request !== spDocs.request) return;
                        spDocs.page = response.meta.current_page;
                        spDocs.render({
                            data: response.data,
                            meta: response.meta,
                            emptyText: spDocs.search ? 'Tidak ada dokumen yang cocok dengan pencarian.' : 'Folder ini kosong.',
                        });
                        spDocs.describe(response.folder);
                    }).fail(xhr => {
                        if (request !== spDocs.request) return;
                        spDocs.render({ emptyText: 'Daftar tidak bisa dimuat.' });
                        $('#docsPage .pagination-container').hide();
                        $('#docsInfo').text('');
                        spDocs.notice([{ kind: 'danger', text: spDocs.failure(xhr) }]);
                    }).always(() => {
                        if (request === spDocs.request) $('#docsReload').prop('disabled', false);
                    });
                },

                failure(xhr) {
                    if (xhr.status === 401) return 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.';
                    if (xhr.status === 403) return 'Role Anda tidak punya izin Read untuk menu ini.';
                    const body = xhr.responseJSON || {};
                    return [404, 422, 502, 503].includes(xhr.status) && typeof body.message === 'string' && body.message ?
                        body.message : 'Daftar tidak bisa dimuat. Coba lagi beberapa saat lagi.';
                },

                describe(folder) {
                    const parts = [];
                    if (folder.folders) parts.push(`${folder.folders} folder`);
                    parts.push(`${folder.files} file`);
                    if (folder.files) parts.push(folder.size);
                    parts.push(`data SharePoint per ${folder.fetched_at}`);
                    $('#docsInfo').text(parts.join(' · '));

                    const notices = [];
                    if (folder.stale) {
                        notices.push({
                            kind: 'warning',
                            text: `SharePoint sedang tidak bisa dihubungi. Yang tampil adalah daftar terakhir yang berhasil diambil (${folder.fetched_at}).`
                        });
                    }
                    if (folder.truncated) {
                        notices.push({
                            kind: 'info',
                            text: 'Isi folder ini sangat banyak: hanya sebagian yang ditampilkan. Buka di SharePoint untuk melihat semuanya.'
                        });
                    }
                    spDocs.notice(notices);
                    spDocs.target(folder.web_url);
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
                    frame.title = 'Isi file';
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
                    spViewer.waiting(true, 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.');
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
                    $('#docsViewerHint').text(message || 'Memuat file…');
                    if (on && message === null) {
                        spViewer.timer = setTimeout(() => {
                            $('#docsViewerHint').text('Masih memuat… Bila file tidak juga tampil, buka lewat tombol "tab baru" atau "SharePoint" di kanan atas.');
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
                $('#docsViewerPrevious').on('click', () => spViewer.step(-1));
                $('#docsViewerNext').on('click', () => spViewer.step(1));
                $('#docsViewer').on('hidden.bs.modal', () => spViewer.closed());
                spDocs.load();
            });
        </script>
    @endpush
@endif
