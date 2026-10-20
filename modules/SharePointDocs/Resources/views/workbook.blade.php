@extends('main.index')

@section('page-title', $title)

@push('style')
    <style>
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
            color: var(--rm-gray-500);
            font-family: var(--rm-mono);
            font-size: 12px;
            text-align: center;
        }

        /* The cards over the table of a sheet that is checked against a folder: each is a button that shows its rows. */
        #wbCards .wb-card {
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--rm-ring);
            text-align: left;
            cursor: pointer;
            transition: background-color .12s ease-out, box-shadow .15s ease-out;
        }

        #wbCards .wb-card:hover {
            background: var(--rm-gray-50);
        }

        #wbCards .wb-card.active {
            background: var(--rm-brand-50);
            box-shadow: inset 0 0 0 2px var(--rm-brand-600);
        }

        #wbCards .wb-card:focus-visible {
            outline: 2px solid var(--rm-brand-600);
            outline-offset: 2px;
        }

        /* A card that has no rows behind it. */
        #wbCards .wb-card:disabled {
            cursor: default;
            opacity: 0.55;
        }

        #wbCards .wb-card .card-body {
            padding: 10px 16px !important;
        }

        #wbCards .wb-card-label {
            color: var(--rm-gray-500);
            font-size: 13px;
            line-height: 18px;
        }

        #wbCards .wb-card-count {
            color: var(--rm-gray-900);
            font-size: 24px;
            font-weight: 600;
            line-height: 32px;
            white-space: nowrap;
        }

        /* The number says the status by its colour (the dot of the old cards is not shown). */
        #wbCards {
            --tblr-gutter-x: 12px;
            --tblr-gutter-y: 12px;
        }

        #wbCards .wb-card .badge {
            display: none;
        }

        #wbCards .wb-card[data-status="ok"] .wb-card-count {
            color: var(--rm-success-600);
        }

        #wbCards .wb-card[data-status="missing"] .wb-card-count {
            color: var(--rm-error-600);
        }

        #wbCards .wb-card[data-status="differs"] .wb-card-count {
            color: var(--rm-orange-600);
        }

        #wbCards .wb-card[data-status="below"] .wb-card-count {
            color: var(--rm-purple-600);
        }

        /* The pencil that asks for a change of a cell: faint until the row is pointed at or the pencil has the focus. */
        #wbTable .wb-edit {
            opacity: 0.3;
            line-height: 1;
        }

        #wbTable tr:hover .wb-edit,
        #wbTable .wb-edit:focus-visible {
            opacity: 1;
        }
    </style>
@endpush

