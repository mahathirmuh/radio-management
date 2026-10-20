// Runs the inline JavaScript of the SharePoint folder page (spUi / spDocs / spViewer) without a
// browser and checks what it does: escaping of names that come from SharePoint, which request a
// search, a header or a page link sends, which answer is shown, where the buttons point, how the
// file viewer steps.
//
// usage: node sharepoint-page-behaviour.mjs <file with the rendered page>
//
// The page's own script and the two global helper scripts it uses (debounce, renderTable...) are
// taken from the rendered HTML and run by page-sandbox.mjs, where "$" is a small stand-in for
// jQuery. At the end every element the script reached for by id must exist in the rendered page.
// PHPUnit calls this from SharePointPageScriptTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, isWellFormed, loadPage } from './page-sandbox.mjs';

const { html, node, exists, requests, appended, page, runTimers, fire, sent, answer, refuse, inside, ids } = loadPage(
    process.argv[2],
    ['function debounce(', 'function renderTableWithFeatures(', 'const spUi = {'],
    { href: 'http://localhost/documents/cof-scan', framePath: '/documents/cof-scan/file' }
);
const { check, done } = checker();
const [spUi, spDocs, spViewer] = [page('spUi'), page('spDocs'), page('spViewer')];
const table = () => node('#docsTable tbody').html;
// The files the pop-up shows: the frames the script made and has not taken out again.
const frames = () => inside('docsViewerBody');

const meta = total => ({ current_page: 1, from: total ? 1 : null, last_page: Math.max(1, Math.ceil(total / 25)), per_page: 25, to: total || null, total });
const folder = { path: '', web_url: 'https://example.com/folder-from-list', fetched_at: '01-01-2026 00:00', files: 3, folders: 0, size: '1,0 MB', truncated: false, stale: false };
const file = (code, extra = {}) => ({
    type: 'file', id: `ID${code}`, name: `${code} - Nama.pdf`, code, label: 'Nama', extension: 'pdf', size: '1 KB', modified: '01-01-2026 00:00',
    modified_by: 'Petugas', url: `https://example.com/${code}.pdf?web=1`, path: null, preview: true, ...extra,
});
const three = () => [file('ICTCOF1'), file('ICTCOF2', { preview: false }), file('ICTCOF3')];
// The table is on another page than the first (page 3): the answer to a request says so.
const onPage = (page = 3) => {
    spDocs.load();
    answer(requests.at(-1), { data: three(), meta: { ...meta(60), current_page: page }, folder });
    assert.equal(spDocs.page, page);
};

// ---------------------------------------------------------------- first load
check('the page asks for its list once, newest first', () => {
    assert.equal(requests.length, 1);
    assert.match(requests[0].url, /\/documents\/cof-scan\/list$/);
    assert.deepEqual(sent(), { page: 1, paginated: 25, search: '', sort: 'modified', dir: 'desc', path: '' });
    assert.equal(node('#docsReload').props.disabled, true);
    assert.match(table(), /Loading/);
});

