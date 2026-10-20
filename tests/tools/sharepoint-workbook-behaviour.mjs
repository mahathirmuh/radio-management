// Runs the inline JavaScript of the SharePoint workbook page (wbUi / wbBook) without a browser
// and checks what it does: escaping of everything that comes from the workbook, which request a
// search, a tab, a title or a page link sends, which answer is shown, what the tabs say.
//
// usage: node sharepoint-workbook-behaviour.mjs <file with the rendered page>
//
// The page's own script and the two global helper scripts it uses (debounce, renderTable...) are
// taken from the rendered HTML and run by page-sandbox.mjs, where "$" is a small stand-in for
// jQuery. At the end every element the script reached for by id must exist in the rendered page.
// PHPUnit calls this from SharePointPageScriptTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, isWellFormed, loadPage } from './page-sandbox.mjs';

const { html, $, node, exists, requests, page, runTimers, fire, sent, answer, refuse, written, ids } = loadPage(
    process.argv[2],
    ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'],
    { href: 'http://localhost/documents/ict-masterlist' }
);
const { check, done } = checker();
const [wbUi, wbBook] = [page('wbUi'), page('wbBook')];
const body = () => node('#wbTable tbody').html;
const tabs = () => node('#wbTabs').html;
// What a title cell shows, and the title cells there are (in the order of the columns).
const title = key => $(`#wbTable th[data-key="${key}"]`).text().trim();
const titles = () => ['row', ...Array.from({ length: 10 }, (_, at) => `c${at}`)].filter(key => exists(`#wbTable th[data-key="${key}"]`)).map(title);

const meta = (total, current = 1) => ({ current_page: current, from: total ? 1 : null, last_page: Math.max(1, Math.ceil(total / 25)), per_page: 25, to: total || null, total });
const workbook = { name: 'Register.xlsx', modified: '06-10-2026 09:00', fetched_at: '07-10-2026 10:00', web_url: 'https://example.com/Doc.aspx?sourcedoc=1', stale: false };
const columns = [['No', 'Nama', 'Tanggal'], ['No. COF', 'Penerima'], []];
const names = ['SRF', 'COF', 'Kosong'];
// The answer for one sheet. matches: per sheet how many rows match what is searched for (null: nothing is).
const reply = (index, { matches = [null, null, null], sheet = {}, data = null, total = null, book = {} } = {}) => {
    const rows = data ?? [3, 2].map(line => ({ line, cells: columns[index].map(name => `${name} ${line}`) }));
    return {
        sheets: names.map((name, at) => ({ index: at, name, rows: [120, 60, 0][at], matches: matches[at] })),
        sheet: { index, name: names[index], columns: columns[index], sort: 'row', dir: 'desc', truncated: false, ...sheet },
        data: rows,
        meta: meta(total ?? rows.length),
        workbook: { ...workbook, ...book },
    };
};
const tab = (at, others = {}) => {
    node('(object)').attrs['data-sheet'] = String(at);

    return fire('#wbTabs', 'click a.wb-tab', { preventDefault() {} }, { member: '#wbTabs a.wb-tab', ...others });
};
// The table is on another page than the first (page 3 of the sheet that is open): the answer to a request says so.
const onPage = (page = 3) => {
    wbBook.load();
    answer(requests.at(-1), { ...reply(wbBook.sheet, { sheet: { sort: wbBook.sort, dir: wbBook.dir } }), meta: { ...meta(100), current_page: page } });
    assert.equal(wbBook.page, page);
};

