// Runs the inline JavaScript of a page of the menu "Snipe-IT" (snipeList, here the page "Aset") without a browser and
// checks what it does: what it asks the server for (search, filters, order, page, "Refresh"), what the address of the
// page says, how a row is drawn (a link to Snipe-IT, a badge in its colour, a second line, a dash for nothing), that
// every text from Snipe-IT is escaped, and what it says when the server has no answer.
//
// usage: node snipe-it-behaviour.mjs <file with the rendered page>
//
// Like the other *-behaviour.mjs checkers: the page's script and the global helpers it uses are taken from the rendered
// HTML and run by page-sandbox.mjs. PHPUnit calls this from SnipeItTest (the page in Indonesian, as it opens by default).
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const snipeList = {'];
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/snipe-it/assets' });
const { check, done } = checker();

const meta = (total, current = 1) => ({ current_page: current, per_page: 25, total, last_page: Math.max(1, Math.ceil(total / 25)), from: total ? 1 : null, to: total ? Math.min(25, total) : null, total_text: String(total) });
const cell = (t, s = null, tone = null) => ({ t, s, tone });
// A row as the server sends it.
const row = (id, cells = {}, link = `http://snipeit.test/hardware/${id}`) => ({
    id, link,
    cells: {
        asset_tag: cell(`MTI-HT-00${id}`), name: cell(null), serial: cell(`867TBT000${id}`), model: cell('Motorola XiR P6620i'), category: cell('HT'),
        status: cell('Deployed', null, 'blue'), holder: cell('Ann O\'Neil', 'User'), location: cell(null), updated: cell('21-10-2026 09:15'),
        ...cells,
    },
});
const loaded = (rows = [row(1), row(2)], total = 2) => {
    const p = open();
    p.answer(p.requests[0], { data: rows, meta: meta(total), sort: { key: 'updated', order: 'desc' } });

    return p;
};
const body = p => p.node('#snipeTable tbody').html;

// ---------------------------------------------------------------- the first request
check('the page asks for its first page in its own order, and shows that it is loading', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.match(p.requests[0].url, /\/snipe-it\/assets\/list$/);
    assert.deepEqual(p.sent(), { tab: 'assets', page: 1, paginated: 25, search: '', category: '', status: '', sort: 'updated', order: 'desc' });
    assert.match(body(p), /Memuat\.\.\./);
    assert.equal(p.node('#snipePagination').hidden, true);
});

