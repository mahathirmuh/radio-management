@extends('main.index')

@section('page-title', $title)

@push('style')
    <style>
        .wb-muted {
            color: var(--tblr-secondary);
            font-size: 0.7rem;
        }

        /* A sheet can have many columns: the table scrolls sideways, cells keep a readable width. */
        #wbTable th,
        #wbTable td {
            white-space: nowrap;
        }

        #wbTable td.wb-cell {
            max-width: 22rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #wbTable td.wb-line {
            color: var(--tblr-secondary);
            font-size: 0.7rem;
            text-align: center;
        }

        #wbTabs .badge {
            font-size: 0.6rem;
        }
    </style>
@endpush

@section('content')
    <div class="row g-2" id="wbPage">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    @if (! $configured)
                        <div class="alert alert-warning mb-0" id="wbNotConfigured">
                            <b>{{ $title }} belum bisa ditampilkan.</b>
                            Alamat workbook di SharePoint atau akun aplikasi Microsoft belum diisi di file <code>.env</code>
                            (lihat README, bagian "Dokumen SharePoint").
                        </div>
                    @else
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="wbSearch" class="form-label">Cari di semua kolom</label>
                                <input type="text" id="wbSearch" class="form-control" autocomplete="off" maxlength="100"
                                    placeholder="Nama, nomor dokumen, serial number, …">
                            </div>
                            <div class="col-md-7 d-flex gap-2 flex-wrap">
                                <x-global.btn-detail label="" color="red" size="sm" outline="true" onclick="wbBook.reset()"
                                    data-bs-toggle="tooltip" data-bs-title="Reset pencarian dan urutan">
                                    <x-slot:icon>
                                        <i class="ti ti-restore"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                <x-global.btn-detail label="Muat ulang" color="blue" size="sm" id="wbReload"
                                    onclick="wbBook.load(true)">
                                    <x-slot:icon>
                                        <i class="ti ti-refresh"></i>
                                    </x-slot:icon>
                                </x-global.btn-detail>
                                {{-- Shown once the workbook has been read: its address comes with the rows. --}}
                                <span class="ms-md-auto d-none" id="wbOpenWrap">
                                    <x-global.btn-detail label="Buka di Excel" color="blue" size="sm" outline="true" id="wbOpen"
                                        href="#" target="_blank" rel="noopener noreferrer">
                                        <x-slot:icon>
                                            <i class="ti ti-external-link"></i>
                                        </x-slot:icon>
                                    </x-global.btn-detail>
                                </span>
                            </div>

                            <div class="col-12">
                                <div class="wb-muted">
                                    @if ($subtitle)
                                        {{ $subtitle }}.
                                    @endif
                                    Isi dibaca dari workbook di SharePoint dan diperbarui tiap {{ $cacheMinutes }} menit; halaman ini
                                    hanya menampilkan, mengubah data tetap dilakukan di Excel.
                                </div>
                                <div class="wb-muted" id="wbInfo"></div>
                                <div id="wbNotice"></div>
                            </div>

                            <div class="col-12 mt-2">
                                <ul class="nav nav-tabs" id="wbTabs" aria-label="Sheet workbook"></ul>
                                <div class="card overflow-hidden mdka-border-gray-200 border-top-0 rounded-top-0">
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-vcenter mdka-border-gray-100 mb-0" id="wbTable">
                                            <thead></thead>
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
@endsection