// ---------------------------------------------------------------- first load
check('the page asks for the first sheet once, in the order of the workbook with the last row first', () => {
    assert.equal(requests.length, 1);
    assert.match(requests[0].url, /\/documents\/ict-masterlist\/rows$/);
    assert.deepEqual(sent(), { tab: 0, page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc' });
    assert.equal(node('#wbReload').props.disabled, true);
    assert.match(body(), /Loading/);
});

check('tabs, titles and rows are drawn, and the facts of the workbook are written', () => {
    answer(requests[0], reply(0));
    assert.equal(node('#wbReload').props.disabled, false);
    assert.equal((tabs().match(/class="nav-link wb-tab /g) || []).length, 3);
    assert.match(tabs(), /class="nav-link wb-tab active" aria-current="true" data-sheet="0">SRF <span class="badge bg-secondary-lt ms-1">120<\/span>/);
    assert.match(tabs(), /class="nav-link wb-tab " data-sheet="1">COF <span class="badge bg-secondary-lt ms-1">60<\/span>/);
    assert.deepEqual(titles(), ['Baris', 'No', 'Nama', 'Tanggal']);
    // The title is put into its cell by the global helper, as text, in the element it wraps it in.
    assert.match(node('#wbTable th[data-key="c0"]').html, /<span class="rm-sort-label "><\/span><span class="rm-sort" aria-hidden="true"><i class="ti ti-chevron-up"><\/i><\/span>/);
    assert.equal(node('#wbTable th[data-key="c0"] span.rm-sort-label').text, 'No');
    // Links to sheets, the current one marked as such: the page does not promise the keyboard model of ARIA tabs.
    assert.match(tabs(), /<a href="#" class="nav-link wb-tab active" aria-current="true" data-sheet="0">SRF/);
    assert.match(tabs(), /<a href="#" class="nav-link wb-tab " data-sheet="1">COF/);
    assert.doesNotMatch(tabs(), /role=|aria-selected/);
    assert.equal((tabs().match(/aria-current/g) || []).length, 1);
    assert.match(body(), /^<tr><td class="wb-line">3<\/td><td class="wb-cell"><span title="No 3">No 3<\/span><\/td>/);
    // Every cell of a row has a title cell above it.
    assert.equal((body().split('</tr>')[0].match(/<td/g) || []).length, titles().length);
    assert.equal(node('#wbInfo').text, '2 baris di sheet "SRF" · workbook terakhir diubah 06-10-2026 09:00 · data SharePoint per 07-10-2026 10:00');
    assert.equal(node('#wbOpen').attrs.href, 'https://example.com/Doc.aspx?sourcedoc=1');
    assert.equal(node('#wbOpenWrap').classes.has('d-none'), false);
    assert.equal(node('#wbNotice').html, '');
    // What the script wrote is markup a browser can lay out: its tags are tags, and each is closed.
    assertWellFormed(tabs(), 'the tabs');
    assertWellFormed(node('#wbTable thead').html, 'the table head');
    assertWellFormed(body(), 'the rows');
    assert.match(node('#wbTable thead').html, /^<tr class="mdka-bg-gray-50"><th [^>]*data-key="row"[^>]*>Baris<\/th><th [^>]*data-key="c0"[^>]*>No<\/th>/);
});

check('what the page shows before the first answer: the parts that need an answer are hidden', () => {
    // (Read from the markup: the sandbox does not parse it.) A failed first load would otherwise leave an empty bar and empty cards on screen.
    assert.match(html, /<div class="alert alert-info py-2 small mt-2 mb-0 d-none" id="wbLineBar">/);
    assert.match(html, /<div class="col-12 mt-2 d-none" id="wbCardsWrap">/);
    assert.match(html, /<span class="ms-md-auto d-none" id="wbOpenWrap">/);
    // The bar is said by a live region that is there from the start, with the bar in it.
    assert.match(html, /<div role="status">\s*<div class="alert alert-info[^"]*" id="wbLineBar">/);
    assert.doesNotMatch(html, /id="wbLineBar"[^>]*role=/);
    assert.match(html, /<label for="wbSearch" class="form-label" id="wbSearchLabel">Cari di semua kolom<\/label>/);
    // The reset button says what it forgets, the rows that were asked for included.
    assert.match(html, /data-bs-title="Reset pencarian, kartu dan baris yang dipilih, dan urutan"/);
});

// ---------------------------------------------------------------- what comes from the workbook is text
check('markup in titles, sheet names, cells and facts is shown as text; only https becomes the Excel link', () => {
    const hostile = reply(0, {
        sheet: { name: '<b>SRF</b>', columns: ['<img src=x onerror=alert(1)>', 'A & "B"', "O'Neil"] },
        data: [{ line: '<i>7</i>', cells: ['<script>alert(2)</script>', '" onmouseover="alert(3)', 'biasa'] }],
        book: { web_url: 'javascript:alert(4)', modified: '<s>kemarin</s>', fetched_at: '<u>tadi</u>', stale: true },
    });
    hostile.sheets[0].name = '<img src=x onerror=alert(5)>';
    hostile.sheets[1] = { index: '1"><img src=x onerror=alert(6)>', name: 'COF', rows: '<b>9</b>', matches: null };
    wbBook.load();
    answer(requests.at(-1), hostile);

    const out = written();
    for (const raw of ['<img', '<script', '<b>', '<i>', '<s>', '<u>', 'onmouseover="alert', 'javascript:']) {
        assert.equal(out.includes(raw), false, `unescaped: ${raw}`);
    }
    // The titles are put into the table by the global helper, which wraps them for sorting.
    assert.deepEqual(titles(), ['Baris', '<img src=x onerror=alert(1)>', 'A & "B"', "O'Neil"]);
    assert.match(tabs(), /data-sheet="0">&lt;img src=x onerror=alert\(5\)&gt; <span/);
    assert.match(tabs(), /data-sheet="NaN">COF <span class="badge bg-secondary-lt ms-1">NaN<\/span>/);
    assert.match(body(), /<td class="wb-line">&lt;i&gt;7&lt;\/i&gt;<\/td>/);
    assert.match(body(), /<span title="&quot; onmouseover=&quot;alert\(3\)">&quot; onmouseover=&quot;alert\(3\)<\/span>/);
    assert.match(node('#wbNotice').html, /alert-warning[^>]*>SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab\. Yang tampil adalah isi terakhir yang berhasil diambil \(&lt;u&gt;tadi&lt;\/u&gt;\)\./);
    assert.equal(node('#wbInfo').text, '1 baris di sheet "<b>SRF</b>" · workbook terakhir diubah <s>kemarin</s> · data SharePoint per <u>tadi</u>');
    assert.equal(node('#wbOpen').attrs.href, '#');
    assert.equal(node('#wbOpenWrap').classes.has('d-none'), true);
    assert.equal(wbUi.isLink('//example.com'), false);
    assert.equal(wbUi.isLink(null), false);
    assert.equal(wbUi.esc(null), '');
});

check('a cell that is an https address can be clicked; nothing else becomes a link', () => {
    const scan = 'https://example.com/Shared%20Documents/A & B/ICTCOF1 - Nama.pdf';
    assert.equal(wbUi.cell(scan), '<a href="https://example.com/Shared%20Documents/A &amp; B/ICTCOF1 - Nama.pdf" target="_blank" rel="noopener noreferrer" '
        + 'title="https://example.com/Shared%20Documents/A &amp; B/ICTCOF1 - Nama.pdf">https://example.com/Shared%20Documents/A &amp; B/ICTCOF1 - Nama.pdf</a>');
    assert.equal(wbUi.cell('https://example.com/"onmouseover="alert(1)'), '<a href="https://example.com/&quot;onmouseover=&quot;alert(1)" target="_blank" rel="noopener noreferrer" '
        + 'title="https://example.com/&quot;onmouseover=&quot;alert(1)">https://example.com/&quot;onmouseover=&quot;alert(1)</a>');
    for (const other of ['javascript:alert(1)', 'http://example.com/', ' https://example.com/', 'lihat https://example.com/', '//example.com/x', 'file:///c:/x', '']) {
        assert.equal(wbUi.cell(other), `<span title="${wbUi.esc(other)}">${wbUi.esc(other)}</span>`, other);
    }
    assert.equal(wbUi.cell(null), '<span title=""></span>');

    wbBook.load();
    answer(requests.at(-1), reply(1, { data: [{ line: 4, cells: ['ICT/COF/3', scan] }] }));
    assert.match(body(), /<td class="wb-cell"><span title="ICT\/COF\/3">ICT\/COF\/3<\/span><\/td><td class="wb-cell"><a href="https:\/\/example\.com\//);
});

// ---------------------------------------------------------------- titles and tabs
check('the titles stay when a sheet is drawn again, and another sheet brings its own', () => {
    wbBook.load();
    answer(requests.at(-1), reply(0));
    assert.deepEqual(titles(), ['Baris', 'No', 'Nama', 'Tanggal']);
    wbBook.load();
    answer(requests.at(-1), reply(0));
    assert.deepEqual(titles(), ['Baris', 'No', 'Nama', 'Tanggal']);

    onPage(3);
    let prevented = false;
    node('(object)').attrs['data-sheet'] = '1';
    fire('#wbTabs', 'click a.wb-tab', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.deepEqual(sent(), { tab: 1, page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc' });
    answer(requests.at(-1), reply(1));
    assert.deepEqual(titles(), ['Baris', 'No. COF', 'Penerima']);
    assert.match(tabs(), /class="nav-link wb-tab " data-sheet="0">SRF/);
    assert.match(tabs(), /class="nav-link wb-tab active" aria-current="true" data-sheet="1">COF/);
    assert.match(body(), /<span title="Penerima 2">Penerima 2<\/span><\/td><\/tr>$/);
    assert.equal((body().split('</tr>')[0].match(/<td/g) || []).length, 3);
});

check('a click on a title sorts by that column, a second click the other way', () => {
    onPage(3);
    fire('#wbTable th[data-key="c1"]', 'click');
    assert.deepEqual([sent().tab, sent().sort, sent().dir, sent().page], [1, 'c1', 'asc', 1]);
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c1', dir: 'asc' } }));
    // The column sorted by is marked, its arrow pointing the way of the order; the others keep a grey arrow up.
    assert.match(node('#wbTable th[data-key="c1"]').html, /ti-chevron-up/);
    assert.equal(node('#wbTable th[data-key="c1"]').classes.has('is-sorted'), true);
    assert.equal(node('#wbTable th[data-key="c0"]').classes.has('is-sorted'), false);
    assert.match(node('#wbTable th[data-key="c0"]').html, /ti-chevron-up/);
    fire('#wbTable th[data-key="c1"]', 'click');
    assert.deepEqual([sent().sort, sent().dir], ['c1', 'desc']);
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c1', dir: 'desc' } }));
    assert.match(node('#wbTable th[data-key="c1"]').html, /ti-chevron-down/);
    fire('#wbTable th[data-key="row"]', 'click'); // "Baris": back to the order of the workbook, first row first
    assert.deepEqual([sent().sort, sent().dir], ['row', 'asc']);
    answer(requests.at(-1), reply(1, { sheet: { sort: 'row', dir: 'asc' } }));

    // Another sheet starts in its own order: a column of this one means nothing there.
    fire('#wbTable th[data-key="c1"]', 'click');
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c1', dir: 'asc' } }));
    tab(0);
    assert.deepEqual(sent(), { tab: 0, page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc' });
    answer(requests.at(-1), reply(0));
});

check('what the server made of a request is what the page goes on with', () => {
    Object.assign(wbBook, { sheet: 9, page: 7, sort: 'c40', dir: 'asc' });
    wbBook.load();
    assert.deepEqual([sent().tab, sent().page, sent().sort], [9, 7, 'c40']);
    answer(requests.at(-1), reply(2, { data: [] })); // the last sheet there is, its first page, in the order of the workbook
    assert.deepEqual([wbBook.sheet, wbBook.page, wbBook.sort, wbBook.dir], [2, 1, 'row', 'desc']);
    assert.match(tabs(), /class="nav-link wb-tab active" aria-current="true" data-sheet="2">Kosong <span class="badge bg-secondary-lt ms-1">0<\/span>/);
    assert.deepEqual(titles(), ['Baris'], 'a sheet without titles has only the row number');
    assert.match(body(), /Sheet ini kosong\./);
    assert.equal(node(' .pagination-container').hidden, true);
    assert.equal(node('#wbInfo').text.startsWith('0 baris di sheet "Kosong"'), true);
    tab(0);
    answer(requests.at(-1), reply(0));
});

// ---------------------------------------------------------------- searching
check('a pause after typing searches; the tabs then say where it was found', () => {
    onPage(3);
    const before = requests.length;
    node('#wbSearch').value = '  budi ';
    fire('#wbSearch', 'input');
    assert.equal(requests.length, before, 'nothing before the pause is over');
    runTimers();
    assert.equal(requests.length, before + 1);
    assert.deepEqual([sent().search, sent().page, sent().tab], ['budi', 1, 0]);
    answer(requests.at(-1), reply(0, { matches: [0, 4, 0], data: [] }));
    assert.match(tabs(), /data-sheet="0">SRF <span class="badge bg-secondary-lt ms-1">0<\/span>/);
    assert.match(tabs(), /data-sheet="1">COF <span class="badge bg-green-lt ms-1">4<\/span>/);
    assert.match(body(), /Tidak ada baris yang cocok dengan pencarian di sheet ini\./);
    assert.equal(node('#wbInfo').text.startsWith('0 baris cocok di sheet "SRF"'), true);

    // The other sheet is opened with what is searched for still in place.
    tab(1);
    assert.deepEqual([sent().tab, sent().search, sent().page], [1, 'budi', 1]);
    answer(requests.at(-1), reply(1, { matches: [0, 4, 0], total: 4 }));
    assert.equal(node('#wbInfo').text.startsWith('4 baris cocok di sheet "COF"'), true);
});

check('Enter searches at once and the pause then asks nothing; only the latest answer is shown', () => {
    const before = requests.length;
    node('#wbSearch').value = 'andi';
    fire('#wbSearch', 'input');
    fire('#wbSearch', 'keydown', { key: 'Enter' });
    assert.equal(requests.length, before + 1);
    runTimers();
    assert.equal(requests.length, before + 1, 'the pause after Enter sends nothing new');
    fire('#wbSearch', 'keydown', { key: 'a' });
    assert.equal(requests.length, before + 1);

    node('#wbSearch').value = 'andi wijaya';
    fire('#wbSearch', 'keydown', { key: 'Enter' });
    const [older, newer] = requests.slice(-2);
    answer(newer, reply(1, { data: [{ line: 9, cells: ['ICTCOF9', 'Andi Wijaya'] }] }));
    assert.equal(node('#wbReload').props.disabled, false);
    answer(older, reply(1, { data: [{ line: 8, cells: ['ICTCOF8', 'Andi'] }] }));
    assert.match(body(), /ICTCOF9/);
    assert.doesNotMatch(body(), /ICTCOF8/);
});

// ---------------------------------------------------------------- the table's own buttons
check('page links and the page size ask for that page; reset and "Muat ulang" for the first', () => {
    answer(requests.at(-1), reply(1, { total: 60 }));
    // The links are what the helper wrote: the page of a link is the one in its markup.
    assert.match(node(' .pagination-container').html, /<button type="button" class="rm-pager-page btn-pagination" data-page="3">3<\/button>/);
    node('(object)').data.page = 3;
    fire(' .pagination-container .btn-pagination, .btn-previous, .btn-next', 'click');
    assert.deepEqual([sent().page, sent().paginated, sent().tab, sent().search], [3, 25, 1, 'andi wijaya']);
    answer(requests.at(-1), { ...reply(1), meta: { ...meta(60), current_page: 3 } });
    assert.equal(wbBook.page, 3);
    node('(object)').data.limit = 50;
    fire(' .pagination-container .pagination-limit', 'click');
    assert.deepEqual([sent().page, sent().paginated], [1, 50]);
    answer(requests.at(-1), reply(1, { total: 60 }));

    fire('#wbTable th[data-key="c0"]', 'click');
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c0', dir: 'asc' }, total: 60 }));
    onPage(3);
    wbBook.reset(); // the search and the order go, the sheet stays
    assert.deepEqual(sent(), { tab: 1, page: 1, paginated: 50, search: '', sort: 'row', dir: 'desc' });
    assert.equal(node('#wbSearch').value, '');
    answer(requests.at(-1), reply(1));
    wbBook.load(true);
    assert.equal(sent().refresh, 1);
    answer(requests.at(-1), reply(1));
    wbBook.load();
    assert.equal('refresh' in sent(), false);
    answer(requests.at(-1), reply(1));
});

// ---------------------------------------------------------------- another sheet is on its way
check('while another sheet loads, the titles and page links of the one before do nothing, and the tab is marked at once', () => {
    const before = requests.length;
    // Sheet 1 is on screen, and its tab is the one that is marked; the tab of sheet 0 is clicked (asked for, not answered).
    const { element, siblings } = tab(0, { siblings: [{ classes: ['active'], attrs: { 'aria-current': 'true' } }, {}] });
    assert.equal(requests.length, before + 1);
    assert.deepEqual([wbBook.sheet, wbBook.shown], [0, 1]);
    // The tab that was clicked is marked at once, the others are not.
    assert.deepEqual([element.classes.has('active'), element.attrs['aria-current']], [true, 'true']);
    assert.deepEqual(siblings.map(other => [other.classes.has('active'), other.attrs['aria-current']]), [[false, undefined], [false, undefined]]);
    // What is still on screen belongs to sheet 1: its titles and its page links do nothing now.
    fire('#wbTable th[data-key="c0"]', 'click');
    node('(object)').data.page = 2;
    fire(' .pagination-container .btn-pagination, .btn-previous, .btn-next', 'click');
    assert.equal(requests.length, before + 1);
    // A failure leaves the sheet that was asked for as the one "Muat ulang" is about ...
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(wbBook.sheet, 0);
    wbBook.load(true);
    assert.equal(sent().tab, 0);
    // ... and once it has arrived, the titles and the page links are its own.
    answer(requests.at(-1), reply(0, { total: 60 }));
    assert.deepEqual([wbBook.sheet, wbBook.shown], [0, 0]);
    fire('#wbTable th[data-key="c1"]', 'click');
    assert.deepEqual([sent().tab, sent().sort], [0, 'c1']);
    answer(requests.at(-1), reply(0));
});

check('an answer that comes after a newer request was sent changes nothing, also when it comes first', () => {
    // The older answer first, as a server that answers one request at a time gives them: the newest request is still on its way.
    wbBook.load();
    answer(requests.at(-1), reply(0, { total: 60 }));
    const shownBefore = wbBook.shown;
    tab(1);
    const older = requests.at(-1);
    tab(2);
    const newer = requests.at(-1);
    const waiting = body();
    assert.match(waiting, /Loading/);
    assert.equal(node('#wbReload').props.disabled, true);
    answer(older, reply(1));
    assert.equal(node('#wbReload').props.disabled, true, 'the newest request is not answered yet');
    assert.deepEqual([wbBook.sheet, wbBook.shown, body()], [2, shownBefore, waiting], 'nothing of the older answer is taken over');
    answer(newer, reply(2, { data: [] }));
    assert.deepEqual([wbBook.sheet, wbBook.shown], [2, 2]);
    assert.equal(node('#wbReload').props.disabled, false);
    assert.match(tabs(), /class="nav-link wb-tab active" aria-current="true" data-sheet="2">Kosong/);

    // A failure of the older request is as little to be heard.
    tab(0);
    const failing = requests.at(-1);
    tab(1);
    const latest = requests.at(-1);
    refuse(failing, { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(node('#wbNotice').html, '');
    assert.equal(node('#wbReload').props.disabled, true);
    answer(latest, reply(1));
    assert.deepEqual([wbBook.sheet, wbBook.shown, node('#wbReload').props.disabled], [1, 1, false]);
    assert.equal(node('#wbNotice').html, '');
});

check('a tab that has the focus keeps it when the tabs are written again', () => {
    const document = page('document');
    const focused = [];
    // An element is there to be given the focus only once the tabs have been written: what was written before is gone.
    const writes = () => node('#wbTabs').writes;
    let written = null;
    document.activeElement = { matches: selector => selector === '#wbTabs a.wb-tab', getAttribute: name => (name === 'data-sheet' ? '1' : null) };
    document.querySelector = selector => (writes() > written ? { focus() { focused.push(selector); } } : null);
    written = writes();
    wbBook.tabs(reply(1).sheets);
    assert.deepEqual(focused, ['#wbTabs a.wb-tab[data-sheet="1"]']);
    // Something else has the focus: no tab is given it.
    document.activeElement = { matches: () => false, getAttribute: () => null };
    wbBook.tabs(reply(1).sheets);
    assert.equal(focused.length, 1);
    delete document.activeElement;
    delete document.querySelector;
});

// ---------------------------------------------------------------- failures and notices
check('a failed load says why in the reader\'s words and clears what no longer holds', () => {
    wbBook.load();
    assert.equal(node('#wbReload').props.disabled, true);
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.' } });
    assert.equal(node('#wbReload').props.disabled, false);
    assert.equal(body().includes('Isi workbook tidak bisa dimuat.'), true);
    assert.equal(node('#wbPage .pagination-container').hidden, true);
    assert.equal(node('#wbInfo').text, '');
    assert.match(node('#wbNotice').html, /alert-danger[^>]*>SharePoint sedang tidak bisa dihubungi/);

    assert.match(wbBook.failure({ status: 401 }), /^Sesi Anda sudah berakhir/);
    assert.match(wbBook.failure({ status: 403, responseJSON: { message: 'You are not allowed to perform this action.' } }), /^Role Anda tidak punya izin Read/);
    assert.equal(wbBook.failure({ status: 500, responseJSON: { message: 'SQLSTATE secret detail' } }), 'Isi workbook tidak bisa dimuat. Coba lagi beberapa saat lagi.');
    assert.equal(wbBook.failure({ status: 0 }), 'Isi workbook tidak bisa dimuat. Coba lagi beberapa saat lagi.');
    for (const status of [404, 413, 415, 502, 503, 504]) {
        assert.equal(wbBook.failure({ status, responseJSON: { message: 'Kata server.' } }), 'Kata server.', `status ${status}`);
    }
    assert.equal(wbBook.failure({ status: 404, responseJSON: { message: '<b>x</b>' } }), '<b>x</b>'); // escaped where it is shown:
    wbBook.notice([{ kind: 'danger', text: '<b>x</b>' }]);
    assert.match(node('#wbNotice').html, /&lt;b&gt;x&lt;\/b&gt;/);

    // The answer to a request that was overtaken is not shown, a failure neither.
    wbBook.load();
    wbBook.load();
    const [older, newer] = requests.slice(-2);
    answer(newer, reply(1));
    refuse(older, { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(node('#wbNotice').html, '');
    assert.match(body(), /No\. COF 3/);
});

check('an old copy or a sheet that was cut off says so', () => {
    wbBook.describe(reply(1, { book: { stale: true, modified: null }, sheet: { truncated: true } }));
    assert.equal(node('#wbInfo').text, '2 baris di sheet "COF" · data SharePoint per 07-10-2026 10:00');
    assert.match(node('#wbNotice').html, /alert-warning[^>]*>SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab\. Yang tampil adalah isi terakhir yang berhasil diambil \(07-10-2026 10:00\)\./);
    assert.match(node('#wbNotice').html, /alert-info[^>]*>Sheet ini sangat besar: hanya sebagian yang ditampilkan\. Buka di Excel untuk melihat semuanya\.<\/div>/);
    // Without an address of the workbook there is no button to be sent to.
    wbBook.describe(reply(1, { book: { web_url: null }, sheet: { truncated: true } }));
    assert.match(node('#wbNotice').html, /alert-info[^>]*>Sheet ini sangat besar: hanya sebagian yang ditampilkan\.<\/div>/);
    assert.doesNotMatch(node('#wbNotice').html, /Buka di Excel/);
    wbBook.describe(reply(1));
    assert.equal(node('#wbNotice').html, '');
});

// ---------------------------------------------------------------- the sheet that is checked against a folder
/**
 * What the answer says about a sheet that is checked against a folder of scans; the cards count the whole sheet. (The counts are all
 * different, so that a card that shows the count of another is noticed.)
 */
const scanned = (status = null, extra = {}) => ({
    column: 2, counts: { total: 10, ok: 4, missing: 3, differs: 2, below: 1 }, status, fetched_at: '07-10-2026 10:15', stale: false, folder: 'COF Scan', open: '/documents/cof-scan', ...extra,
});
const scanRows = [
    { line: 7, cells: ['ICT/COF/6', 'Rangkap', 'Belum ada'], scan: { status: 'missing', code: '' } },
    { line: 6, cells: ['ICT/COF/5', '', 'Subfolder'], scan: { status: 'below', code: 'ICTCOF5' } },
    { line: 5, cells: ['ICT/COF/4', '', 'Ada'], scan: { status: 'ok', code: 'ICTCOF4' } },
    { line: 3, cells: ['ICT/COF/2', '', 'Nama beda'], scan: { status: 'differs', code: 'ICTCOF2' } },
];
const scanReply = (scan, data = scanRows, sheet = {}) => reply(1, { sheet: { columns: ['COF No', 'Catatan', 'File scan'], scan, ...sheet }, data });
const card = status => {
    node('(object)').attrs['data-status'] = status;

    return fire('#wbCards', 'click button.wb-card');
};
/** The cards on screen, as they are drawn: the status each asks for, its words, its count and colour, whether it is on and whether it is off. */
const cardsOnScreen = () => [...node('#wbCards').html.matchAll(
    /<button type="button" class="card card-sm w-100 wb-card ([^"]*)" data-status="([^"]*)" aria-pressed="(true|false)"( disabled)?>.*?<span class="badge bg-(\w+) me-2">&nbsp;<\/span><span class="wb-card-label">([^<]*)<\/span><\/span><span class="wb-card-count d-block">([^<]*)<\/span>/g
)].map(m => ({ status: m[2], label: m[6], count: m[7], tone: m[5], on: m[3] === 'true', active: m[1].includes('active'), off: m[4] !== undefined }));

check('a sheet that is checked against a folder has its cards and a badge in the last column', () => {
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned()));
    assert.deepEqual(titles(), ['Baris', 'COF No', 'Catatan', 'File scan']);
    // A sheet that is checked and could be: nothing to say about it, and the search is told what it does not look in.
    assert.equal(node('#wbNotice').html, '');
    assert.equal(node('#wbSearchLabel').text, 'Cari di semua kolom kecuali File scan');
    assertWellFormed(node('#wbCards').html, 'the cards');
    assertWellFormed(body(), 'the rows of a checked sheet');

    // Five cards, each a button that says which it is, in this order: the sheet's rows, those with their file, without it, under another name, below a folder.
    assert.deepEqual(
        cardsOnScreen(),
        [
            { status: '', label: 'Total baris', count: '10', tone: 'blue', on: true, active: true, off: false },
            { status: 'ok', label: 'Ada', count: '4', tone: 'green', on: false, active: false, off: false },
            { status: 'missing', label: 'Belum ada', count: '3', tone: 'red', on: false, active: false, off: false },
            { status: 'differs', label: 'Nama beda', count: '2', tone: 'orange', on: false, active: false, off: false },
            { status: 'below', label: 'Subfolder', count: '1', tone: 'purple', on: false, active: false, off: false },
        ]
    );
    assert.match(node('#wbCards').html, /<div class="col-6 col-md"><button/, 'two cards to a row on a narrow screen, all of them in a row on a wide one');
    assert.equal(node('#wbCardsWrap').classes.has('d-none'), false);
    assert.equal(node('#wbCardsInfo').text, 'Status dicek terhadap folder COF Scan (data folder per 07-10-2026 10:15).');
    assert.match(html, /id="wbCards" role="group" aria-label="Ringkasan kolom File scan"/);
    assert.match(html, /huruf besar atau kecil tidak dibedakan[\s\S]*pencarian tidak mencari di kolom ini/, 'the legend under the cards');
    assert.match(html, /<b>Subfolder<\/b>: link menunjuk ke subfolder[\s\S]*Klik sebuah kartu untuk hanya melihat barisnya/);

    // The badges: a colour and words of the page's own, what it means in the tooltip; a way to the file where there is one, told in the tooltip.
    const out = body();
    assert.match(out, /<td class="wb-cell"><span class="badge bg-red-lt" title="Tidak ada file dengan nama atau nomor ini di folder">Belum ada<\/span><\/td>/);
    // (The tooltip is on the badge; the name of the link, for a screen reader, says where it goes.)
    assert.match(out, /<td class="wb-cell"><a href="\/documents\/cof-scan\?code=ICTCOF5" class="text-decoration-none" aria-label="Subfolder: lihat file di COF Scan"><span class="badge bg-purple-lt" title="Link menunjuk ke subfolder: biasanya karena nama pemohon mengandung tanda &quot;\/&quot;\. Klik untuk melihat file ini di COF Scan">Subfolder<\/span><\/a><\/td>/);
    assert.match(out, /<td class="wb-cell"><a href="\/documents\/cof-scan\?code=ICTCOF4" class="text-decoration-none" aria-label="Ada: lihat file di COF Scan"><span class="badge bg-green-lt" title="Ada file dengan nama yang sama seperti di link \(huruf besar atau kecil tidak dibedakan\)\. Klik untuk melihat file ini di COF Scan">Ada<\/span><\/a><\/td>/);
    assert.match(out, /<a href="\/documents\/cof-scan\?code=ICTCOF2" class="text-decoration-none" aria-label="Nama beda: lihat file di COF Scan"><span class="badge bg-orange-lt" title="Ada file dengan nomor ini, tetapi link di Excel kosong atau menyebut nama lain \(spasi lebih atau kurang, misalnya\)\. Klik untuk melihat file ini di COF Scan">Nama beda<\/span><\/a>/);
    assert.equal((out.match(/<a href="\/documents\/cof-scan/g) || []).length, 3, 'a way to the file wherever a file is there, even under another name; not for a row without one');
    assert.doesNotMatch(out, /<a [^>]*title=/, 'the link has no tooltip of its own: the badge would cover it');
    // The other cells are as ever, and the row has a cell for each title.
    assert.match(out, /<span title="Rangkap">Rangkap<\/span>/);
    assert.equal((out.split('</tr>')[0].match(/<td/g) || []).length, titles().length);
});

check('a card shows the rows of its kind, a second click on it all of them, and "Total" all', () => {
    card('missing');
    assert.deepEqual([sent().status, sent().page, sent().tab], ['missing', 1, 1]);
    answer(requests.at(-1), scanReply(scanned('missing'), [scanRows[0]]));
    assert.deepEqual(cardsOnScreen().map(c => [c.status, c.on, c.active]), [['', false, false], ['ok', false, false], ['missing', true, true], ['differs', false, false], ['below', false, false]]);
    assert.equal(wbBook.status, 'missing');
    // The counts are those of the sheet, whatever the cards show.
    assert.deepEqual(cardsOnScreen().map(c => c.count), ['10', '4', '3', '2', '1']);
    // The line about the sheet says that what it counts is the rows that were asked for, not all of them.
    assert.equal(node('#wbInfo').text, '1 baris cocok di sheet "COF" · workbook terakhir diubah 06-10-2026 09:00 · data SharePoint per 07-10-2026 10:00');

    // The same card again: all rows.
    card('missing');
    assert.equal('status' in sent(), false);
    answer(requests.at(-1), scanReply(scanned()));
    assert.equal(wbBook.status, '');

    // Another card, then "Total": all rows.
    card('ok');
    assert.equal(sent().status, 'ok');
    answer(requests.at(-1), scanReply(scanned('ok'), [scanRows[2]]));
    card('');
    assert.equal('status' in sent(), false);
    answer(requests.at(-1), scanReply(scanned()));
    card('');
    assert.equal('status' in sent(), false, 'the total card is no filter to turn on');
    answer(requests.at(-1), scanReply(scanned()));

    // What was asked on page 3 starts again at the first page; a search stays.
    wbBook.search = 'budi';
    onPage(3);
    card('differs');
    assert.deepEqual([sent().status, sent().page, sent().search], ['differs', 1, 'budi']);
    answer(requests.at(-1), scanReply(scanned('differs'), [scanRows[3]]));

    // Another sheet and "reset" start with all rows; the server's word on what it showed is the one the page goes on with.
    tab(0);
    assert.equal('status' in sent(), false);
    answer(requests.at(-1), reply(0));
    wbBook.open(1);
    card('below');
    answer(requests.at(-1), scanReply(scanned('below'), [scanRows[1]]));
    wbBook.reset();
    assert.equal('status' in sent(), false);
    answer(requests.at(-1), scanReply(scanned()));
    wbBook.status = 'ok';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(null)));
    assert.equal(wbBook.status, '', 'a status that the server did not apply is dropped');
    wbBook.search = '';
});

check('a card with no rows behind it is off, unless it is the one that is on; a card that shows nothing says which', () => {
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned(null, { counts: { total: 10, ok: 7, missing: 0, differs: 3, below: 0 } })));
    assert.deepEqual(cardsOnScreen().map(c => [c.status, c.off]), [['', false], ['ok', false], ['missing', true], ['differs', false], ['below', true]]);
    assert.match(node('#wbCards').html, /data-status="missing" aria-pressed="false" disabled>/);

    // The one that is on stays on offer, so that it can be turned off again, even when its rows are gone (they were fixed in Excel).
    wbBook.status = 'missing';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned('missing', { counts: { total: 10, ok: 7, missing: 0, differs: 3, below: 0 } }), []));
    assert.deepEqual(cardsOnScreen().map(c => [c.status, c.off]), [['', false], ['ok', false], ['missing', false], ['differs', false], ['below', true]]);
    // The table says why it is empty, in the words of the card.
    assert.match(body(), /Tidak ada baris dengan status "Belum ada" di sheet ini\./);
    assert.doesNotMatch(body(), /Sheet ini kosong/);
    wbBook.search = 'budi';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned('missing', { counts: { total: 10, ok: 7, missing: 0, differs: 3, below: 0 } }), []));
    assert.match(body(), /Tidak ada baris dengan status "Belum ada" yang cocok dengan pencarian di sheet ini\./);
    // Only a search: as before. Nothing at all: the sheet is empty.
    wbBook.status = '';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(), []));
    assert.match(body(), /Tidak ada baris yang cocok dengan pencarian di sheet ini\./);
    wbBook.search = '';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(), []));
    assert.match(body(), /Sheet ini kosong\./);
    // A state that the page has no words for is said as it is, as text.
    wbBook.status = '<b>x</b>';
    assert.match(wbBook.empty(), /status "&lt;b&gt;x&lt;\/b&gt;"/);
    assert.doesNotMatch(wbBook.empty(), /<b>/);
    wbBook.status = '';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned()));
});

check('a card that has the focus keeps it when the cards are written again', () => {
    const document = page('document');
    const focused = [];
    // An element is there to be given the focus only once the cards have been written: what was written before is gone.
    const writes = () => node('#wbCards').writes;
    let written = null;
    document.activeElement = { matches: selector => selector === '#wbCards button.wb-card', getAttribute: name => (name === 'data-status' ? 'missing' : null) };
    document.querySelector = selector => (writes() > written ? { focus() { focused.push(selector); } } : null);
    written = writes();
    wbBook.cards(scanned());
    assert.deepEqual(focused, ['#wbCards button.wb-card[data-status="missing"]']);
    // The Total card has an empty status: it is found by that.
    document.activeElement = { matches: () => true, getAttribute: () => '' };
    written = writes();
    wbBook.cards(scanned());
    assert.deepEqual(focused.at(-1), '#wbCards button.wb-card[data-status=""]');
    // Something else has the focus (that has a data-status of its own, too): no card is given it.
    const before = focused.length;
    document.activeElement = { matches: () => false, getAttribute: () => 'missing' };
    wbBook.cards(scanned());
    assert.equal(focused.length, before);
    // A card that is off cannot take it.
    document.activeElement = { matches: () => true, getAttribute: () => 'ok' };
    document.querySelector = selector => (writes() > written ? { disabled: true, focus() { focused.push('off'); } } : null);
    written = writes();
    wbBook.cards(scanned());
    assert.equal(focused.includes('off'), false);
    // ...and the focus does not fall to the page: the Total card takes it (when the last row of a kind was fixed in Excel while its card was open).
    const total = focused.length;
    document.activeElement = { matches: () => true, getAttribute: () => 'missing' };
    document.querySelector = selector => (writes() > written ? (selector.endsWith('[data-status="missing"]') ? { disabled: true, focus() { focused.push('off'); } } : { focus() { focused.push(selector); } }) : null);
    written = writes();
    wbBook.cards(scanned(null, { counts: { total: 10, ok: 7, missing: 0, differs: 3, below: 0 } }));
    assert.deepEqual(focused.slice(total), ['#wbCards button.wb-card[data-status=""]']);
    delete document.activeElement;
    delete document.querySelector;
});

check('the link that gives the rows up goes with its bar, and the focus goes to the search box then', () => {
    const document = page('document');
    const focusTo = () => node('#wbSearch').triggered.filter(event => event === 'focus').length;
    const before = focusTo();
    document.activeElement = { id: 'wbLineClear' };
    wbBook.lineBar([2, 9]);
    assert.equal(focusTo(), before, 'the link stays while there are rows to give up');
    assert.equal(node('#wbLineBar').classes.has('d-none'), false);
    wbBook.lineBar([]);
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    assert.equal(focusTo(), before + 1);
    document.activeElement = { id: 'wbSearch' };
    wbBook.lineBar([]);
    assert.equal(focusTo(), before + 1, 'something else has the focus: it stays there');
    delete document.activeElement;
    wbBook.lineBar([]);
    assert.equal(focusTo(), before + 1, 'nothing has it');
});

check('a card is not listened to while another sheet is loading', () => {
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned()));
    tab(0);
    const waiting = requests.length;
    card('missing');
    assert.equal(requests.length, waiting, 'the cards on screen are those of the sheet that is being left');
    answer(requests.at(-1), reply(0));
});