@section('content')
    <div class="rm-docs" id="wbPage">
        <div class="rm-head">
            <div>
                <h1 class="rm-title">{{ $title }}</h1>
                <p class="rm-sub">{{ $subtitle ? $subtitle . '. ' : '' }}Isi dibaca dari workbook di SharePoint dan diperbarui tiap
                    {{ $cacheMinutes }} menit.</p>
            </div>
        </div>
        <div class="rm-card">
            @if (! $configured)
                <div class="alert alert-warning mb-0" id="wbNotConfigured">
                    <b>{{ $title }} belum bisa ditampilkan.</b>
                    Alamat workbook di SharePoint atau akun aplikasi Microsoft belum diisi di file <code>.env</code>
                    (lihat README, bagian "Dokumen SharePoint").
                </div>
            @else
                <div class="rm-banner">
                    <i class="ti ti-info-circle" aria-hidden="true"></i>
                    <span id="wbEditHint">Halaman ini hanya menampilkan; mengubah data tetap dilakukan di Excel.</span>
                </div>

                <div class="rm-toolbar">
                    <div class="rm-field">
                        <label for="wbSearch" class="form-label" id="wbSearchLabel">Cari di semua kolom</label>
                        <div class="rm-search rm-search-wide">
                            <i class="ti ti-search" aria-hidden="true"></i>
                            <input type="search" id="wbSearch" autocomplete="off" maxlength="100"
                                placeholder="Nama, nomor dokumen, serial number, …">
                        </div>
                    </div>
                    <button type="button" class="btn-rm secondary" onclick="wbBook.reset()" data-bs-toggle="tooltip"
                        data-bs-title="Reset pencarian, kartu dan baris yang dipilih, dan urutan"><i class="ti ti-restore"
                            aria-hidden="true"></i> Reset</button>
                    <button type="button" class="btn-rm secondary" id="wbReload" onclick="wbBook.load(true)">
                        <i class="ti ti-refresh" aria-hidden="true"></i> Muat ulang
                    </button>
                    {{-- Shown once the workbook has been read: its address comes with the rows. --}}
                    <span class="ms-md-auto d-none" id="wbOpenWrap">
                        <a class="btn-rm secondary" id="wbOpen" href="#" target="_blank" rel="noopener noreferrer"><i
                                class="ti ti-external-link" aria-hidden="true"></i> Buka di Excel</a>
                    </span>
                </div>

                <div class="wb-muted" id="wbInfo"></div>
                <div id="wbNotice"></div>
                {{-- Said when a link from the page of the folder asked for some rows of Excel only. (The live region is
                     always there, so that what appears in it is read out; a region that appears with its text may not be.) --}}
                <div role="status">
                    <div class="alert alert-info py-2 small mt-2 mb-0 d-none" id="wbLineBar">
                        <span id="wbLineText"></span>
                        <a href="#" id="wbLineClear" class="ms-1">Tampilkan semua baris</a>
                    </div>
                </div>

                {{-- Only on a sheet that is checked against a folder of scans (the answer says so). --}}
                <div class="col-12 mt-2 d-none" id="wbCardsWrap">
                    <div class="row g-2" id="wbCards" role="group" aria-label="Ringkasan kolom File scan"></div>
                    <div class="wb-muted mt-2" id="wbCardsInfo"></div>
                    <div class="wb-muted mt-1">
                        <b>Ada</b>: file dengan nama yang sama seperti di link (huruf besar atau kecil tidak dibedakan).
                        <b>Nama beda</b>: ada file dengan nomor yang sama, tetapi link-nya kosong atau menyebut nama lain
                        (spasi lebih atau kurang, misalnya). <b>Subfolder</b>: link menunjuk ke subfolder, biasanya karena
                        nama pemohon mengandung tanda "/". <b>Belum ada</b>: tidak ada file dengan nama atau nomor itu.
                        Klik sebuah kartu untuk hanya melihat barisnya; pencarian tidak mencari di kolom ini.
                    </div>
                </div>

                <div class="rm-wb-sheets">
                    <ul class="nav nav-tabs rm-wb-tabs" id="wbTabs" aria-label="Sheet workbook"></ul>
                    <div class="rm-box mt-3">
                        <div class="table-responsive">
                            <table class="rm-table" id="wbTable">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="pagination-container"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>

