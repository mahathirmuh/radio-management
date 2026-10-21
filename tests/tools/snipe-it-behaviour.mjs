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
// A row as the server sends it: an asset leads to its detail here (inside), another row to Snipe-IT's page; Snipe-IT's picture
// of it, and the icon of its category.
const row = (id, cells = {}, link = `/snipe-it/assets/${id}`, inside = true, image = 'https://snipeit.test/uploads/models/p6620i.png', icon = 'radio') => ({
    id, link, inside, image, icon,
    cells: {
        asset_tag: cell(`MTI-HT-00${id}`, `HT ${id}`), serial: cell(`867TBT000${id}`), model: cell('Motorola XiR P6620i'), category: cell('HT'),
        status: cell('Deployed', null, 'blue'), holder: cell('Ann O\'Neil', 'User'), location: cell(null), updated: cell('21-10-2026 09:15'), open: cell(null),
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
check('a row: the picture with the tag and the name, the status a badge in its colour, the holder with what it is, a dash for nothing, a button to the detail', () => {
    const p = loaded();
    const rows = body(p).split('</tr>');
    assertWellFormed(body(p), 'the rows');
    // Snipe-IT's picture over the icon of the category (which shows when the picture cannot be loaded), the tag, the name.
    assert.match(rows[0], /<td class=""><div class="snipe-asset"><span class="snipe-thumb"><i class="ti ti-radio" aria-hidden="true"><\/i><img src="https:\/\/snipeit\.test\/uploads\/models\/p6620i\.png" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this\.remove\(\)"><\/span><div class="min-w-0"><div class="rm-strong">MTI-HT-001<\/div><div class="rm-line2">HT 1<\/div><\/div><\/div><\/td>/);
    // The button to the detail: a page here, in the same tab, with its words for the eye and the ear.
    assert.match(rows[0], /<td class="text-end rm-sticky-end"><div class="rm-acts"><a href="\/snipe-it\/assets\/1" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Lihat detail" aria-label="Lihat detail"><i class="ti ti-eye" aria-hidden="true"><\/i><\/a><\/div><\/td>/);
    assert.doesNotMatch(rows[0], /target=/, 'the detail opens in the same tab');
    assert.doesNotMatch(rows[0], /class="snipe-link"/, 'the tag itself is no link: the button is');
    assert.match(rows[0], /<td class=""><span class="rm-none">—<\/span><\/td>/);
    assert.match(rows[0], /<td class=""><span class="rm-code">867TBT0001<\/span><\/td>/);
    assert.match(rows[0], /<td class="is-badge"><span class="rm-badge blue">Deployed<\/span><\/td>/);
    assert.match(rows[0], /<td class="">Ann O&#39;Neil<div class="rm-line2">User<\/div><\/td>/);
    assert.match(rows[0], /<td class="is-date">21-10-2026 09:15<\/td>/);
    assert.equal(p.node('#snipePagination').hidden, false);
    assert.match(p.node('#snipePagination').html, /1 – 2 .* 2/);
});

check('everything from Snipe-IT is escaped; a page of Snipe-IT opens in a tab of its own; without a picture or a page, the icon and no button; an unknown colour is grey', () => {
    const p = loaded([row(3, {
        asset_tag: cell('<img src=x onerror=alert(1)>', '<b>sub</b>'),
        model: cell('"><script>alert(2)</script>'),
        status: cell('Odd', null, 'pink" onmouseover="alert(3)'),
    }, 'http://snipeit.test/hardware/3?a="b"', false, 'https://snipeit.test/p.png" onload="alert(4)', 'x" onclick="alert(5)'), row(4, {}, null, true, null, 'device-laptop')]);
    const html = body(p);
    const [third, fourth] = html.split('</tr>');
    assertWellFormed(html, 'the rows');
    assert.doesNotMatch(html, /<script|<b>|\sonload="|\sonclick="/, 'no attribute of the page from Snipe-IT');
    assert.equal((html.match(/<img /g) || []).length, 1, 'one picture: the escaped address of the third row');
    assert.match(third, /<img src="https:\/\/snipeit\.test\/p\.png&quot; onload=&quot;alert\(4\)" alt=""/);
    assert.match(third, /<i class="ti ti-box" aria-hidden="true"><\/i>/, 'an icon that is no name of one is the box');
    assert.match(third, /<div class="rm-strong">&lt;img src=x onerror=alert\(1\)&gt;<\/div><div class="rm-line2">&lt;b&gt;sub&lt;\/b&gt;<\/div>/);
    assert.match(third, /&quot;&gt;&lt;script&gt;alert\(2\)&lt;\/script&gt;/);
    assert.match(third, /<a href="http:\/\/snipeit\.test\/hardware\/3\?a=&quot;b&quot;" class="rm-act" data-bs-toggle="tooltip" data-bs-title="Lihat detail" aria-label="Lihat detail" target="_blank" rel="noopener noreferrer">/);
    assert.match(third, /<span class="rm-badge gray">Odd<\/span>/);
    assert.match(fourth, /<span class="snipe-thumb"><i class="ti ti-device-laptop" aria-hidden="true"><\/i><\/span>/);
    assert.match(fourth, /<td class="text-end rm-sticky-end"><\/td>/, 'no page, no button');
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
    for (const key of ['asset_tag', 'serial', 'model', 'category', 'status', 'holder', 'location', 'updated']) {
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