// ---------------------------------------------------------------- a row
check('a row: the asset tag a link to Snipe-IT, the status a badge in its colour, the holder with what it is, a dash for nothing', () => {
    const p = loaded();
    const rows = body(p).split('</tr>');
    assertWellFormed(body(p), 'the rows');
    assert.match(rows[0], /<td class=""><a href="http:\/\/snipeit\.test\/hardware\/1" class="snipe-link" target="_blank" rel="noopener noreferrer" title="Buka di Snipe-IT">MTI-HT-001<\/a><\/td>/);
    assert.match(rows[0], /<td class=""><span class="rm-none">—<\/span><\/td>/);
    assert.match(rows[0], /<td class=""><span class="rm-code">867TBT0001<\/span><\/td>/);
    assert.match(rows[0], /<td class="is-badge"><span class="rm-badge blue">Deployed<\/span><\/td>/);
    assert.match(rows[0], /<td class="">Ann O&#39;Neil<div class="rm-line2">User<\/div><\/td>/);
    assert.match(rows[0], /<td class="is-date">21-10-2026 09:15<\/td>/);
    assert.equal(p.node('#snipePagination').hidden, false);
    assert.match(p.node('#snipePagination').html, /1 – 2 .* 2/);
});

check('everything from Snipe-IT is escaped; a link-less row shows its tag in bold; an unknown colour is grey', () => {
    const p = loaded([row(3, {
        asset_tag: cell('<img src=x onerror=alert(1)>'),
        name: cell('"><script>alert(2)</script>', '<b>sub</b>'),
        status: cell('Odd', null, 'pink" onmouseover="alert(3)'),
    }, 'http://snipeit.test/hardware/3?a="b"'), row(4, {}, null)]);
    const html = body(p);
    assertWellFormed(html, 'the rows');
    assert.doesNotMatch(html, /<img|<script|<b>/);
    assert.match(html, /href="http:\/\/snipeit\.test\/hardware\/3\?a=&quot;b&quot;"/);
    assert.match(html, /&lt;img src=x onerror=alert\(1\)&gt;<\/a>/);
    assert.match(html, /&quot;&gt;&lt;script&gt;alert\(2\)&lt;\/script&gt;<div class="rm-line2">&lt;b&gt;sub&lt;\/b&gt;<\/div>/);
    assert.match(html, /<span class="rm-badge gray">Odd<\/span>/);
    assert.match(html.split('</tr>')[1], /<td class=""><span class="rm-strong">MTI-HT-004<\/span><\/td>/);
});

// ---------------------------------------------------------------- search, filters, address
check('a search is sent after a pause, or at once with Enter; a filter at once; the address says what is shown', () => {
    const p = loaded();
    p.node('#snipeSearch').value = '  P6620i ';
    p.fire('#snipeSearch', 'input', {});
    assert.equal(p.requests.length, 1, 'not before the pause');
    p.runTimers();
    assert.equal(p.sent().search, 'P6620i');
    assert.equal(p.sent().page, 1);
    assert.equal(p.addresses().at(-1), '/snipe-it/assets?q=P6620i');

    p.node('#snipeFilter-category').value = '101';
    p.node('#snipeFilter-status').value = '4';
    p.fire('[data-snipe-filter]', 'change', {});
    assert.deepEqual([p.sent().category, p.sent().status, p.sent().search], ['101', '4', 'P6620i']);
    assert.equal(p.addresses().at(-1), '/snipe-it/assets?q=P6620i&category=101&status=4');

    p.node('#snipeSearch').value = 'MTI-HT';
    p.fire('#snipeSearch', 'keydown', { key: 'Enter' });
    assert.equal(p.sent().search, 'MTI-HT');
});

check('nothing found says so; without a search, that there is nothing', () => {
    const p = open();
    p.answer(p.requests[0], { data: [], meta: meta(0) });
    assert.match(body(p), /Tidak ada data\./);
    assert.equal(p.node('#snipePagination').hidden, true);
    p.node('#snipeSearch').value = 'zzz';
    p.fire('#snipeSearch', 'keydown', { key: 'Enter' });
    p.answer(p.requests.at(-1), { data: [], meta: meta(0) });
    assert.match(body(p), /Tidak ada yang cocok dengan pencarian\./);
});

// ---------------------------------------------------------------- order, pages, refresh
check('a click on a column orders by it, a second click the other way; page links and the page size ask for that page', () => {
    const p = loaded([row(1)], 60);
    for (const key of ['asset_tag', 'name', 'serial', 'model', 'category', 'status', 'holder', 'location', 'updated']) {
        assert.equal(p.exists(`#snipeTable th[data-key="${key}"]`), true, key);
    }
    p.fire('#snipeTable th[data-key="asset_tag"]', 'click');
    assert.deepEqual([p.sent().sort, p.sent().order, p.sent().page], ['asset_tag', 'asc', 1]);
    p.answer(p.requests.at(-1), { data: [row(1)], meta: meta(60), sort: { key: 'asset_tag', order: 'asc' } });
    p.fire('#snipeTable th[data-key="asset_tag"]', 'click');
    assert.deepEqual([p.sent().sort, p.sent().order], ['asset_tag', 'desc']);
    p.answer(p.requests.at(-1), { data: [row(1)], meta: meta(60), sort: { key: 'asset_tag', order: 'desc' } });

    p.node('(object)').data.page = 3;
    p.fire('#snipePagination .btn-pagination, .btn-previous, .btn-next', 'click');
    assert.deepEqual([p.sent().page, p.sent().paginated, p.sent().sort, p.sent().order], [3, 25, 'asset_tag', 'desc']);
    p.answer(p.requests.at(-1), { data: [row(1)], meta: meta(60, 3) });
    p.node('(object)').data.limit = 50;
    p.fire('#snipePagination .pagination-limit', 'click', { preventDefault() {} });
    assert.deepEqual([p.sent().page, p.sent().paginated], [1, 50]);
});

check('"Refresh" asks Snipe-IT again for what is shown', () => {
    const p = loaded();
    p.fire('#snipeRefresh', 'click', {});
    assert.equal(p.sent().fresh, 1);
    assert.equal(p.sent().tab, 'assets');
    assert.match(body(p), /Memuat\.\.\./);
});

// ---------------------------------------------------------------- no answer
check('when the server has no answer, the table says why (the server\'s words), else that it could not load', () => {
    const p = open();
    p.refuse(p.requests[0], { status: 503, responseJSON: { message: 'Snipe-IT tidak bisa dihubungi. <b>Coba lagi</b>' } });
    assert.match(body(p), /Snipe-IT tidak bisa dihubungi\. &lt;b&gt;Coba lagi&lt;\/b&gt;/);
    assert.equal(p.node('#snipePagination').hidden, true);

    const q = open();
    q.refuse(q.requests[0], { status: 500 });
    assert.match(body(q), /Data Snipe-IT tidak bisa dimuat\./);
});

done();
