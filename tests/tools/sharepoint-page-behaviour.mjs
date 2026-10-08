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
import { checker, loadPage } from './page-sandbox.mjs';

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
    assert.match(node('#docsTable th[data-key="extension"]').html, /<span class="flex-grow-1 text-wrap "><\/span>/);
    assert.equal(node('#docsTable th[data-key="extension"] span.flex-grow-1').text, 'Tipe');

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
    assert.match(node(' .pagination-container').html, /<a data-page="3" class="btn-pagination /);
    node('(object)').data.page = 3;
    fire(' .pagination-container .btn-pagination, .btn-previous, .btn-next', 'click');
    assert.deepEqual([sent().page, sent().paginated, sent().sort], [3, 25, 'modified']);
    answer(requests.at(-1), { data: three(), meta: { ...meta(60), current_page: 3 }, folder });
    node('(object)').value = '50';
    fire(' .pagination-container .pagination-limit', 'change');
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

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const used = ids();
    used.delete('docsViewerFrame'); // made by the script when a file is opened
    assert.ok(used.size >= 18, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(html), `the page has no element with id="${id}"`);
    }
    assert.ok(/class="[^"]*\bpagination-container\b/.test(html), 'the page has no .pagination-container');
});

done();
