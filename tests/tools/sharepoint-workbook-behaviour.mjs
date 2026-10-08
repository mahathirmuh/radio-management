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
import { checker, loadPage } from './page-sandbox.mjs';

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
    assert.deepEqual(sent(), { sheet: 0, page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc' });
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
    assert.match(node('#wbTable th[data-key="c0"]').html, /<span class="flex-grow-1 text-wrap "><\/span>/);
    assert.equal(node('#wbTable th[data-key="c0"] span.flex-grow-1').text, 'No');
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
    assert.deepEqual(sent(), { sheet: 1, page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc' });
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
    assert.deepEqual([sent().sheet, sent().sort, sent().dir, sent().page], [1, 'c1', 'asc', 1]);
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c1', dir: 'asc' } }));
    assert.match(node('#wbTable th[data-key="c1"]').html, /icon-tabler-chevron-up/);
    assert.doesNotMatch(node('#wbTable th[data-key="c0"]').html, /icon-tabler-chevron/);
    fire('#wbTable th[data-key="c1"]', 'click');
    assert.deepEqual([sent().sort, sent().dir], ['c1', 'desc']);
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c1', dir: 'desc' } }));
    assert.match(node('#wbTable th[data-key="c1"]').html, /icon-tabler-chevron-down/);
    fire('#wbTable th[data-key="row"]', 'click'); // "Baris": back to the order of the workbook, first row first
    assert.deepEqual([sent().sort, sent().dir], ['row', 'asc']);
    answer(requests.at(-1), reply(1, { sheet: { sort: 'row', dir: 'asc' } }));

    // Another sheet starts in its own order: a column of this one means nothing there.
    fire('#wbTable th[data-key="c1"]', 'click');
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c1', dir: 'asc' } }));
    tab(0);
    assert.deepEqual(sent(), { sheet: 0, page: 1, paginated: 25, search: '', sort: 'row', dir: 'desc' });
    answer(requests.at(-1), reply(0));
});

check('what the server made of a request is what the page goes on with', () => {
    Object.assign(wbBook, { sheet: 9, page: 7, sort: 'c40', dir: 'asc' });
    wbBook.load();
    assert.deepEqual([sent().sheet, sent().page, sent().sort], [9, 7, 'c40']);
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
    assert.deepEqual([sent().search, sent().page, sent().sheet], ['budi', 1, 0]);
    answer(requests.at(-1), reply(0, { matches: [0, 4, 0], data: [] }));
    assert.match(tabs(), /data-sheet="0">SRF <span class="badge bg-secondary-lt ms-1">0<\/span>/);
    assert.match(tabs(), /data-sheet="1">COF <span class="badge bg-green-lt ms-1">4<\/span>/);
    assert.match(body(), /Tidak ada baris yang cocok dengan pencarian di sheet ini\./);
    assert.equal(node('#wbInfo').text.startsWith('0 baris cocok di sheet "SRF"'), true);

    // The other sheet is opened with what is searched for still in place.
    tab(1);
    assert.deepEqual([sent().sheet, sent().search, sent().page], [1, 'budi', 1]);
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
    assert.match(node(' .pagination-container').html, /<a data-page="3" class="btn-pagination /);
    node('(object)').data.page = 3;
    fire(' .pagination-container .btn-pagination, .btn-previous, .btn-next', 'click');
    assert.deepEqual([sent().page, sent().paginated, sent().sheet, sent().search], [3, 25, 1, 'andi wijaya']);
    answer(requests.at(-1), { ...reply(1), meta: { ...meta(60), current_page: 3 } });
    assert.equal(wbBook.page, 3);
    node('(object)').value = '50';
    fire(' .pagination-container .pagination-limit', 'change');
    assert.deepEqual([sent().page, sent().paginated], [1, 50]);
    answer(requests.at(-1), reply(1, { total: 60 }));

    fire('#wbTable th[data-key="c0"]', 'click');
    answer(requests.at(-1), reply(1, { sheet: { sort: 'c0', dir: 'asc' }, total: 60 }));
    onPage(3);
    wbBook.reset(); // the search and the order go, the sheet stays
    assert.deepEqual(sent(), { sheet: 1, page: 1, paginated: 50, search: '', sort: 'row', dir: 'desc' });
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
    assert.equal(sent().sheet, 0);
    // ... and once it has arrived, the titles and the page links are its own.
    answer(requests.at(-1), reply(0, { total: 60 }));
    assert.deepEqual([wbBook.sheet, wbBook.shown], [0, 0]);
    fire('#wbTable th[data-key="c1"]', 'click');
    assert.deepEqual([sent().sheet, sent().sort], [0, 'c1']);
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

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const used = ids();
    assert.ok(used.size >= 9, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(html), `the page has no element with id="${id}"`);
    }
    assert.ok(/class="[^"]*\bpagination-container\b/.test(html), 'the page has no .pagination-container');
    assert.ok(/<table[^>]*id="wbTable"[^>]*>\s*<thead><\/thead>\s*<tbody><\/tbody>/.test(html), 'the table has no empty head and body to fill');
});

done();
