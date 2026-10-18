// Runs the inline JavaScript of the register list (Register Radio) without a browser and checks the bar of the radios
// ticked (radio.bulk): a box per row, the box of the header for the page, what is ticked kept while the page changes,
// "every radio of the filter" offered, chosen and dropped when the filter changes, and what each action sends.
//
// usage: node radio-bulk-behaviour.mjs <file with the rendered page>
//
// The page's own scripts are taken from the rendered HTML and run by page-sandbox.mjs, where "$" is a small stand-in for
// jQuery. PHPUnit calls this from RadioBulkTest.
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const { node, page, fire, requests, dialogs, resolve, answer } = loadPage(
    process.argv[2],
    ['function debounce(', 'function renderTableWithFeatures(', 'const RadioUi = {', 'const radioFilter = {', 'const radioForm = {', 'const radioArchive = {', 'const radioPrint = {', 'const radioBulk = {', 'const radioTable = {'],
    { href: 'http://localhost/radio-management/radio' }
);
const { check, done } = checker();
const [radioBulk, radioTable] = ['radioBulk', 'radioTable'].map(name => page(name));

// A row of the list as the server sends it (the table draws every column when the page is drawn again).
const row = (id, extra = {}) => ({
    id, dmr_id: id + 10, mdc: (id + 10).toString(16).toUpperCase(), serial_number: `867TEST${String(id).padStart(4, '0')}`,
    archived: false, placement: { value: 'user', label: 'Di User', color: 'blue' }, condition: { value: 'layak', label: 'Layak', color: 'green' },
    issues: [], detail_url: `http://localhost/radio-management/radio/${id}/detail`, print_url: `http://localhost/radio-management/radio/${id}/checkout-form`,
    ...extra,
});
const rows = [row(1), row(2), row(3)];
/** The list answered: these rows, of a filter of ten. */
const listed = (shown = rows, total = 10) => {
    radioTable.last = { data: shown, meta: { total } };
    radioBulk.shown(shown, { total });
};
const reset = () => {
    radioBulk.picked.clear();
    radioBulk.all = null;
    radioTable.search = '';
    radioTable.orderBy = 'dmr_id';
    listed();
};
/** A click on the box of a row: what jQuery reads of it (.data, :checked) is set on the element first. */
const tick = (r, on = true) => {
    Object.assign(node('(object)'), { data: { id: r.id, label: radioBulk.label(r) }, props: { checked: on } });
    fire('#radioTable', 'change input.radio-pick');
};
const cell = r => radioTable.columns.find(column => column.key === 'pick').render(r);
const lastTo = url => requests.filter(request => request.url === url).at(-1);
const shown = selector => !node(selector).classes.has('d-none');
const noDefault = { preventDefault() {} };
// What the page made (in the sandbox's own realm) as plain data of this one, to be compared.
const plain = value => JSON.parse(JSON.stringify(value));

check('a row has a box that names its radio, as text that cannot break the markup', () => {
    reset();
    assert.match(cell(row(1)), /^<input type="checkbox" class="form-check-input m-0 radio-pick" aria-label="Pilih ID 11 · 867TEST0001" data-id="1" data-label="ID 11 · 867TEST0001">$/);
    assert.match(cell(row(4, { dmr_id: null, serial_number: '"><img src=x onerror=alert(1)>' })), /data-label="&quot;&gt;&lt;img src=x onerror=alert\(1\)&gt;"/);
    assert.match(cell(row(5, { dmr_id: null, serial_number: null })), /data-label="#5"/);
});

check('ticking rows counts them; the bar and the box of the header say so', () => {
    reset();
    assert.equal(shown('#radioBulkBar'), false);
    tick(rows[0]);
    tick(rows[1]);
    assert.deepEqual([...radioBulk.picked.keys()], [1, 2]);
    assert.equal(shown('#radioBulkBar'), true);
    assert.equal(node('#radioBulkCount').text, '2 radio dipilih');
    assert.deepEqual([node('#radioPickPage').props.checked, node('#radioPickPage').props.indeterminate], [false, true]);
    assert.match(cell(rows[0]), / checked>$/);
    tick(rows[0], false);
    assert.deepEqual([...radioBulk.picked.keys()], [2]);
    tick(rows[0]);
    tick(rows[2]);
    assert.deepEqual([node('#radioPickPage').props.checked, node('#radioPickPage').props.indeterminate], [true, false]);
    // The whole page ticked, the filter has more: every radio of the filter is offered.
    assert.equal(shown('#radioBulkAll'), true);
    assert.equal(node('#radioBulkAll').text, 'Pilih semua 10 radio hasil filter');
});