check('a sheet that is not checked has no cards, and neither has one that could not be', () => {
    // The sheet before, and the cards are gone with it.
    tab(0);
    answer(requests.at(-1), reply(0));
    assert.equal(node('#wbCardsWrap').classes.has('d-none'), true);
    assert.equal(node('#wbCards').html, '');
    assert.equal(wbBook.scan, null);
    assert.equal(node('#wbSearchLabel').text, 'Cari di semua kolom', 'the search looks in every column of a sheet that is not checked');
    assert.deepEqual(titles(), ['Baris', 'No', 'Nama', 'Tanggal']);

    // The answer says why there is no check: no cards, no column, the reason in words (as text).
    wbBook.open(1);
    answer(requests.at(-1), reply(1, { sheet: { scan: { unavailable: 'Kolom File scan belum bisa ditampilkan. <b>x</b>' } } }));
    assert.equal(node('#wbCardsWrap').classes.has('d-none'), true);
    assert.deepEqual(titles(), ['Baris', 'No. COF', 'Penerima']);
    assert.match(node('#wbNotice').html, /alert-info[^>]*>Kolom File scan belum bisa ditampilkan\. &lt;b&gt;x&lt;\/b&gt;/);
    assert.doesNotMatch(body(), /badge bg-/);
    // (A check that has no counts is no check either.)
    wbBook.load();
    answer(requests.at(-1), reply(1, { sheet: { scan: { column: 2, status: null } } }));
    assert.equal(node('#wbCardsWrap').classes.has('d-none'), true);
    assert.equal(wbBook.scan, null);

    // The status that was clicked on a sheet that has no check is nothing the other sheet knows: the page goes on without it.
    wbBook.status = 'ok';
    wbBook.load();
    answer(requests.at(-1), reply(0));
    assert.equal(wbBook.status, '');

    // A failed load takes the cards away as well.
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned()));
    assert.equal(node('#wbCardsWrap').classes.has('d-none'), false);
    wbBook.load();
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(node('#wbCardsWrap').classes.has('d-none'), true);
});