check('rows are drawn with the type and the eye, and the folder facts are written', () => {
    answer(requests[0], { data: [file('ICTCOF1'), file('ICTCOF2', { extension: 'xlsx', preview: false })], meta: meta(2), folder });
    assert.equal(node('#docsReload').props.disabled, false);
    assert.match(table(), /<span class="docs-code">ICTCOF1<\/span>/);
    assert.match(table(), /<span class="badge bg-red-lt docs-kind">PDF<\/span>/);
    assert.match(table(), /<span class="badge bg-green-lt docs-kind">XLSX<\/span>/);
    assert.equal((table().match(/class="btn btn-sm btn-icon btn-outline-blue docs-view" data-index="0"/g) || []).length, 1);
    assert.doesNotMatch(table(), /data-index="1"/); // not shown here: a link to SharePoint instead
    assert.match(table(), /href="https:\/\/example\.com\/ICTCOF2\.pdf\?web=1" target="_blank" rel="noopener noreferrer" title="Tidak bisa ditampilkan di sini/);
    assert.equal(node('#docsInfo').text, '3 file · 1,0 MB · data SharePoint per 01-01-2026 00:00');
    assert.equal(node('#docsOpen').attrs.href, 'https://example.com/folder-from-list');
    assert.equal(node('#docsOpenWrap').classes.has('d-none'), false);
    assert.equal(node('#docsNotice').html, '');
    // Every cell of a row has a header cell above it.
    const cells = (table().match(/<tr>/g) || []).length ? (table().split('</tr>')[0].match(/<td/g) || []).length : 0;
    assert.equal(cells, (html.match(/<table[^>]*id="docsTable"[\s\S]*?<\/thead>/)[0].match(/<th\b/g) || []).length);
});

// ---------------------------------------------------------------- what comes from SharePoint is text
check('markup in names, types, people and links is escaped; only https becomes a link', () => {
    const hostile = [
        file('<img src=x onerror=alert(1)>', { name: '"><script>alert(2)</script>', label: '<b>bold</b>', extension: '<i>pdf</i>', size: '<u>1</u>', modified: '<s>now</s>', modified_by: '<script>alert(3)</script>', url: 'javascript:alert(4)', preview: false }),
        file('X1', { name: 'x" onmouseover="alert(5)', url: 'https://example.com/a"onmouseover="alert(6)', preview: false }),
        { type: 'folder', id: null, name: 'A & "B"', code: '', label: 'A & "B"', extension: '', size: null, modified: null, modified_by: null, url: null, path: 'A & "B"/C\'d', preview: false },
        file('Y1', { id: '"><img src=x onerror=alert(7)>' }),
    ];
    spDocs.render({ data: hostile, meta: meta(4) });
    const out = table();
    for (const raw of ['<img', '<script', '<b>', '<i>pdf', '<u>', '<s>', 'javascript:', 'onmouseover="alert', 'onerror=alert(7)']) {
        assert.equal(out.includes(raw), false, `unescaped: ${raw}`);
    }
    assert.match(out, /&lt;img src=x onerror=alert\(1\)&gt;/);
    assert.match(out, /href="https:\/\/example\.com\/a&quot;onmouseover=&quot;alert\(6\)"/);
    assert.match(out, /title="x&quot; onmouseover=&quot;alert\(5\)"/);
    assert.match(out, /<a href="#" class="docs-folder" data-path="A &amp; &quot;B&quot;\/C&#039;d">/);
    assert.match(out, /<span class="badge bg-secondary-lt docs-kind">&lt;I&gt;PDF&lt;\/I&gt;<\/span>/);
    assert.match(out, /docs-view" data-index="3" title="Lihat file"/); // the id itself is never written into the page
});

check('icons and colours are looked up safely', () => {
    assert.equal(spUi.pick(spUi.icons, 'constructor', 'file'), 'file');
    assert.equal(spUi.pick(spUi.icons, '__proto__', 'file'), 'file');
    assert.equal(spUi.pick(spUi.colors, 'pdf', 'secondary'), 'red');
    assert.equal(spUi.kind({ type: 'folder' }), '<span class="badge bg-yellow-lt docs-kind">Folder</span>');
    assert.match(spUi.kind({ type: 'file', extension: '' }), /docs-muted/);
    assert.equal(spUi.view({ type: 'folder', preview: true, url: 'https://example.com/' }, 0), '');
    assert.equal(spUi.view({ type: 'file', preview: false, url: 'http://example.com/x' }, 0), '');
    assert.equal(spUi.isLink('https://example.com'), true);
    assert.equal(spUi.isLink('//example.com'), false);
    assert.equal(spUi.isLink(null), false);
});

// ---------------------------------------------------------------- searching
check('a pause after typing searches; Enter searches at once and the pause then asks nothing', () => {
    onPage(3);
    const before = requests.length;
    node('#docsSearch').value = '  budi ';
    fire('#docsSearch', 'input');
    assert.equal(requests.length, before, 'nothing before the pause is over');
    runTimers();
    assert.equal(requests.length, before + 1);
    assert.equal(sent().search, 'budi');
    assert.equal(sent().page, 1);

    node('#docsSearch').value = 'andi';
    fire('#docsSearch', 'input');
    fire('#docsSearch', 'keydown', { key: 'Enter' });
    assert.equal(requests.length, before + 2);
    runTimers();
    assert.equal(requests.length, before + 2, 'the pause after Enter sends nothing new');
    fire('#docsSearch', 'keydown', { key: 'a' });
    assert.equal(requests.length, before + 2);
});

check('only the answer to the latest request is shown', () => {
    const [older, newer] = requests.slice(-2);
    answer(newer, { data: [file('ICTCOF9')], meta: meta(1), folder });
    answer(older, { data: [file('ICTCOF8')], meta: meta(1), folder });
    assert.match(table(), /ICTCOF9/);
    assert.doesNotMatch(table(), /ICTCOF8/);
    assert.equal(node('#docsReload').props.disabled, false);
});

// ---------------------------------------------------------------- the table's own buttons
check('a click on a column header sorts by it, a second click the other way', () => {
    spDocs.reset();
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });
    for (const key of ['name', 'label', 'extension', 'modified', 'size']) {
        assert.equal(exists(`#docsTable th[data-key="${key}"]`), true, `the page has no sortable header "${key}"`);
    }

    // The title is put into its cell by the global helper, as text, in the element it wraps it in.
    assert.match(node('#docsTable th[data-key="extension"]').html, /<span class="rm-sort-label "><\/span><span class="rm-sort" aria-hidden="true"><i class="ti ti-chevron-up"><\/i><\/span>/);
    assert.equal(node('#docsTable th[data-key="extension"] span.rm-sort-label').text, 'Tipe');

    onPage(3);
    fire('#docsTable th[data-key="extension"]', 'click'); // the "Tipe" header
    assert.deepEqual([sent().sort, sent().dir, sent().page], ['extension', 'asc', 1]);
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });
    fire('#docsTable th[data-key="extension"]', 'click');
    assert.deepEqual([sent().sort, sent().dir], ['extension', 'desc']);
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });
    fire('#docsTable th[data-key="modified"]', 'click');
    assert.deepEqual([sent().sort, sent().dir], ['modified', 'asc']);
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });
});

check('page links and the page size ask for that page; reset and "Muat ulang" for the first', () => {
    // The links are what the helper wrote: the page of a link is the one in its markup.
    assert.match(node(' .pagination-container').html, /<button type="button" class="rm-pager-page btn-pagination" data-page="3">3<\/button>/);
    node('(object)').data.page = 3;
    fire(' .pagination-container .btn-pagination, .btn-previous, .btn-next', 'click');
    assert.deepEqual([sent().page, sent().paginated, sent().sort], [3, 25, 'modified']);
    answer(requests.at(-1), { data: three(), meta: { ...meta(60), current_page: 3 }, folder });
    node('(object)').data.limit = 50;
    fire(' .pagination-container .pagination-limit', 'click');
    assert.deepEqual([sent().page, sent().paginated], [1, 50]);
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });

    onPage(3);
    node('#docsSearch').value = 'apa saja';
    spDocs.reset();
    assert.deepEqual(sent(), { page: 1, paginated: 50, search: '', sort: 'modified', dir: 'desc', path: '' });
    assert.equal(node('#docsSearch').value, '');
    spDocs.load(true);
    assert.equal(sent().refresh, 1);
    answer(requests.at(-1), { data: three(), meta: meta(3), folder });
});