check('what is ticked stays while the page changes', () => {
    reset();
    rows.forEach(r => tick(r));
    listed([row(4), row(5)]);
    assert.equal(node('#radioBulkCount').text, '3 radio dipilih');
    assert.deepEqual([node('#radioPickPage').props.checked, node('#radioPickPage').props.indeterminate], [false, false]);
    assert.equal(shown('#radioBulkAll'), false);
    assert.match(cell(row(1)), / checked>$/);
    assert.doesNotMatch(cell(row(4)), / checked/);
});

check('the box of the header ticks and unticks the page', () => {
    reset();
    node('(object)').props = { checked: true };
    fire('#radioPickPage', 'change');
    assert.deepEqual([...radioBulk.picked.keys()], [1, 2, 3]);
    node('(object)').props = { checked: false };
    fire('#radioPickPage', 'change');
    assert.equal(radioBulk.picked.size, 0);
    assert.equal(shown('#radioBulkBar'), false);
});

check('every radio of the filter: chosen, sent as the filter, kept for another order, dropped for another filter', () => {
    reset();
    rows.forEach(r => tick(r));
    fire('#radioBulkAll', 'click', noDefault);
    assert.equal(radioBulk.all.total, 10);
    assert.equal(node('#radioBulkCount').text, 'Semua 10 radio hasil filter dipilih');
    assert.equal(shown('#radioBulkAll'), false);
    // Every box is ticked, and stays so.
    assert.match(cell(row(9)), / checked disabled>$/);
    // Verifying takes it; archiving and deleting want radios ticked one by one.
    assert.equal(node('#radioBulkVerify').props.disabled, false);
    assert.equal(node('#radioBulkArchive').props.disabled, true);
    assert.equal(node('#radioBulkDelete').props.disabled, true);
    assert.equal(node('#radioBulkDelete').attrs.title, 'Untuk aksi ini, centang radionya satu per satu.');
    const payload = radioBulk.payload();
    assert.equal(payload.all, 1);
    assert.equal(payload.scope, 'active');
    assert.equal('ids' in payload || 'page' in payload || 'paginated' in payload || 'orderBy' in payload, false);
    // Another order is the same radios.
    radioTable.orderBy = 'serial_number';
    radioTable.load();
    assert.notEqual(radioBulk.all, null);
    // Another filter is not.
    radioTable.search = '867TZB';
    radioTable.load();
    assert.equal(radioBulk.all, null);
    assert.equal(shown('#radioBulkBar'), false);
});

check('"Kosongkan pilihan" lets go of everything', () => {
    reset();
    rows.forEach(r => tick(r));
    fire('#radioBulkAll', 'click', noDefault);
    fire('#radioBulkClear', 'click', noDefault);
    assert.deepEqual([radioBulk.all, radioBulk.picked.size, shown('#radioBulkBar')], [null, 0, false]);
});

check('verifying sends the radios ticked, the date and the note; then the choice is let go and the list loads again', () => {
    reset();
    tick(rows[0]);
    tick(rows[1]);
    fire('#radioBulkVerify', 'click');
    assert.equal(node('#radioBulkVerifyModal').modal, 'show');
    assert.equal(node('#radioBulkVerifyLabel').text, '2 radio dipilih.');
    assert.match(node('#bulk_verified_at').value, /^\d{4}-\d{2}-\d{2}$/);
    node('#bulk_verified_at').value = '2026-10-18';
    node('#bulk_verify_note').value = 'Stock opname Weda';
    radioBulk.submitVerify();
    const sent = lastTo(radioBulk.urls.verify);
    assert.equal(sent.type, 'POST');
    assert.deepEqual(plain(sent.data), { _method: 'PUT', ids: [1, 2], event_date: '2026-10-18', description: 'Stock opname Weda' });
    const loads = requests.length;
    answer(sent, { message: '2 radio diverifikasi.', done: 2, skipped: [] });
    assert.equal(node('#radioBulkVerifyModal').modal, 'hide');
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.icon], ['Berhasil', 'success']);
    assert.equal(radioBulk.picked.size, 0);
    assert.equal(requests.length, loads + 1);
    assert.equal(requests.at(-1).url, radioTable.listUrl);
});