check('an old listing of the folder says so; what comes from the answer is text and only a path of the application is followed', () => {
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned(null, { stale: true, folder: '<i>COF</i>', fetched_at: '<u>tadi</u>' })));
    assert.match(node('#wbNotice').html, /alert-warning[^>]*>SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab\. Kolom File scan dicek terhadap daftar terakhir folder &lt;i&gt;COF&lt;\/i&gt; yang berhasil diambil \(&lt;u&gt;tadi&lt;\/u&gt;\)\./);
    assert.equal(node('#wbCardsInfo').text, 'Status dicek terhadap folder <i>COF</i> (data folder per <u>tadi</u>).');
    assert.match(body(), /Klik untuk melihat file ini di &lt;i&gt;COF&lt;\/i&gt;"/);
    assert.doesNotMatch(node('#wbCards').html + body(), /<i>|<u>/);

    // What the address of the other page is: only a path of this application, whatever else it looks like.
    for (const open of ['javascript:alert(1)', '//evil.example/x', 'https://evil.example/x', '/\\evil.example/x', '/\t/evil.example/x', '/\n/evil.example/x', '/ x', '', null]) {
        wbBook.load();
        answer(requests.at(-1), scanReply(scanned(null, { open })));
        assert.doesNotMatch(body(), /<a href=/, JSON.stringify(open));
        assert.match(body(), /badge bg-green-lt/, 'the badge is still there');
    }
    for (const [url, is] of [['/documents/cof-scan', true], ['/', true], ['//a', false], ['/\\a', false], ['/a\\b', false], ['/\ta', false], ['/ a', false], ['a/b', false], ['https://a/b', false], ['', false], [null, false], [5, false]]) {
        assert.equal(wbUi.isPath(url), is, JSON.stringify(url));
    }
    // The words of a state are the page's own, for a state it knows and for no other (a property of every object is none).
    assert.equal(wbUi.words('missing'), 'Belum ada');
    assert.equal(wbUi.words('below'), 'Subfolder');
    assert.equal(wbUi.words('constructor'), '');
    assert.equal(wbUi.words('toString'), '');
    assert.equal(wbUi.words(''), '');

    // A path with marks in it that the value of an attribute does not take is still written as text.
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(null, { open: '/x"onmouseover="alert(1)' })));
    assert.doesNotMatch(body(), /"onmouseover=/);
    assert.match(body(), /<a href="\/x&quot;onmouseover=&quot;alert\(1\)\?code=ICTCOF4" class="text-decoration-none" aria-label="Ada: lihat file di COF Scan">/);

    // The words of a badge are text too, whatever they are.
    const words = wbUi.badges.ok[1];
    wbUi.badges.ok[1] = '<u>Ada</u>';
    try {
        wbBook.load();
        answer(requests.at(-1), scanReply(scanned(), [scanRows[2]]));
        assert.match(body(), /&lt;u&gt;Ada&lt;\/u&gt;/);
        assert.doesNotMatch(body(), /<u>/);
    } finally {
        wbUi.badges.ok[1] = words;
    }

    // A code that carries characters of a query is written as one value; an unknown state is an ordinary cell.
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(), [
        { line: 9, cells: ['x', 'y', '<b>Belum</b>'], scan: { status: 'hax" onmouseover="alert(1)', code: 'ICTCOF9' } },
        { line: 8, cells: ['x', 'y', 'Ada'], scan: { status: 'ok', code: 'A&B "C"' } },
        { line: 7, cells: ['x', 'y', 'Ada'], scan: { status: 'constructor', code: 'ICTCOF7' } },
        { line: 6, cells: ['x', 'y', 'Ada'], scan: { status: 'ok', code: '' } },
        { line: 5, cells: ['x', 'y', 'Ada'] },
        { line: 4, cells: ['x', 'y', 'Belum ada'], scan: { status: 'missing', code: 'ICTCOF4' } },
    ]));
    const out = body();
    assert.match(out, /<span title="&lt;b&gt;Belum&lt;\/b&gt;">&lt;b&gt;Belum&lt;\/b&gt;<\/span>/);
    assert.match(out, /<a href="\/documents\/cof-scan\?code=A%26B\+%22C%22"/);
    assert.doesNotMatch(out, /onmouseover="alert/);
    assert.doesNotMatch(out, /constructor/, 'a property of every object is no state');
    // No number to go to, no way to the file; a row without any word about it is an ordinary cell; a file that is not there is not gone to.
    assert.equal((out.match(/<a href="\/documents\/cof-scan/g) || []).length, 1);
    assert.equal((out.match(/badge bg-green-lt/g) || []).length, 2, 'the two rows with a state that is known have its badge');
    assert.equal((out.match(/<span title="Ada">Ada<\/span>/g) || []).length, 2, 'the row of a state that is not known and the one without any are ordinary cells');
    assert.match(out, /<span class="badge bg-red-lt" title="Tidak ada file dengan nama atau nomor ini di folder">Belum ada<\/span>/);

    // Numbers are numbers: whatever else arrives is nothing.
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(null, { counts: { total: '<b>9</b>', ok: 1, missing: undefined, differs: null, below: 'x' } })));
    assert.deepEqual(cardsOnScreen().map(c => c.count), ['0', '1', '0', '0', '0']);
    assert.doesNotMatch(node('#wbCards').html, /<b>|NaN/);
    wbBook.load();
    answer(requests.at(-1), reply(0));
});