@if ($configured)
    {{-- Asks for a change of one cell: nothing is written to the workbook until somebody else has approved it. --}}
    <div class="modal fade rm-modal" data-bs-backdrop="static" id="wbEditModal" tabindex="-1" aria-labelledby="wbEditTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="rm-dialog-head">
                    <span class="rm-dialog-icon"><i class="ti ti-edit" aria-hidden="true"></i></span>
                    <div class="rm-dialog-titles">
                        <h3 class="rm-dialog-title modal-title" id="wbEditTitle">Ajukan perubahan sel</h3>
                        <p class="rm-dialog-sub wb-muted" id="wbEditWhere"></p>
                    </div>
                    <button type="button" class="rm-dialog-close" data-bs-dismiss="modal" aria-label="Tutup"><i
                            class="ti ti-x"></i></button>
                </div>
                <div class="rm-dialog-body">
                    <div class="rm-field">
                        <label for="wbEditOld" class="form-label">Isi sekarang</label>
                        <input type="text" id="wbEditOld" class="form-control" readonly tabindex="-1">
                    </div>
                    <div class="rm-field">
                        <label for="wbEditValue" class="form-label required">Isi baru</label>
                        <input type="text" id="wbEditValue" class="form-control" maxlength="1000" autocomplete="off">
                        <div class="rm-hint">Kosongkan untuk mengosongkan sel. Kolom angka diisi angka (1500 atau 1.500,50); kolom tanggal dengan format hh-bb-tttt (15-03-2023).</div>
                    </div>
                    <div role="alert">
                        <div class="alert alert-danger py-2 small mb-0 d-none" id="wbEditError"></div>
                    </div>
                    <div class="alert alert-info py-2 mb-0 small">
                        Perubahan <b>belum</b> tersimpan di SharePoint. Orang lain yang berwenang harus menyetujuinya dulu di
                        halaman Persetujuan Edit; Anda tidak bisa menyetujui pengajuan Anda sendiri.
                    </div>
                </div>
                <div class="rm-dialog-foot">
                    <button type="button" class="btn-rm secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn-rm primary" id="wbEditSend" onclick="wbEdit.submit()">
                        <i class="ti ti-send" aria-hidden="true"></i> Ajukan perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
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
                /**
                 * An address on this site (what the server gives for another page of the application): "/…", never "//…" nor
                 * "/\…" (a browser reads both as another host), and no blank in it ("/<tab>/…" is read the same way).
                 */
                isPath(url) {
                    return typeof url === 'string' && /^\/(?![\/\\])[^\s\\]*$/.test(url);
                },
                /** The badge of each status of the "File scan" column: colour, words, and what it means. The words are the page's own. */
                badges: {
                    ok: ['green', 'Ada', 'Ada file dengan nama yang sama seperti di link (huruf besar atau kecil tidak dibedakan)'],
                    missing: ['red', 'Belum ada', 'Tidak ada file dengan nama atau nomor ini di folder'],
                    differs: ['orange', 'Nama beda', 'Ada file dengan nomor ini, tetapi link di Excel kosong atau menyebut nama lain (spasi lebih atau kurang, misalnya)'],
                    below: ['purple', 'Subfolder', 'Link menunjuk ke subfolder: biasanya karena nama pemohon mengandung tanda "/"'],
                },
                /**
                 * The "File scan" cell of a row: the badge of its status; for a file that is there (even under another name) a way
                 * to the file on the page of the folder, which then shows the files with that document number. The way is told in the
                 * tooltip of the badge (the badge is all there is to point at). Anything else is an ordinary cell.
                 */
                scan(row, index, scan) {
                    const info = row.scan;
                    if (!info || !scan || !Object.prototype.hasOwnProperty.call(wbUi.badges, info.status)) return wbUi.cell(row.cells[index]);
                    const [tone, text, hint] = wbUi.badges[info.status];
                    const goes = info.status !== 'missing' && !!info.code && wbUi.isPath(scan.open);
                    const title = goes ? `${hint}. Klik untuk melihat file ini di ${scan.folder}` : hint;
                    const badge = `<span class="badge bg-${tone}-lt" title="${wbUi.esc(title)}">${wbUi.esc(text)}</span>`;
                    if (goes) {
                        // (The tooltip is on the badge; a screen reader is told where the link goes by the name of the link.)
                        return `<a href="${wbUi.esc(scan.open + '?' + $.param({ code: info.code }))}" class="text-decoration-none" aria-label="${wbUi.esc(`${text}: lihat file di ${scan.folder}`)}">${badge}</a>`;
                    }
                    return badge;
                },
                /** The words of a status, as the badges say them ('' when there is no such status). */
                words(status) {
                    return Object.prototype.hasOwnProperty.call(wbUi.badges, status) ? wbUi.badges[status][1] : '';
                },
                /**
                 * What goes before a cell of who may ask for a change: the pencil that opens the question; for a cell that holds a formula a
                 * lock (it is changed in Excel); for a cell that already has a request waiting the mark of that. Nothing for a column that is
                 * no cell of the workbook (the check against the folder) or for who may not ask.
                 */
                editor(row, index) {
                    if (!(index < wbBook.editable)) return '';
                    const waiting = row.waiting && Object.prototype.hasOwnProperty.call(row.waiting, index) ? row.waiting[index] : null;
                    if (waiting !== null) {
                        return `<span class="badge bg-blue-lt me-1" title="${wbUi.esc('Menunggu persetujuan: ' + waiting)}">menunggu</span>`;
                    }
                    if (Array.isArray(row.locked) && row.locked.includes(index)) {
                        return '<i class="ti ti-lock text-muted me-1" title="Sel ini berisi rumus: ubah di Excel" aria-label="Berisi rumus"></i>';
                    }

                    return `<button type="button" class="btn btn-link btn-sm p-0 me-1 wb-edit" data-line="${Number(row.line)}" data-col="${Number(index)}" ` +
                        `title="Ajukan perubahan sel ini" aria-label="Ajukan perubahan sel ini"><i class="ti ti-pencil"></i></button>`;
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
                /** The kind of row the clicked card shows ('' : all). */
                status: '',
                /** The Excel rows that a link from the page of the folder asked for, as they are sent ("2,9"); '' : all rows. */
                line: '',
                /** What the answer says about the check against the folder, for the sheet on screen; null: the sheet has no such check. */
                scan: null,
                /** The name of a sheet that a link from another page asked for: said once, in the first request. */
                sheetName: '',
                request: 0,
                /** How many columns of the sheet on screen can be asked a change of (0: the reader may not ask); the rows on screen by their number in Excel. */
                editable: 0,
                byLine: {},
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
                        // The column that says whether the file is there: a badge. (What the answer says is read when the row is drawn.)
                        render: r => wbBook.scan && wbBook.scan.column === index ? wbUi.scan(r, index, wbBook.scan) : wbUi.editor(r, index) + wbUi.cell(r.cells[index])
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
                        // The sheet a link asked for is asked for by its name, once; every other request asks by position ("tab"),
                        // which is never taken for a name (a sheet may be called "1").
                        ...(wbBook.sheetName !== '' ? { sheet: wbBook.sheetName } : { tab: wbBook.sheet }),
                        page: wbBook.page,
                        paginated: wbBook.limit,
                        search: wbBook.search,
                        sort: wbBook.sort,
                        dir: wbBook.dir,
                        ...(wbBook.status !== '' ? { status: wbBook.status } : {}),
                        ...(wbBook.line !== '' ? { line: wbBook.line } : {}),
                        ...(refresh ? { refresh: 1 } : {}),
                    }).done(response => {
                        if (request !== wbBook.request) return;
                        wbBook.sheet = wbBook.shown = response.sheet.index;
                        wbBook.sheetName = '';
                        // Whether the reader may ask for a change of a cell (the server says so), and the rows the pencils belong to.
                        wbBook.editable = Math.max(0, Number(response.sheet.editable_columns) || 0);
                        wbBook.byLine = {};
                        response.data.forEach(row => {
                            wbBook.byLine[row.line] = row;
                        });
                        $('#wbEditHint').text(wbBook.editable > 0 ?
                            'Klik ikon pensil di sebuah sel untuk mengajukan perubahannya. Isi workbook baru berubah setelah disetujui orang lain di halaman Persetujuan Edit.' :
                            'Halaman ini hanya menampilkan; mengubah data tetap dilakukan di Excel.');
                        wbBook.page = response.meta.current_page;
                        wbBook.sort = response.sheet.sort;
                        wbBook.dir = response.sheet.dir;
                        // What the sheet is checked against, and which kind of row the answer is of (the server says what it applied).
                        wbBook.scan = response.sheet.scan && response.sheet.scan.counts ? response.sheet.scan : null;
                        wbBook.status = wbBook.scan && wbBook.scan.status ? wbBook.scan.status : '';
                        // The rows of Excel that the answer is limited to (the server says what it applied).
                        const lines = Array.isArray(response.sheet.lines) ? response.sheet.lines.map(Number).filter(n => Number.isInteger(n) && n > 0) : [];
                        wbBook.line = lines.join(',');
                        wbBook.lineBar(lines);
                        wbBook.tabs(response.sheets);
                        wbBook.build(response.sheet.columns);
                        wbBook.cards(response.sheet.scan);
                        wbBook.render({
                            data: response.data,
                            meta: response.meta,
                            emptyText: wbBook.empty(),
                        });
                        wbBook.describe(response);
                    }).fail(xhr => {
                        if (request !== wbBook.request) return;
                        wbBook.render({ emptyText: 'Isi workbook tidak bisa dimuat.' });
                        $('#wbPage .pagination-container').hide();
                        $('#wbInfo').text('');
                        $('#wbCardsWrap').addClass('d-none');
                        wbBook.notice([{ kind: 'danger', text: wbBook.failure(xhr) }]);
                    }).always(() => {
                        if (request === wbBook.request) $('#wbReload').prop('disabled', false);
                    });
                },

                /** What an empty table says: which of the things that were asked for left nothing. */
                empty() {
                    // (The table puts this text in as markup: what is not the page's own is escaped.)
                    const filtered = wbBook.status !== '' ? ` dengan status "${wbUi.esc(wbUi.words(wbBook.status) || wbBook.status)}"` : '';
                    if (wbBook.line !== '') {
                        return `Baris Excel ${wbUi.esc(wbBook.line.split(',').join(', '))} tidak ada di sheet ini, atau tidak cocok dengan pencarian atau kartu yang dipilih.`;
                    }
                    if (filtered !== '') {
                        return `Tidak ada baris${filtered}${wbBook.search ? ' yang cocok dengan pencarian' : ''} di sheet ini.`;
                    }

                    return wbBook.search ? 'Tidak ada baris yang cocok dengan pencarian di sheet ini.' : 'Sheet ini kosong.';
                },

                /** A link from the page of the folder asked for some rows of Excel: said, with a way back to all of them. */
                lineBar(lines) {
                    // The link that gives the rows up goes with its bar: when it had the focus (used with the keyboard), the search box gets it.
                    const focused = document.activeElement;
                    const had = !!focused && focused.id === 'wbLineClear';
                    $('#wbLineBar').toggleClass('d-none', lines.length === 0);
                    $('#wbLineText').text(lines.length ? `Hanya baris Excel ${lines.join(', ')}.` : '');
                    if (had && lines.length === 0) $('#wbSearch').trigger('focus');
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
                    rmMotion.ink('#wbTabs', '.nav-link.active');
                },

                /**
                 * The cards over the table: how many rows have their file, have none, have it under another name, point into a
                 * subfolder; each shows its rows when clicked (and all of them when clicked again). Nothing for a sheet that is
                 * not checked against a folder. The numbers are of the whole sheet, whatever is searched for.
                 */
                cards(scan) {
                    // (The search does not look in the column of the check: said where it is asked for.)
                    $('#wbSearchLabel').text(scan && scan.counts ? 'Cari di semua kolom kecuali File scan' : 'Cari di semua kolom');
                    if (!scan || !scan.counts) {
                        $('#wbCardsWrap').addClass('d-none');
                        $('#wbCards').empty();
                        return;
                    }
                    const counts = scan.counts;
                    const cards = [
                        { status: '', label: 'Total baris', tone: 'blue', count: counts.total },
                        { status: 'ok', label: 'Ada', tone: 'green', count: counts.ok },
                        { status: 'missing', label: 'Belum ada', tone: 'red', count: counts.missing },
                        { status: 'differs', label: 'Nama beda', tone: 'orange', count: counts.differs },
                        { status: 'below', label: 'Subfolder', tone: 'purple', count: counts.below },
                    ];
                    // The cards are written again with every answer: the one that has the focus gets it back (a card used with the keyboard).
                    const focused = document.activeElement;
                    const had = focused && focused.matches && focused.matches('#wbCards button.wb-card') ? focused.getAttribute('data-status') : null;
                    $('#wbCards').html(cards.map(card => {
                        const active = (scan.status || '') === card.status;
                        const count = Number(card.count) || 0;
                        // A card with no rows behind it is not offered, unless it is the one that is open (so that it can be closed again).
                        const off = count === 0 && !active;
                        return `<div class="col-6 col-md"><button type="button" class="card card-sm w-100 wb-card ${active ? 'active' : ''}" data-status="${card.status}" aria-pressed="${active}"${off ? ' disabled' : ''}>` +
                            `<span class="card-body p-2 d-block"><span class="d-flex align-items-center"><span class="badge bg-${card.tone} me-2">&nbsp;</span>` +
                            `<span class="wb-card-label">${card.label}</span></span><span class="wb-card-count d-block">${count}</span></span></button></div>`;
                    }).join(''));
                    if (had !== null) {
                        // A card that has gone off (its last row was fixed in Excel) cannot take the focus: the Total card does.
                        const again = document.querySelector(`#wbCards button.wb-card[data-status="${had}"]`);
                        const next = again && !again.disabled ? again : document.querySelector('#wbCards button.wb-card[data-status=""]');
                        if (next && !next.disabled) next.focus();
                    }
                    $('#wbCardsInfo').text(`Status dicek terhadap folder ${scan.folder} (data folder per ${scan.fetched_at}).`);
                    $('#wbCardsWrap').removeClass('d-none');
                },

                describe(response) {
                    const book = response.workbook;
                    const narrowed = wbBook.search !== '' || wbBook.status !== '' || wbBook.line !== '';
                    const parts = [`${response.meta.total} baris${narrowed ? ' cocok' : ''} di sheet "${response.sheet.name}"`];
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
                    const scan = response.sheet.scan;
                    if (scan && scan.unavailable) {
                        notices.push({ kind: 'info', text: scan.unavailable });
                    }
                    if (scan && scan.stale) {
                        notices.push({
                            kind: 'warning',
                            text: `SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Kolom File scan dicek terhadap daftar terakhir folder ${scan.folder} yang berhasil diambil (${scan.fetched_at}).`
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
                    wbBook.status = '';
                    wbBook.line = '';
                    wbBook.lineBar([]);
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
                    wbBook.status = '';
                    wbBook.line = '';
                    wbBook.lineBar([]);
                    wbBook.apply();
                },
            };

            /** Asking for a change of one cell: the question, and what the server answers. Nothing is written to the workbook by it. */
            const wbEdit = {
                url: "{{ route('documents.' . $workbookKey . '.edit') }}",
                line: 0,
                column: 0,
                /** The sheet the question is about: the one the table on screen shows. */
                sheet: 0,

                open(line, column) {
                    const row = wbBook.byLine[line];
                    if (!row || !(column < wbBook.editable)) return;
                    wbEdit.line = line;
                    wbEdit.column = column;
                    wbEdit.sheet = wbBook.shown;
                    const title = wbBook.head ? JSON.parse(wbBook.head)[column] : '';
                    const old = row.cells[column] === undefined || row.cells[column] === null ? '' : String(row.cells[column]);
                    $('#wbEditWhere').text(`Kolom "${title}", baris Excel ${line}`);
                    $('#wbEditOld').val(old).attr('placeholder', '(kosong)');
                    $('#wbEditValue').val(old);
                    wbEdit.error('');
                    $('#wbEditModal').modal('show');
                },

                error(text) {
                    $('#wbEditError').toggleClass('d-none', text === '').text(text);
                },

                /** What a refusal says: the words of the server, or of the first thing it found wrong. */
                failure(xhr) {
                    if (xhr.status === 401 || xhr.status === 419) return 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.';
                    const body = xhr.responseJSON || {};
                    // The menu's own refusal is in English; the words of this module are Indonesian and are shown as they are.
                    if (xhr.status === 403 && !(typeof body.message === 'string' && body.message !== '' && !/^You /.test(body.message))) {
                        return 'Role Anda tidak punya izin Update untuk menu ini.';
                    }
                    if (body.errors) {
                        const first = Object.values(body.errors)[0];
                        if (Array.isArray(first) && first.length) return String(first[0]);
                    }

                    return typeof body.message === 'string' && body.message ? body.message : 'Pengajuan tidak bisa dikirim. Coba lagi beberapa saat lagi.';
                },

                submit() {
                    const button = $('#wbEditSend');
                    if (button.prop('disabled')) return;
                    button.prop('disabled', true);
                    wbEdit.error('');
                    $.ajax({
                        url: wbEdit.url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            _method: 'PATCH',
                            sheet: wbEdit.sheet,
                            line: wbEdit.line,
                            column: wbEdit.column,
                            value: $('#wbEditValue').val(),
                        },
                    }).done(response => {
                        $('#wbEditModal').modal('hide');
                        Swal.fire('Pengajuan dikirim', wbUi.esc(response.message), 'success');
                        // The cell shows that it waits for a decision.
                        wbBook.load();
                    }).fail(xhr => {
                        wbEdit.error(wbEdit.failure(xhr));
                    }).always(() => {
                        button.prop('disabled', false);
                    });
                },
            };

            $(function() {
                $('#wbTable').on('click', 'button.wb-edit', function() {
                    // Another sheet is loading: the rows on screen are those of the sheet that is being left.
                    if (wbBook.sheet !== wbBook.shown) return;
                    wbEdit.open(Number($(this).attr('data-line')), Number($(this).attr('data-col')));
                });
                $('#wbEditValue').on('keydown', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        wbEdit.submit();
                    }
                });
                $('#wbEditModal').on('shown.bs.modal', () => $('#wbEditValue').trigger('focus').trigger('select'));
            });

            $(function() {
                // A link from another page ("?sheet=COF&search=ICTCOF123") opens the sheet with that searched for.
                const asked = new URLSearchParams(location.search);
                // Cut by characters, not by the halves of one: a character outside the basic plane cut in two cannot be put into an address.
                const cut = (text, most) => Array.from(text).slice(0, most).join('');
                wbBook.sheetName = cut((asked.get('sheet') || '').trim(), 100);
                wbBook.search = cut((asked.get('search') || '').trim(), 100);
                // "?line=2,9": whole numbers only (a few), or the rows of Excel are not asked for.
                const wanted = (asked.get('line') || '').trim();
                wbBook.line = /^\d{1,9}(,\d{1,9}){0,19}$/.test(wanted) ? wanted : '';
                $('#wbSearch').val(wbBook.search);
                $('#wbLineClear').on('click', function(e) {
                    e.preventDefault();
                    wbBook.line = '';
                    wbBook.lineBar([]);
                    wbBook.page = 1;
                    wbBook.load();
                });
                $('#wbCards').on('click', 'button.wb-card', function() {
                    // Another sheet is loading: the cards on screen are those of the sheet that is being left (sorting and paging wait too).
                    if (wbBook.sheet !== wbBook.shown) return;
                    // attr(), not data(): "" must stay "" and not turn into something else.
                    const status = $(this).attr('data-status');
                    wbBook.status = wbBook.status === status ? '' : status;
                    wbBook.page = 1;
                    wbBook.load();
                });
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