// ---------------------------------------------------------------- failures and notices
check('a failed load says why in the reader\'s words and clears what no longer holds', () => {
    spDocs.load();
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.' } });
    assert.equal(table().includes('Daftar tidak bisa dimuat.'), true);
    assert.equal(node('#docsPage .pagination-container').hidden, true);
    assert.equal(node('#docsInfo').text, '');
    assert.match(node('#docsNotice').html, /alert-danger[^>]*>SharePoint sedang tidak bisa dihubungi/);
    assert.equal(spDocs.rows.length, 0);

    assert.match(spDocs.failure({ status: 401 }), /^Sesi Anda sudah berakhir/);
    assert.match(spDocs.failure({ status: 403, responseJSON: { message: 'You are not allowed to perform this action.' } }), /^Role Anda tidak punya izin Read/);
    assert.equal(spDocs.failure({ status: 500, responseJSON: { message: 'SQLSTATE secret detail' } }), 'Daftar tidak bisa dimuat. Coba lagi beberapa saat lagi.');
    assert.equal(spDocs.failure({ status: 0 }), 'Daftar tidak bisa dimuat. Coba lagi beberapa saat lagi.');
    assert.equal(spDocs.failure({ status: 404, responseJSON: { message: '<b>x</b>' } }), '<b>x</b>'); // escaped where it is shown:
    spDocs.notice([{ kind: 'danger', text: '<b>x</b>' }]);
    assert.match(node('#docsNotice').html, /&lt;b&gt;x&lt;\/b&gt;/);
});

check('an old or cut-off list says so', () => {
    spDocs.describe({ ...folder, folders: 2, stale: true, truncated: true });
    assert.equal(node('#docsInfo').text, '2 folder · 3 file · 1,0 MB · data SharePoint per 01-01-2026 00:00');
    assert.match(node('#docsNotice').html, /alert-warning[^>]*>SharePoint sedang tidak bisa dihubungi\. Yang tampil adalah daftar terakhir yang berhasil diambil \(01-01-2026 00:00\)\./);
    assert.match(node('#docsNotice').html, /alert-info[^>]*>Isi folder ini sangat banyak/);
});