check('some rows of Excel can be asked for, are said, and can be given up', () => {
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned()));
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);

    // The answer says which rows it was limited to: they are said, and kept for the next request.
    wbBook.line = '2,9';
    wbBook.load();
    assert.equal(sent().line, '2,9');
    answer(requests.at(-1), scanReply(scanned(), [scanRows[3]], { lines: [2, 9] }));
    assert.equal(node('#wbLineBar').classes.has('d-none'), false);
    assert.equal(node('#wbLineText').text, 'Hanya baris Excel 2, 9.');
    assert.equal(wbBook.line, '2,9');
    assert.equal(node('#wbInfo').text, '1 baris cocok di sheet "COF" · workbook terakhir diubah 06-10-2026 09:00 · data SharePoint per 07-10-2026 10:00');
    assert.match(html, /id="wbLineClear"[^>]*>Tampilkan semua baris<\/a>/);

    // Nothing is left of them: the table says which rows.
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(), [], { lines: [5] }));
    assert.match(body(), /Baris Excel 5 tidak ada di sheet ini, atau tidak cocok dengan pencarian atau kartu yang dipilih\./);
    assert.doesNotMatch(body(), /Sheet ini kosong/);

    // The table says it without markup, whatever the rows are.
    wbBook.line = '<b>1</b>';
    assert.doesNotMatch(wbBook.empty(), /<b>/);
    assert.match(wbBook.empty(), /Baris Excel &lt;b&gt;1&lt;\/b&gt; tidak ada/);

    // Giving them up (from the page of the folder they were asked for, on the third page): the next request has none, and starts at the first
    // page; the link that is clicked is not followed.
    let prevented = 0;
    wbBook.line = '2,9';
    wbBook.page = 3;
    wbBook.lineBar([2, 9]);
    assert.equal(node('#wbLineBar').classes.has('d-none'), false);
    fire('#wbLineClear', 'click', { preventDefault() { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal('line' in sent(), false);
    assert.equal(sent().page, 1);
    assert.equal(wbBook.line, '');
    // The bar goes at once, not when the answer comes: the answer may be a failure, and the bar would go on saying what is no longer so.
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    assert.equal(node('#wbLineText').text, '');
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned()));
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    assert.equal(node('#wbLineText').text, '');

    // The same for another sheet and for "reset": the bar is gone before the answer, which may be a failure.
    wbBook.line = '2,9';
    wbBook.lineBar([2, 9]);
    tab(0);
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    wbBook.open(1);
    answer(requests.at(-1), scanReply(scanned()));
    wbBook.line = '2,9';
    wbBook.lineBar([2, 9]);
    wbBook.reset();
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    refuse(requests.at(-1), { status: 503, responseJSON: { message: 'Terlambat.' } });
    assert.equal(node('#wbLineBar').classes.has('d-none'), true);
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned()));

    // What the server did not apply is not kept; rows that are no rows are not said; another sheet and "reset" ask for all rows.
    wbBook.line = '4';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned()));
    assert.equal(wbBook.line, '');
    wbBook.line = '4';
    wbBook.load();
    answer(requests.at(-1), scanReply(scanned(), scanRows, { lines: ['x', 0, -1, 2.5, 7] }));
    assert.equal(node('#wbLineText').text, 'Hanya baris Excel 7.');
    assert.equal(wbBook.line, '7');
    tab(0);
    assert.equal('line' in sent(), false);
    answer(requests.at(-1), reply(0));
    wbBook.line = '3';
    wbBook.reset();
    assert.equal('line' in sent(), false);
    answer(requests.at(-1), reply(0));
    assert.equal(wbBook.line, '');
});