@if ($configured)
    @push('javascript')
        <script>
            /** Everything shown comes from the workbook: it is escaped, and only an https address becomes a link. */
            const wbUi = {
                esc(value) {
                    if (value === null || value === undefined) return '';
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
                isLink(url) {
                    return typeof url === 'string' && url.startsWith('https://');
                },
                /** A cell: its text, whole in the tooltip where the column cuts it off. An address (the link to a scan, say) can be clicked. */
                cell(value) {
                    const text = wbUi.esc(value);
                    return wbUi.isLink(value) ?
                        `<a href="${text}" target="_blank" rel="noopener noreferrer" title="${text}">${text}</a>` :
                        `<span title="${text}">${text}</span>`;
                },
            };

            const wbBook = {
                url: "{{ route('documents.' . $workbookKey . '.rows') }}",
                sheet: 0,
                /** The sheet the table on screen is of (the last answer was for it): its titles and page links mean that sheet only. */
                shown: 0,
                page: 1,
                limit: 25,
                search: '',
                sort: 'row',
                dir: 'desc',
                request: 0,
                /** The titles the table head was last built for (as one string): rebuilt only when they change. */
                head: null,
                columns: [],

                /** The table head and the column definitions for a sheet's titles. */
                build(titles) {
                    const head = JSON.stringify(titles);
                    if (head === wbBook.head) return;
                    wbBook.head = head;
                    $('#wbTable thead').html('<tr class="mdka-bg-gray-50"><th class="fs-5 py-3 text-center" data-key="row" width="60">Baris</th>' +
                        titles.map((title, index) => `<th class="fs-5 py-3" data-key="c${index}">${wbUi.esc(title)}</th>`).join('') + '</tr>');
                    wbBook.columns = [{
                        key: 'row',
                        sortable: true,
                        headerClassName: '',
                        className: 'wb-line',
                        render: r => wbUi.esc(r.line)
                    }, ...titles.map((title, index) => ({
                        key: `c${index}`,
                        sortable: true,
                        headerClassName: '',
                        className: 'wb-cell',
                        render: r => wbUi.cell(r.cells[index])
                    }))];
                },

                render(extra = {}) {
                    renderTableWithFeatures({
                        container: '#wbTable',
                        columns: wbBook.columns.length ? wbBook.columns : [{ key: 'row' }],
                        currentPage: wbBook.page,
                        limit: wbBook.limit,
                        emptyText: 'Tidak ada baris',
                        currentSortKey: wbBook.sort,
                        currentSortDir: wbBook.dir,
                        onPageChange: page => {
                            // The page links are those of the sheet that is being left, while another is loading.
                            if (wbBook.sheet !== wbBook.shown) return;
                            wbBook.page = page;
                            wbBook.load();
                        },
                        onLimitChange: limit => {
                            wbBook.limit = limit;
                            wbBook.page = 1;
                            wbBook.load();
                        },
                        onSortChange: (key, dir) => {
                            // Likewise the titles: their columns are not the new sheet's.
                            if (wbBook.sheet !== wbBook.shown) return;
                            wbBook.sort = key;
                            wbBook.dir = dir;
                            wbBook.page = 1;
                            wbBook.load();
                        },
                        ...extra,
                    });
                },

                /** Only the answer to the latest request is shown: typing fast sends several. */
                load(refresh = false) {
                    const request = ++wbBook.request;
                    wbBook.render({ loading: true });
                    $('#wbReload').prop('disabled', true);

                    $.getJSON(wbBook.url, {
                        sheet: wbBook.sheet,
                        page: wbBook.page,
                        paginated: wbBook.limit,
                        search: wbBook.search,
                        sort: wbBook.sort,
                        dir: wbBook.dir,
                        ...(refresh ? { refresh: 1 } : {}),
                    }).done(response => {
                        if (request !== wbBook.request) return;
                        wbBook.sheet = wbBook.shown = response.sheet.index;
                        wbBook.page = response.meta.current_page;
                        wbBook.sort = response.sheet.sort;
                        wbBook.dir = response.sheet.dir;
                        wbBook.tabs(response.sheets);
                        wbBook.build(response.sheet.columns);
                        wbBook.render({
                            data: response.data,
                            meta: response.meta,
                            emptyText: wbBook.search ? 'Tidak ada baris yang cocok dengan pencarian di sheet ini.' : 'Sheet ini kosong.',
                        });
                        wbBook.describe(response);
                    }).fail(xhr => {
                        if (request !== wbBook.request) return;
                        wbBook.render({ emptyText: 'Isi workbook tidak bisa dimuat.' });
                        $('#wbPage .pagination-container').hide();
                        $('#wbInfo').text('');
                        wbBook.notice([{ kind: 'danger', text: wbBook.failure(xhr) }]);
                    }).always(() => {
                        if (request === wbBook.request) $('#wbReload').prop('disabled', false);
                    });
                },

                failure(xhr) {
                    if (xhr.status === 401) return 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.';
                    if (xhr.status === 403) return 'Role Anda tidak punya izin Read untuk menu ini.';
                    const body = xhr.responseJSON || {};
                    return [404, 413, 415, 502, 503, 504].includes(xhr.status) && typeof body.message === 'string' && body.message ?
                        body.message : 'Isi workbook tidak bisa dimuat. Coba lagi beberapa saat lagi.';
                },

                /** One tab per sheet; while something is searched for, each says how many of its rows match. */
                tabs(sheets) {
                    // The tabs are written again with every answer: the one that has the focus gets it back.
                    const focused = document.activeElement;
                    const had = focused && focused.matches && focused.matches('#wbTabs a.wb-tab') ? focused.getAttribute('data-sheet') : null;
                    $('#wbTabs').html(sheets.map(sheet => {
                        const count = sheet.matches === null ? sheet.rows : sheet.matches;
                        const tone = sheet.matches === null ? 'bg-secondary-lt' : (sheet.matches ? 'bg-green-lt' : 'bg-secondary-lt');
                        const active = sheet.index === wbBook.sheet;
                        return `<li class="nav-item"><a href="#" class="nav-link wb-tab ${active ? 'active' : ''}" ${active ? 'aria-current="true" ' : ''}` +
                            `data-sheet="${Number(sheet.index)}">${wbUi.esc(sheet.name)} <span class="badge ${tone} ms-1">${Number(count)}</span></a></li>`;
                    }).join(''));
                    if (had !== null) {
                        const again = document.querySelector(`#wbTabs a.wb-tab[data-sheet="${had}"]`);
                        if (again) again.focus();
                    }
                },

                describe(response) {
                    const book = response.workbook;
                    const parts = [`${response.meta.total} baris${wbBook.search ? ' cocok' : ''} di sheet "${response.sheet.name}"`];
                    if (book.modified) parts.push(`workbook terakhir diubah ${book.modified}`);
                    parts.push(`data SharePoint per ${book.fetched_at}`);
                    $('#wbInfo').text(parts.join(' · '));

                    const notices = [];
                    if (book.stale) {
                        notices.push({
                            kind: 'warning',
                            text: `SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Yang tampil adalah isi terakhir yang berhasil diambil (${book.fetched_at}).`
                        });
                    }
                    if (response.sheet.truncated) {
                        notices.push({
                            kind: 'info',
                            // The button is there only when the workbook has an https address.
                            text: 'Sheet ini sangat besar: hanya sebagian yang ditampilkan.' +
                                (wbUi.isLink(book.web_url) ? ' Buka di Excel untuk melihat semuanya.' : '')
                        });
                    }
                    wbBook.notice(notices);

                    $('#wbOpen').attr('href', wbUi.isLink(book.web_url) ? book.web_url : '#');
                    $('#wbOpenWrap').toggleClass('d-none', !wbUi.isLink(book.web_url));
                },

                notice(notices) {
                    $('#wbNotice').html(notices.map(notice =>
                        `<div class="alert alert-${wbUi.esc(notice.kind)} py-2 small mt-2 mb-0">${wbUi.esc(notice.text)}</div>`
                    ).join(''));
                },

                /** Another sheet: its own order from the top, the search stays. */
                open(sheet) {
                    wbBook.sheet = sheet;
                    wbBook.page = 1;
                    wbBook.sort = 'row';
                    wbBook.dir = 'desc';
                    wbBook.load();
                },

                apply() {
                    wbBook.search = $('#wbSearch').val().trim();
                    wbBook.page = 1;
                    wbBook.load();
                },

                reset() {
                    $('#wbSearch').val('');
                    wbBook.sort = 'row';
                    wbBook.dir = 'desc';
                    wbBook.apply();
                },
            };

            $(function() {
                // Enter searches at once; the pause after typing then has nothing new to ask for.
                $('#wbSearch').on('input', debounce(() => {
                    if ($('#wbSearch').val().trim() !== wbBook.search) wbBook.apply();
                })).on('keydown', e => {
                    if (e.key === 'Enter') wbBook.apply();
                });
                $('#wbTabs').on('click', 'a.wb-tab', function(e) {
                    e.preventDefault();
                    // The tab is marked at once: the answer may take a while.
                    $('#wbTabs a.wb-tab').removeClass('active').removeAttr('aria-current');
                    $(this).addClass('active').attr('aria-current', 'true');
                    wbBook.open(Number($(this).attr('data-sheet')));
                });
                wbBook.load();
            });
        </script>
    @endpush
@endif