// ---------------------------------------------------------------- "Buka di SharePoint" and folders below
check('"Buka di SharePoint" points to the folder being looked at, or is hidden', () => {
    const home = spDocs.home;
    spDocs.path = '';
    spDocs.target(null);
    assert.equal(node('#docsOpen').attrs.href, home || '#');
    assert.equal(node('#docsOpenWrap').classes.has('d-none'), !home);
    spDocs.target('javascript:alert(1)');
    assert.equal(node('#docsOpen').attrs.href, home || '#');

    // A click on a folder row: the handler takes the path from the element it is called on (from page 3 of the list it leaves).
    onPage(3);
    let prevented = false;
    node('(object)').attrs['data-path'] = '2025/Januari & Februari';
    fire('#docsPage', 'click a.docs-folder', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(sent().path, '2025/Januari & Februari');
    assert.equal(sent().page, 1);
    assert.equal(sent().search, '');
    assert.equal(node('#docsOpen').attrs.href, '#', 'no address known yet for the folder below');
    assert.equal(node('#docsOpenWrap').classes.has('d-none'), true);
    assert.match(node('#docsCrumbs').html, /data-path="">[^<]+<\/a>.*data-path="2025">2025<\/a>.*<b>Januari &amp; Februari<\/b>/);
    assert.equal(node('#docsCrumbs').classes.has('d-none'), false);
    answer(requests.at(-1), { data: [file('ICTCOF7')], meta: meta(1), folder: { ...folder, path: '2025/Januari & Februari', web_url: 'https://example.com/folder/2025/x' } });
    assert.equal(node('#docsOpen').attrs.href, 'https://example.com/folder/2025/x');
    assert.equal(node('#docsOpenWrap').classes.has('d-none'), false);
    spDocs.open('');
    assert.equal(node('#docsCrumbs').classes.has('d-none'), true);
    answer(requests.at(-1), { data: three(), meta: meta(3), folder });
});

// ---------------------------------------------------------------- the file viewer
check('the eye opens the file in the pop-up; stepping skips rows that cannot be shown', () => {
    spViewer.open(1); // not a row that can be shown here
    assert.equal(spViewer.at, -1);
    assert.equal(node('#docsViewer').modal, null);

    node('(object)').attrs['data-index'] = '0';
    fire('#docsTable', 'click button.docs-view');
    assert.equal(spViewer.at, 0);
    assert.equal(node('#docsViewer').modal, 'show');
    assert.equal(node('#docsViewerTitle').text, 'ICTCOF1 - Nama');
    assert.equal(node('#docsViewerPlace').text, '1 / 2');
    assert.deepEqual([node('#docsViewerPrevious').props.disabled, node('#docsViewerNext').props.disabled], [true, false]);
    const frame = appended.at(-1);
    assert.equal(frame.into, 'docsViewerBody');
    assert.equal(frame.element.id, 'docsViewerFrame');
    assert.equal(frames().length, 1);
    assert.match(frame.element.src, /\/documents\/cof-scan\/file\?path=&id=IDICTCOF1$/);
    assert.equal(node('#docsViewerTab').attrs.href, frame.element.src);
    assert.equal(node('#docsViewerSharePoint').attrs.href, 'https://example.com/ICTCOF1.pdf?web=1');
    assert.equal(node('#docsViewerWait').classes.has('d-none'), false, 'the notice shows while the file loads');
    frame.element.listeners.load();
    assert.equal(node('#docsViewerWait').classes.has('d-none'), true);

    fire('#docsViewerNext', 'click');
    assert.equal(spViewer.at, 2);
    assert.equal(node('#docsViewerPlace').text, '2 / 2');
    assert.deepEqual([node('#docsViewerPrevious').props.disabled, node('#docsViewerNext').props.disabled], [false, true]);
    assert.match(appended.at(-1).element.src, /id=IDICTCOF3$/);
    assert.deepEqual(frames(), [appended.at(-1).element], 'the file before it is taken out of the pop-up');
    fire('#docsViewerNext', 'click');
    assert.equal(spViewer.at, 2, 'nothing after the last one');
    fire('#docsViewerPrevious', 'click');
    assert.equal(spViewer.at, 0);
    assert.equal(frames().length, 1);
});

check('the pop-up keeps the list it was opened from when the table behind it is redrawn', () => {
    spDocs.render({ data: [file('ICTCOF50'), file('ICTCOF51'), file('ICTCOF52'), file('ICTCOF53')], meta: meta(4) });
    assert.equal(node('#docsViewerPlace').text, '1 / 2');
    fire('#docsViewerNext', 'click');
    assert.equal(node('#docsViewerTitle').text, 'ICTCOF3 - Nama');
    assert.equal(node('#docsViewerPlace').text, '2 / 2');
    spDocs.render({ data: three(), meta: meta(3) });
});

check('a file that takes long keeps its spinner and points to the other buttons', () => {
    spViewer.show(2);
    assert.equal(node('#docsViewerHint').text, 'Memuat file…');
    runTimers(); // no load event came
    assert.match(node('#docsViewerHint').text, /^Masih memuat….*tab baru/);
    assert.equal(node('#docsViewerSpinner').classes.has('d-none'), false);
    assert.equal(node('#docsViewerWait').classes.has('d-none'), false);
});

check('when the session has ended, the pop-up says so instead of showing the sign-in page', () => {
    spViewer.show(0);
    const frame = appended.at(-1).element;
    assert.deepEqual(frames(), [frame]);
    frame.contentWindow.location.pathname = '/login';
    frame.listeners.load();
    assert.equal(frames().length, 0, 'the sign-in page is taken out of the pop-up');
    assert.match(node('#docsViewerHint').text, /^Sesi Anda sudah berakhir/);
    assert.equal(node('#docsViewerSpinner').classes.has('d-none'), true);
    assert.equal(node('#docsViewerWait').classes.has('d-none'), false);
    runTimers();
    assert.match(node('#docsViewerHint').text, /^Sesi Anda sudah berakhir/, 'no "still loading" after that');

    // A frame whose address cannot be read is the browser's own viewer: that is the file.
    spViewer.show(0);
    const viewer = appended.at(-1).element;
    Object.defineProperty(viewer, 'contentWindow', { get() { throw new Error('cross-origin'); } });
    viewer.listeners.load();
    assert.equal(node('#docsViewerWait').classes.has('d-none'), true);
});

check('closing the pop-up takes the file off the page', () => {
    assert.equal(frames().length, 1);
    fire('#docsViewer', 'hidden.bs.modal');
    assert.equal(spViewer.at, -1);
    assert.equal(spViewer.rows.length, 0);
    assert.equal(frames().length, 0);
});

check('a file in a folder below is asked for with that folder\'s path', () => {
    spDocs.open('2025/Januari & Februari');
    answer(requests.at(-1), { data: [file('ICTCOF7')], meta: meta(1), folder: { ...folder, path: '2025/Januari & Februari' } });
    spViewer.open(0);
    assert.match(appended.at(-1).element.src, /\/file\?path=2025%2FJanuari\+%26\+Februari&id=IDICTCOF7$/);
    assert.equal(node('#docsViewerTab').attrs.href, appended.at(-1).element.src);
    fire('#docsViewer', 'hidden.bs.modal');
});

// ---------------------------------------------------------------- the sheet that lists the files
/** What the sheet says about a file: its own row, another row with its number, or none; and the note of the row. */
const listed = (status, line = null, extra = {}) => ({
    masterlist: { status, line, more: 0, url: status === 'unlinked' ? null : `/documents/ict-masterlist?sheet=COF&line=${line}`, ...extra.masterlist },
    note: extra.note ?? null,
});
/** The cells of each row of the table as they are drawn. */
const cellsOf = out => out.split('</tr>').map(row => (row.match(/<td[^>]*>(.*?)<\/td>/g) || []));

check('the page is made with the columns of the sheet when the reader may open it, the first of them sortable', () => {
    assert.deepEqual(JSON.parse(JSON.stringify(spDocs.register)), { title: 'ICT Masterlist', sheet: 'COF', open: '/documents/ict-masterlist' });
    assert.equal(exists('#docsTable th[data-key="masterlist"]'), true);
    assert.match(html, /<th class="text-capitalize fs-5 py-3" width="200">Catatan<\/th>/);
    // The headers are in the order of the cells: Masterlist, then Catatan, both after the name and before the type.
    assert.match(html, /<th [^>]*data-key="label">Nama<\/th>\s*<th [^>]*data-key="masterlist"[^>]*>Masterlist<\/th>\s*<th [^>]*>Catatan<\/th>\s*<th [^>]*data-key="extension"/);
    // The name is given room beside them (the rule is in the style of the page, and the table has the class it is for).
    assert.match(html, /<table class="table table-vcenter mdka-border-gray-100 mb-0 docs-register" id="docsTable">/);
    assert.match(html, /#docsTable\.docs-register \.docs-name \{\s*min-width: 13rem;/);
    assert.match(html, /Kolom Masterlist dan Catatan dari sheet COF di ICT Masterlist/);
    assert.equal(spDocs.columns.length, 'index name label masterlist note extension modified modified_by size view'.split(' ').length);

    onPage(3);
    fire('#docsTable th[data-key="masterlist"]', 'click');
    assert.deepEqual([sent().sort, sent().dir, sent().page], ['masterlist', 'asc', 1]);
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });
    spDocs.reset();
    answer(requests.at(-1), { data: three(), meta: meta(60), folder });
});