check('a link from the page of the folder opens the sheet by its name with the number searched for', () => {
    const linked = loadPage(
        process.argv[2],
        ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'],
        { href: 'http://localhost/documents/ict-masterlist?sheet=%20COF%20&search=%20ICTCOF123%20' }
    );
    assert.deepEqual(linked.sent(), { sheet: 'COF', page: 1, paginated: 25, search: 'ICTCOF123', sort: 'row', dir: 'desc' });
    assert.equal(linked.node('#wbSearch').value, 'ICTCOF123');
    // Said once: the answer says which sheet it was, and the page goes on with its number.
    linked.answer(linked.requests[0], reply(1));
    const book = linked.page('wbBook');
    assert.deepEqual([book.sheet, book.sheetName], [1, '']);
    book.load();
    assert.deepEqual([linked.sent().tab, linked.sent().search], [1, 'ICTCOF123']);
    assert.equal('sheet' in linked.sent(), false, 'by its position from then on: a sheet that is called "1" is not the second');

    // The name is cut as the search is, and a link without them asks for the first sheet.
    const long = loadPage(process.argv[2], ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'], { href: `http://localhost/documents/ict-masterlist?sheet=${'s'.repeat(300)}&search=${'x'.repeat(300)}` });
    assert.deepEqual([long.sent().sheet.length, long.sent().search.length], [100, 100]);
    assert.equal('tab' in long.sent(), false);
    const none = loadPage(process.argv[2], ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'], { href: 'http://localhost/documents/ict-masterlist?sheet=&search=' });
    assert.deepEqual([none.sent().tab, none.sent().search], [0, '']);
    assert.equal('sheet' in none.sent(), false);

    // The cut is between characters, never in the middle of one that is written with two halves (a request cannot carry half of one).
    const astral = loadPage(process.argv[2], ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'], {
        href: `http://localhost/documents/ict-masterlist?sheet=${encodeURIComponent('s'.repeat(99) + '😀' + 'b')}&search=${encodeURIComponent('x'.repeat(99) + '😀' + 'b')}`,
    });
    assert.equal(astral.sent().sheet, 's'.repeat(99) + '😀');
    assert.equal(astral.sent().search, 'x'.repeat(99) + '😀');
    assert.equal(isWellFormed(astral.sent().sheet) && isWellFormed(astral.sent().search), true);
});

check('a link from the page of the folder asks for rows of Excel, as many as 20 and nothing but numbers', () => {
    const open = query => loadPage(process.argv[2], ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'], { href: `http://localhost/documents/ict-masterlist?${query}` });
    const asked = open('sheet=COF&line=2,9');
    assert.deepEqual(asked.sent(), { sheet: 'COF', page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc', line: '2,9' });
    assert.equal(asked.page('wbBook').line, '2,9');
    assert.equal(open('line=%202%20').sent().line, '2', 'the blanks around it are not part of it');

    const twenty = Array.from({ length: 20 }, (_, at) => at + 1).join(',');
    assert.equal(open(`line=${twenty}`).sent().line, twenty);
    for (const odd of [`${twenty},21`, '2;3', '2,,3', ',2', '2,', 'x', '0x2', '1.5', '-2', '1234567890', '2 3', '']) {
        assert.equal('line' in open(`line=${encodeURIComponent(odd)}`).sent(), false, JSON.stringify(odd));
    }
});

// ---------------------------------------------------------------- asking for a change of a cell
const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const wbUi = {'];
const fresh = () => loadPage(process.argv[2], markers, { href: 'http://localhost/documents/ict-masterlist' });
// The page of a reader who may ask for a change of the first three columns: a row with a formula in the third, one with a request that waits.
const editable = ({ data, sheet = {} } = {}) => {
    const p = fresh();
    p.answer(p.requests[0], reply(0, {
        sheet: { editable_columns: 3, ...sheet },
        data: data ?? [
            { line: 3, cells: ['No 3', 'Nama 3', 'Tanggal 3'], locked: [2] },
            { line: 2, cells: ['No 2', 'Nama 2', 'Tanggal 2'], waiting: { 1: '<b>Baru</b>' } },
        ],
    }));

    return p;
};
// Clicks the pencil of a cell, as the browser does: the element carries the row and the column.
const click = (p, line, col) => {
    p.node('(object)').attrs['data-line'] = String(line);
    p.node('(object)').attrs['data-col'] = String(col);

    return p.fire('#wbTable', 'click button.wb-edit', {});
};

check('a reader who may not ask for a change sees no pencil, and the page says it only shows', () => {
    const p = fresh();
    p.answer(p.requests[0], reply(0));
    assert.doesNotMatch(p.node('#wbTable tbody').html, /wb-edit|ti-lock|menunggu/);
    assert.equal(p.node('#wbEditHint').text, 'Halaman ini hanya menampilkan; mengubah data tetap dilakukan di Excel.');
    assert.equal(p.page('wbBook').editable, 0);
});

check('a reader who may ask gets a pencil on a cell, a lock on a formula and a mark on a cell that waits', () => {
    const p = editable();
    const rows = p.node('#wbTable tbody').html;
    assertWellFormed(rows, 'the rows with pencils');
    // The first row: a pencil on the first two cells (what row and column it is for is in the button), a lock where the cell has a formula.
    assert.match(rows, /<button type="button" class="btn btn-link btn-sm p-0 me-1 wb-edit" data-line="3" data-col="0" title="Ajukan perubahan sel ini" aria-label="Ajukan perubahan sel ini"><i class="ti ti-pencil"><\/i><\/button><span title="No 3">No 3<\/span>/);
    assert.match(rows, /data-line="3" data-col="1"/);
    assert.doesNotMatch(rows, /data-line="3" data-col="2"/);
    assert.match(rows, /<i class="ti ti-lock text-muted me-1" title="Sel ini berisi rumus: ubah di Excel" aria-label="Berisi rumus"><\/i><span title="Tanggal 3">/);
    // The second: the cell that waits for a decision shows that instead of a pencil, with what was asked for as text.
    assert.match(rows, /<span class="badge bg-blue-lt me-1" title="Menunggu persetujuan: &lt;b&gt;Baru&lt;\/b&gt;">menunggu<\/span><span title="Nama 2">/);
    assert.doesNotMatch(rows, /data-line="2" data-col="1"/);
    assert.match(rows, /data-line="2" data-col="0"/);
    assert.equal(rows.includes('<b>Baru</b>'), false);
    assert.match(p.node('#wbEditHint').text, /pensil.*disetujui/);
    assert.equal(p.page('wbBook').editable, 3);
});

check('a column that is no cell of the workbook, the check against the folder, has no pencil', () => {
    const p = fresh();
    const scan = { column: 3, counts: { total: 1, ok: 1, missing: 0, differs: 0, below: 0 }, status: null, fetched_at: '07-10-2026 10:00', stale: false, folder: 'COF Scan', open: '/documents/cof-scan' };
    p.answer(p.requests[0], reply(0, {
        sheet: { columns: ['No', 'Nama', 'Tanggal', 'File scan'], editable_columns: 3, scan },
        data: [{ line: 2, cells: ['No 2', 'Nama 2', 'Tanggal 2', 'Ada'], scan: { status: 'ok', code: 'ICTCOF1' } }],
    }));
    const rows = p.node('#wbTable tbody').html;
    assert.match(rows, /data-line="2" data-col="2"/);
    assert.doesNotMatch(rows, /data-col="3"/);
    assert.match(rows, /badge bg-green-lt/);
});

check('the pencil opens the question with what the cell holds, and only for a row that is on screen', () => {
    const p = editable();
    click(p, 3, 1);
    assert.equal(p.node('#wbEditModal').modal, 'show');
    assert.equal(p.node('#wbEditOld').value, 'Nama 3');
    assert.equal(p.node('#wbEditValue').value, 'Nama 3');
    assert.equal(p.node('#wbEditWhere').text, 'Kolom "Nama", baris Excel 3');
    assert.equal(p.node('#wbEditError').classes.has('d-none'), true);

    // A row that is not on the page (the table was drawn again), a column that is not a cell, a click while another sheet loads: nothing.
    for (const [line, col] of [[99, 1], [3, 3]]) {
        const other = editable();
        click(other, line, col);
        assert.equal(other.node('#wbEditModal').modal, null, `${line}/${col}`);
    }
    const loading = editable();
    loading.page('wbBook').sheet = 1;
    click(loading, 3, 1);
    assert.equal(loading.node('#wbEditModal').modal, null);
});

check('asking sends the sheet on screen, the row, the column and what was typed; the button is off while it waits', () => {
    const p = editable();
    const before = p.requests.length;
    click(p, 3, 1);
    p.node('#wbEditValue').value = 'Nama Baru';
    p.page('wbEdit').submit();

    assert.equal(p.requests.length, before + 1);
    const request = p.requests.at(-1);
    assert.match(request.url, /\/documents\/ict-masterlist\/edit$/);
    assert.equal(request.type, 'POST');
    // (A form field the framework reads as the method: the route is a PATCH.)
    assert.deepEqual(p.sent(), { _method: 'PATCH', sheet: 0, line: 3, column: 1, value: 'Nama Baru' });
    assert.equal(p.node('#wbEditSend').props.disabled, true);
    p.page('wbEdit').submit();
    assert.equal(p.requests.length, before + 1, 'a second click while it waits sends nothing');

    // Refused: the words of the server are in the box, which stays open for another try.
    p.refuse(request, { status: 422, responseJSON: { message: 'Nilai baru sama dengan nilai sekarang.' } });
    assert.equal(p.node('#wbEditError').text, 'Nilai baru sama dengan nilai sekarang.');
    assert.equal(p.node('#wbEditError').classes.has('d-none'), false);
    assert.notEqual(p.node('#wbEditModal').modal, 'hide');
    assert.equal(p.node('#wbEditSend').props.disabled, false);
    assert.equal(p.dialogs.length, 0);

    // Sent: the box closes, the person is told in words that it is not saved yet, and the table is read again to show the mark.
    p.page('wbEdit').submit();
    assert.equal(p.node('#wbEditError').classes.has('d-none'), true, 'the old refusal is gone');
    p.answer(p.requests.at(-1), { message: 'Pengajuan perubahan sel B3 dikirim. Isi workbook baru berubah setelah disetujui.' });
    assert.equal(p.node('#wbEditModal').modal, 'hide');
    assert.equal(p.dialogs.length, 1);
    assert.equal(p.dialogs[0].args[0], 'Pengajuan dikirim');
    assert.match(p.dialogs[0].args[1], /setelah disetujui/);
    assert.equal(p.dialogs[0].args[2], 'success');
    assert.match(p.requests.at(-1).url, /\/documents\/ict-masterlist\/rows$/);
    assert.equal(p.node('#wbEditSend').props.disabled, false);
});

check('the words of a refusal: what the server says, or what went wrong where it says nothing', () => {
    const failure = xhr => editable().page('wbEdit').failure(xhr);
    assert.equal(failure({ status: 401 }), 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.');
    assert.equal(failure({ status: 419 }), 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.');
    // The menu refuses in English: said in Indonesian. This module's own refusals are Indonesian already and are shown as they are.
    assert.equal(failure({ status: 403, responseJSON: { message: 'You are not allowed to perform this action.' } }), 'Role Anda tidak punya izin Update untuk menu ini.');
    assert.equal(failure({ status: 403 }), 'Role Anda tidak punya izin Update untuk menu ini.');
    assert.equal(failure({ status: 403, responseJSON: { message: 'Data workbook ini belum bisa diubah dari sini.' } }), 'Data workbook ini belum bisa diubah dari sini.');
    assert.equal(failure({ status: 422, responseJSON: { message: 'The given data was invalid.', errors: { line: ['Baris harus angka.'] } } }), 'Baris harus angka.');
    assert.equal(failure({ status: 409, responseJSON: { message: 'Sudah ada pengajuan untuk sel B3.' } }), 'Sudah ada pengajuan untuk sel B3.');
    assert.equal(failure({ status: 500 }), 'Pengajuan tidak bisa dikirim. Coba lagi beberapa saat lagi.');
    assert.equal(failure({ status: 502, responseJSON: { message: '' } }), 'Pengajuan tidak bisa dikirim. Coba lagi beberapa saat lagi.');
});

check('what is shown of a refusal or of the cell is text, never markup', () => {
    const p = editable({ data: [{ line: 3, cells: ['<img src=x onerror=alert(1)>', '"><script>alert(2)</script>', 'x'] }] });
    click(p, 3, 1);
    assert.equal(p.node('#wbEditValue').value, '"><script>alert(2)</script>');
    p.page('wbEdit').submit();
    p.refuse(p.requests.at(-1), { status: 422, responseJSON: { message: '<b>salah</b>' } });
    // (Written with text(), which a browser does not read as markup.)
    assert.equal(p.node('#wbEditError').wrote, 'text');
    assert.equal(p.node('#wbEditError').text, '<b>salah</b>');
    assert.equal(p.written().includes('<script>'), false);
    p.page('wbEdit').submit();
    p.answer(p.requests.at(-1), { message: '<img src=x onerror=alert(3)>' });
    assert.equal(p.dialogs[0].args[1], '&lt;img src=x onerror=alert(3)&gt;');
});

check('Enter in the box sends the question; any other key does not', () => {
    const p = editable();
    click(p, 3, 1);
    const before = p.requests.length;
    p.fire('#wbEditValue', 'keydown', { key: 'a', preventDefault() {} });
    assert.equal(p.requests.length, before);
    let prevented = false;
    p.fire('#wbEditValue', 'keydown', { key: 'Enter', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(p.requests.length, before + 1);
    assert.equal(p.sent().line, 3);
});

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const used = ids();
    assert.ok(used.size >= 11, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(html), `the page has no element with id="${id}"`);
    }
    assert.ok(/class="[^"]*\bpagination-container\b/.test(html), 'the page has no .pagination-container');
    assert.ok(/<table[^>]*id="wbTable"[^>]*>\s*<thead><\/thead>\s*<tbody><\/tbody>/.test(html), 'the table has no empty head and body to fill');
});

done();