check('every radio of a filter is verified as that filter', () => {
    reset();
    rows.forEach(r => tick(r));
    fire('#radioBulkAll', 'click', noDefault);
    fire('#radioBulkVerify', 'click');
    assert.equal(node('#radioBulkVerifyLabel').text, 'Semua 10 radio hasil filter.');
    radioBulk.submitVerify();
    const data = lastTo(radioBulk.urls.verify).data;
    assert.deepEqual([data._method, data.all, data.scope, 'ids' in data], ['PUT', 1, 'active', false]);
});

check('archiving sends one reason, date and note for the radios ticked', () => {
    reset();
    tick(rows[2]);
    fire('#radioBulkArchive', 'click');
    assert.equal(node('#radioBulkArchiveModal').modal, 'show');
    Object.assign(node('#bulk_archive_reason'), { value: 'Afkir' });
    node('#bulk_archived_at').value = '2026-10-18';
    node('#bulk_archive_note').value = 'Rusak berat';
    radioBulk.submitArchive();
    assert.deepEqual(plain(lastTo(radioBulk.urls.archive).data), { _method: 'PUT', ids: [3], archive_reason: 'Afkir', archived_at: '2026-10-18', note: 'Rusak berat' });
});

check('deleting asks for the number to be typed, and only that number deletes', () => {
    reset();
    tick(rows[0]);
    tick(rows[1]);
    const before = requests.length;
    fire('#radioBulkDelete', 'click');
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.input], ['Hapus 2 radio?', 'text']);
    resolve({ isConfirmed: false });
    resolve({ isConfirmed: true, value: '3' }, dialogs.at(-1));
    assert.equal(requests.length, before);
    assert.equal(dialogs.at(-1).options.title, 'Tidak dihapus');
    fire('#radioBulkDelete', 'click');
    resolve({ isConfirmed: true, value: ' 2 ' });
    assert.deepEqual(plain(lastTo(radioBulk.urls.destroy).data), { _method: 'DELETE', ids: [1, 2], confirm: 2 });
});

check('what was left out is said as text, and the choice is let go', () => {
    reset();
    tick(rows[0]);
    radioBulk.finished({ message: '<b>1</b> radio diverifikasi. 1 dilewati.', done: 1, skipped: [{ radio: '<img src=x onerror=alert(1)>', reason: 'sudah diarsipkan' }] });
    const dialog = dialogs.at(-1).options;
    assert.deepEqual([dialog.title, dialog.icon], ['Sebagian dilewati', 'warning']);
    assert.match(dialog.html, /&lt;b&gt;1&lt;\/b&gt; radio diverifikasi/);
    assert.match(dialog.html, /<li>&lt;img src=x onerror=alert\(1\)&gt;: sudah diarsipkan<\/li>/);
    assert.equal(radioBulk.picked.size, 0);
});

check('the export is of the radios ticked, by their ids, or of the filter', () => {
    reset();
    tick(rows[0]);
    tick(rows[1]);
    fire('#radioBulkExport', 'click');
    const ticked = String(page('window.location'));
    assert.ok(ticked.startsWith(radioBulk.urls.export + '?'), ticked);
    assert.match(ticked, /ids=1%2C2/);
    assert.match(ticked, /orderBy=dmr_id/);
    rows.forEach(r => tick(r));
    fire('#radioBulkAll', 'click', noDefault);
    fire('#radioBulkExport', 'click');
    const all = String(page('window.location'));
    assert.match(all, /scope=active/);
    assert.doesNotMatch(all, /ids=/);
});

check('archiving and deleting wait for at most a hundred radios ticked', () => {
    reset();
    for (let id = 1; id <= 101; id++) radioBulk.pick(id, `#${id}`, true);
    assert.equal(node('#radioBulkArchive').props.disabled, true);
    assert.equal(node('#radioBulkArchive').attrs.title, 'Paling banyak 100 radio sekaligus.');
    assert.equal(node('#radioBulkVerify').props.disabled, false);
    radioBulk.pick(101, '', false);
    assert.equal(node('#radioBulkArchive').props.disabled, false);
});

done();