check('every file says which row of the sheet names it, the way to that row, and the note of the row', () => {
    const rows = [
        file('ICTCOF1', listed('linked', 12, { note: 'Menyusul' })),
        file('ICTCOF2', listed('differs', 3, { masterlist: { more: 1 } })),
        file('ICTCOF9', listed('unlinked')),
        { type: 'folder', id: null, name: 'Lama', code: '', label: 'Lama', extension: '', size: null, modified: null, modified_by: null, url: null, path: 'Lama', preview: false },
        file('ICTCOF5'), // nothing told: no such sheet could be read
    ];
    spDocs.render({ data: rows, meta: meta(5) });
    const out = table();

    // The way to the row is the badge itself, and what it leads to is told in its tooltip (a tooltip on the link would be covered by it) and,
    // for a screen reader, in the name of the link.
    assert.match(out, /<a href="\/documents\/ict-masterlist\?sheet=COF&amp;line=12" class="text-decoration-none" aria-label="Baris 12: lihat barisnya di ICT Masterlist"><span class="badge bg-green-lt docs-kind" title="Baris di Masterlist yang menyebut file ini\. Klik untuk melihat barisnya di ICT Masterlist">Baris 12<\/span><\/a>/);
    assert.doesNotMatch(out, /<a [^>]*title="Lihat/);
    assert.match(out, /<span class="docs-note" title="Menyusul">Menyusul<\/span>/);
    // Another row has the number: that the link there is empty or names another file is said, and so is that other rows have it too.
    assert.match(out, /<a href="\/documents\/ict-masterlist\?sheet=COF&amp;line=3" class="text-decoration-none" aria-label="Nama beda · baris 3 \+1: lihat barisnya di ICT Masterlist"><span class="badge bg-orange-lt docs-kind" title="Ada baris dengan nomor ini, tetapi link-nya kosong atau menyebut nama file yang lain \(atau file ini salinan ganda dari file yang sudah disebut\) \(dan 1 baris lain\)\. Klik untuk melihat barisnya di ICT Masterlist">Nama beda · baris 3 \+1<\/span><\/a>/);
    assertWellFormed(out, 'the rows of the folder');
    // A file with no row is told so and has nowhere to go; a folder and a file that was not told get nothing to read.
    assert.match(out, /<span class="badge bg-red-lt docs-kind" title="Tidak ada baris di Masterlist untuk file ini">Tidak ada baris<\/span>/);
    assert.equal((out.match(/badge bg-red-lt docs-kind" title="Tidak ada baris/g) || []).length, 1, 'only the file without a row says so');
    const cells = cellsOf(out);
    assert.equal(cells.filter(row => row.length).every(row => row.length === 10), true, 'every row has all ten cells');
    // Each of the two cells is under its own header: the row under "Masterlist", the note under "Catatan"; the badge is not broken in two.
    assert.match(cells[0][3], /^<td class="text-nowrap">/);
    assert.match(cells[0][3], /Baris 12/);
    assert.doesNotMatch(cells[0][3], /Menyusul/);
    assert.match(cells[0][4], /<span class="docs-note" title="Menyusul">Menyusul<\/span>/);
    assert.doesNotMatch(cells[0][4], /Baris/);
    assert.match(cells[1][3], /Nama beda/);
    assert.match(cells[1][4], /docs-muted">-</, 'a file whose row has no note has a dash');
    // The row of the folder has empty cells there; the file that was not told has a dash.
    assert.match(cells[3][3], /^<td[^>]*><\/td>$/);
    assert.match(cells[3][4], /^<td[^>]*><\/td>$/);
    assert.match(cells[4][3], /docs-muted">-</);
    assert.match(cells[4][4], /docs-muted">-</);
});

check('what the sheet says is text, only a path of the application is followed, and only a known state is drawn', () => {
    const hostile = [
        file('A1', { masterlist: { status: 'linked', line: 4, more: 0, url: 'javascript:alert(1)' }, note: '<img src=x onerror=alert(2)>' }),
        file('A2', { masterlist: { status: 'linked', line: 5, more: 0, url: '//evil.example/x' }, note: '"><script>alert(3)</script>' }),
        file('A3', { masterlist: { status: 'linked', line: 6, more: 0, url: 'https://evil.example/x' }, note: 'a & b' }),
        file('A4', { masterlist: { status: 'hax" onmouseover="alert(4)', line: 7, more: 0, url: '/x' }, note: null }),
        file('A5', { masterlist: { status: 'linked', line: '<b>9</b>', more: '<i>2</i>', url: '/documents/ict-masterlist?line=9' }, note: null }),
        file('A6', { masterlist: { status: 'constructor', line: 1, more: 0, url: null }, note: null }),
        file('A7', { masterlist: { status: 'linked', line: 8, more: 0, url: '/\\evil.example/x' }, note: null }),
        file('A8', { masterlist: { status: 'linked', line: 10, more: 0, url: '/\t/evil.example/x' }, note: null }),
        file('A9', { masterlist: { status: 'linked', line: null, more: 0, url: null }, note: null }),
        file('B1', { masterlist: { status: 'linked', line: 0, more: 0, url: null }, note: null }),
        file('B2', { masterlist: { status: 'linked', line: '7', more: 0, url: null }, note: null }),
        file('B3', { masterlist: { status: 'linked', line: 3, more: '0x10', url: null }, note: null }),
    ];
    spDocs.render({ data: hostile, meta: meta(12) });
    const out = table();
    // How many other rows is a number, whatever it is written as: not the text that came.
    assert.match(out, /Baris 3 \+16</);
    assert.doesNotMatch(out, /0x10/);

    for (const raw of ['<img', '<script', '<b>', '<i>', 'javascript:', 'evil.example', 'onmouseover="alert']) {
        assert.equal(out.includes(raw), false, `unescaped: ${raw}`);
    }
    assert.match(out, /title="&lt;img src=x onerror=alert\(2\)&gt;">&lt;img src=x onerror=alert\(2\)&gt;<\/span>/);
    assert.match(out, /title="&quot;&gt;&lt;script&gt;alert\(3\)&lt;\/script&gt;">/);
    assert.match(out, /title="a &amp; b">a &amp; b<\/span>/);
    // The badge is there, the link is not: not a path of this application.
    assert.equal((out.match(/Baris 4</g) || []).length, 1);
    assert.doesNotMatch(out, /<a href="javascript|<a href="\/\/|<a href="https:\/\/evil|<a href="\/\\|<a href="\/\t/);
    // A state that this page does not know, and one that is a property of every object: a dash.
    assert.doesNotMatch(out, /alert\(4\)|constructor/);
    const cells = cellsOf(out);
    assert.match(cells[3][3], /docs-muted">-</, 'the state that is not known is a dash');
    assert.match(cells[5][3], /docs-muted">-</, 'so is a property of every object');
    // A row number that is no number of a row is a question mark, whatever it looks like.
    assert.equal((out.match(/Baris \?</g) || []).length, 4);
    for (const [url, is] of [['/a', true], ['/', true], ['/documents/ict-masterlist?sheet=COF&line=2', true], ['//a', false], ['/\\a', false], ['/a\\b', false], ['/\ta', false], ['/ a', false], ['a/b', false], ['https://a/b', false], ['', false], [null, false], [undefined, false], [5, false]]) {
        assert.equal(spUi.isPath(url), is, JSON.stringify(url));
    }

    // The name of the workbook in the tooltip of a badge is text too, and so is a path with marks in it that the value of an attribute does not take.
    const register = spDocs.register;
    spDocs.register = { ...register, title: 'ICT "x" <b>' };
    try {
        spDocs.render({ data: [file('C1', { masterlist: { status: 'linked', line: 4, more: 0, url: '/x"onmouseover="alert(1)' }, note: null })], meta: meta(1) });
        const quoted = table();
        assert.match(quoted, /Klik untuk melihat barisnya di ICT &quot;x&quot; &lt;b&gt;"/);
        assert.doesNotMatch(quoted, /<b>|"onmouseover=/);
        assert.match(quoted, /<a href="\/x&quot;onmouseover=&quot;alert\(1\)" class="text-decoration-none" aria-label="Baris 4: lihat barisnya di ICT &quot;x&quot; &lt;b&gt;">/);
    } finally {
        spDocs.register = register;
    }
});

check('the sheet that lists the files tells how old it is, and why its columns are empty', () => {
    spDocs.describe(folder, { title: 'ICT Masterlist', sheet: 'COF', fetched_at: '01-01-2026 00:05', stale: false });
    assert.equal(node('#docsInfo').text, '3 file · 1,0 MB · data SharePoint per 01-01-2026 00:00 · ICT Masterlist per 01-01-2026 00:05');
    assert.equal(node('#docsNotice').html, '');

    spDocs.describe(folder, { title: 'ICT <Masterlist>', sheet: 'COF', fetched_at: '<u>tadi</u>', stale: true });
    assert.match(node('#docsNotice').html, /alert-warning[^>]*>SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab\. Kolom Masterlist dan Catatan memakai isi terakhir ICT &lt;Masterlist&gt; yang berhasil diambil \(&lt;u&gt;tadi&lt;\/u&gt;\)\./);

    spDocs.describe(folder, { title: 'ICT Masterlist', unavailable: 'Kolom Masterlist dan Catatan belum bisa ditampilkan. <b>x</b>' });
    assert.match(node('#docsNotice').html, /alert-info[^>]*>Kolom Masterlist dan Catatan belum bisa ditampilkan\. &lt;b&gt;x&lt;\/b&gt;/);
    assert.equal(node('#docsInfo').text, '3 file · 1,0 MB · data SharePoint per 01-01-2026 00:00', 'no time of the sheet when it could not be read');

    // An answer of the server carries what is said of the sheet, and the page says it (not only when it is asked directly).
    spDocs.load();
    answer(requests.at(-1), { data: three(), meta: meta(3), folder, register: { title: 'ICT Masterlist', sheet: 'COF', fetched_at: '01-01-2026 00:07', stale: false } });
    assert.equal(node('#docsInfo').text, '3 file · 1,0 MB · data SharePoint per 01-01-2026 00:00 · ICT Masterlist per 01-01-2026 00:07');
    spDocs.load();
    answer(requests.at(-1), { data: three(), meta: meta(3), folder, register: { title: 'ICT Masterlist', unavailable: 'Kolom Masterlist dan Catatan belum bisa ditampilkan.' } });
    assert.match(node('#docsNotice').html, /alert-info[^>]*>Kolom Masterlist dan Catatan belum bisa ditampilkan\./);

    // A page without the sheet says nothing of it.
    spDocs.describe(folder);
    assert.equal(node('#docsInfo').text, '3 file · 1,0 MB · data SharePoint per 01-01-2026 00:00');
    assert.equal(node('#docsNotice').html, '');
});

check('a page made without the sheet has the columns of the folder only', () => {
    const was = spDocs.register;
    spDocs.register = null;
    spDocs.build();
    try {
        assert.equal(spDocs.columns.length, 'index name label extension modified modified_by size view'.split(' ').length);
        spDocs.render({ data: [file('ICTCOF1', listed('linked', 12, { note: 'Menyusul' }))], meta: meta(1) });
        assert.doesNotMatch(table(), /Baris 12|docs-note|Menyusul/);
        assert.equal((table().split('</tr>')[0].match(/<td/g) || []).length, 8);
    } finally {
        spDocs.register = was;
        spDocs.build();
    }
});

check('a link from the page of the workbook opens the folder with the number searched for', () => {
    const linked = loadPage(
        process.argv[2],
        ['function debounce(', 'function renderTableWithFeatures(', 'const spUi = {'],
        { href: 'http://localhost/documents/cof-scan?search=%20ICTCOF123%20', framePath: '/documents/cof-scan/file' }
    );
    assert.equal(linked.requests.length, 1);
    assert.deepEqual(linked.sent(), { page: 1, paginated: 25, search: 'ICTCOF123', sort: 'modified', dir: 'desc', path: '' });
    assert.equal(linked.node('#docsSearch').value, 'ICTCOF123');

    // Anything longer than the box takes is cut, and nothing at all is no search.
    const long = loadPage(process.argv[2], ['function debounce(', 'function renderTableWithFeatures(', 'const spUi = {'], { href: `http://localhost/documents/cof-scan?search=${'x'.repeat(300)}` });
    assert.equal(long.sent().search.length, 100);
    const none = loadPage(process.argv[2], ['function debounce(', 'function renderTableWithFeatures(', 'const spUi = {'], { href: 'http://localhost/documents/cof-scan?search=' });
    assert.equal(none.sent().search, '');
});

check('a link from the page of the workbook asks for the files of one document number, says so, and can be given up', () => {
    const open = query => loadPage(
        process.argv[2],
        ['function debounce(', 'function renderTableWithFeatures(', 'const spUi = {'],
        { href: `http://localhost/documents/cof-scan?${query}`, framePath: '/documents/cof-scan/file' }
    );
    const linked = open('code=%20ICTCOF4%20');
    assert.deepEqual(linked.sent(), { page: 1, paginated: 25, search: '', sort: 'modified', dir: 'desc', path: '', code: 'ICTCOF4' });
    assert.match(html, /<div class="alert alert-info py-2 small mt-2 mb-0 text-break d-none" id="docsCodeBar">/, 'not said before the answer says it');
    // The bar is said by a live region that is there from the start, with the bar in it.
    assert.match(html, /<div role="status">\s*<div class="alert alert-info[^"]*" id="docsCodeBar">/);
    assert.match(html, /data-bs-title="Reset pencarian, pilihan nomor, dan urutan"/);
    assert.doesNotMatch(html, /id="docsCodeBar"[^>]*role=/);

    // The answer says which number it was limited to: that is said, and kept for the next request.
    linked.answer(linked.requests[0], { data: [file('ICTCOF4')], meta: meta(1), folder: { ...folder, code: 'ICTCOF4' } });
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), false);
    assert.equal(linked.node('#docsCodeText').text, 'Hanya file dengan nomor ICTCOF4.');
    assert.match(html, /id="docsCodeClear"[^>]*>Tampilkan semua file<\/a>/);
    const docs = linked.page('spDocs');
    docs.load();
    assert.equal(linked.sent().code, 'ICTCOF4');

    // No file has it: the table says which number.
    linked.answer(linked.requests.at(-1), { data: [], meta: meta(0), folder: { ...folder, code: 'ICTCOF77' } });
    assert.match(linked.node('#docsTable tbody').html, /Tidak ada file dengan nomor ICTCOF77\./);
    assert.doesNotMatch(linked.node('#docsTable tbody').html, /Folder ini kosong/);
    docs.search = 'budi';
    docs.load();
    linked.answer(linked.requests.at(-1), { data: [], meta: meta(0), folder: { ...folder, code: 'ICTCOF77' } });
    assert.match(linked.node('#docsTable tbody').html, /Tidak ada file dengan nomor ICTCOF77 yang cocok dengan pencarian\./);
    docs.search = '';
    // With no number the words are those of the folder: it is empty, or nothing in it matches what is searched for.
    docs.code = '';
    docs.load();
    linked.answer(linked.requests.at(-1), { data: [], meta: meta(0), folder });
    assert.match(linked.node('#docsTable tbody').html, /Folder ini kosong\./);
    docs.search = 'budi';
    docs.load();
    linked.answer(linked.requests.at(-1), { data: [], meta: meta(0), folder });
    assert.match(linked.node('#docsTable tbody').html, /Tidak ada dokumen yang cocok dengan pencarian\./);
    assert.doesNotMatch(linked.node('#docsTable tbody').html, /Folder ini kosong/);
    docs.search = '';
    // A number that is not the page's own is text where it is shown as markup.
    docs.code = '<b>x</b>';
    assert.doesNotMatch(String(linked.node('#docsTable tbody').html), /<b>x/);
    docs.load();
    linked.answer(linked.requests.at(-1), { data: [], meta: meta(0), folder: { ...folder, code: '<b>x</b>' } });
    assert.doesNotMatch(linked.node('#docsTable tbody').html, /<b>/);
    assert.match(linked.node('#docsTable tbody').html, /nomor &lt;b&gt;x&lt;\/b&gt;\./);
    assert.equal(linked.node('#docsCodeText').text, 'Hanya file dengan nomor <b>x</b>.', 'as text');

    // Giving it up: the next request has none, and starts at the first page; the link that is clicked is not followed.
    let prevented = 0;
    docs.code = 'ICTCOF4';
    docs.codeBar();
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), false);
    docs.page = 3;
    linked.fire('#docsCodeClear', 'click', { preventDefault() { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal('code' in linked.sent(), false);
    assert.equal(linked.sent().page, 1);
    assert.equal(docs.code, '');
    // The bar goes at once, not when the answer comes: the answer may be a failure, and the bar would go on saying what is no longer so.
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), true);
    assert.equal(linked.node('#docsCodeText').text, '');
    linked.refuse(linked.requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), true);
    docs.load();
    linked.answer(linked.requests.at(-1), { data: three(), meta: meta(3), folder });
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), true);
    assert.equal(linked.node('#docsCodeText').text, '');

    // The same for a folder that is opened and for "reset": a failure after them does not leave the bar up.
    docs.code = 'ICTCOF4';
    docs.codeBar();
    docs.open('Lama');
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), true);
    linked.refuse(linked.requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    docs.open('');
    linked.answer(linked.requests.at(-1), { data: three(), meta: meta(3), folder });
    docs.code = 'ICTCOF4';
    docs.codeBar();
    docs.reset();
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), true);
    linked.refuse(linked.requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(linked.node('#docsCodeBar').classes.has('d-none'), true);
    docs.load();
    linked.answer(linked.requests.at(-1), { data: three(), meta: meta(3), folder });

    // The link that gives the number up has the focus when it is used with the keyboard, and goes with its bar: the search box gets the focus.
    const document = linked.page('document');
    const focusTo = () => linked.node('#docsSearch').triggered.filter(event => event === 'focus').length;
    const before = focusTo();
    docs.code = 'ICTCOF4';
    document.activeElement = { id: 'docsCodeClear' };
    docs.codeBar();
    assert.equal(focusTo(), before, 'the link stays: the number is still asked for');
    docs.code = '';
    docs.codeBar();
    assert.equal(focusTo(), before + 1);
    document.activeElement = { id: 'docsSearch' };
    docs.codeBar();
    assert.equal(focusTo(), before + 1, 'something else has the focus: it stays there');
    delete document.activeElement;
    docs.codeBar();
    assert.equal(focusTo(), before + 1, 'nothing has it');

    // What the server did not apply is not kept (and what it says that is no text is nothing); a folder opened and "reset" ask for all the files.
    docs.code = 'ICTCOF4';
    docs.load();
    linked.answer(linked.requests.at(-1), { data: three(), meta: meta(3), folder });
    assert.equal(docs.code, '');
    for (const odd of [123, ['ICTCOF4'], { a: 1 }, true]) {
        docs.code = 'ICTCOF4';
        docs.load();
        linked.answer(linked.requests.at(-1), { data: three(), meta: meta(3), folder: { ...folder, code: odd } });
        assert.equal(docs.code, '', JSON.stringify(odd));
        assert.equal(linked.node('#docsCodeText').text, '');
    }
    docs.code = 'ICTCOF4';
    docs.open('Lama');
    assert.equal('code' in linked.sent(), false);
    linked.answer(linked.requests.at(-1), { data: three(), meta: meta(3), folder: { ...folder, path: 'Lama' } });
    docs.code = 'ICTCOF4';
    docs.reset();
    assert.equal('code' in linked.sent(), false);

    // What is asked for is the text as it stands (the server tells whether it is a document number), up to 400 characters, the longest name a
    // file can have (the number of a file with no separator in its name is all of it): the number of "ICTCOF55 (2) - Dewi.pdf" is
    // "ICTCOF55 (2)"; nothing at all is asked for when nothing is there.
    for (const asked of ['ICTCOF55 (2)', 'ICT/COF/4', 'ICT-COF.4_a', 'a,b', 'a&b', '<b>x</b>', 'x'.repeat(40), 'Tanda Terima Laptop Dell Latitude 5420 Pak Budi', 'x'.repeat(400)]) {
        assert.equal(open(`code=${encodeURIComponent(asked)}`).sent().code, asked, JSON.stringify(asked));
    }
    assert.equal(open(`code=${'x'.repeat(450)}`).sent().code, 'x'.repeat(400));
    // The cut is between characters, never in the middle of one that is written with two halves (a request cannot carry half of one).
    const cut = open(`code=${encodeURIComponent('a'.repeat(399) + '😀' + 'b')}`);
    assert.equal(cut.sent().code, 'a'.repeat(399) + '😀');
    assert.equal(isWellFormed(cut.sent().code), true);
    const search = open(`search=${encodeURIComponent('a'.repeat(99) + '😀' + 'b')}`);
    assert.equal(search.sent().search, 'a'.repeat(99) + '😀');
    assert.equal(isWellFormed(search.sent().search), true);
    // Both can be asked for together.
    assert.deepEqual(open('search=budi&code=ICTCOF4').sent(), { page: 1, paginated: 25, search: 'budi', sort: 'modified', dir: 'desc', path: '', code: 'ICTCOF4' });
    for (const none of ['', '%20', '+', '%20%20']) {
        assert.equal('code' in open(`code=${none}`).sent(), false, JSON.stringify(none));
    }
    // A number that the server did not take for one is not kept: its answer says nothing of it.
    const odd = open('code=%3C%3E');
    odd.answer(odd.requests[0], { data: three(), meta: meta(3), folder });
    assert.equal(odd.page('spDocs').code, '');
    assert.equal(odd.node('#docsCodeText').text, '');
});

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const used = ids();
    used.delete('docsViewerFrame'); // made by the script when a file is opened
    assert.ok(used.size >= 20, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(html), `the page has no element with id="${id}"`);
    }
    assert.ok(/class="[^"]*\bpagination-container\b/.test(html), 'the page has no .pagination-container');
});

done();
