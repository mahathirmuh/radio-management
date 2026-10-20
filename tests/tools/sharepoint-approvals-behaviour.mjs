// Runs the inline JavaScript of the page "Persetujuan Edit" (eaUi / eaPage) without a browser and checks what it
// does: escaping of everything that comes from the database, which request a filter, a search or a page sends, which
// buttons a row has, what approving and turning down send, and what the person is told of the answer.
//
// usage: node sharepoint-approvals-behaviour.mjs <file with the rendered page>
//
// Like sharepoint-workbook-behaviour.mjs: the page's script and the two global helpers it uses are taken from the
// rendered HTML and run by page-sandbox.mjs. PHPUnit calls this from SharePointPageScriptTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const eaUi = {'];
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/documents/edit-approvals' });
const { check, done } = checker();

const meta = (total, current = 1) => ({ current_page: current, from: total ? 1 : null, last_page: Math.max(1, Math.ceil(total / 25)), per_page: 25, to: total || null, total });
const counts = (given = {}) => ({ pending: 0, processing: 0, applied: 0, rejected: 0, conflict: 0, failed: 0, ...given });
// One request as the list gives it.
const row = (id, other = {}) => ({
    id, workbook: 'ICT Masterlist', sheet: 'SRF', cell: `C${id}`, line: id, column: 'Requested By', row_key: `ICT/SRF/${id}`,
    old: 'Budi Santoso', new: 'Budi S.', status: 'pending', status_label: 'Menunggu', requested_by: 'Admin Radio Satu', requested_at: '12-10-2026 10:00',
    decided_by: null, decided_at: null, note: null, failure: null, applied_at: null, mine: false, can_approve: true, can_reject: true,
    open: `/documents/ict-masterlist?sheet=SRF&line=${id}`, ...other,
});
const reply = (rows, { total = null, others = {}, may = true } = {}) => ({ data: rows, meta: meta(total ?? rows.length), counts: counts(others), may_decide: may });
const loaded = (rows = [row(7), row(8)], extra = {}) => {
    const p = open();
    p.answer(p.requests[0], reply(rows, extra));

    return p;
};
const body = p => p.node('#eaTable tbody').html;
// Clicks a button of a row of the table, or a card, as the browser does: the element carries what it is for.
const press = (p, selector, event, attrs) => {
    Object.assign(p.node('(object)').attrs, attrs);

    return p.fire(selector, event, {});
};

// ---------------------------------------------------------------- first load
check('the page asks for the requests that wait, newest first, once', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.match(p.requests[0].url, /\/documents\/edit-approvals\/list$/);
    assert.deepEqual(p.sent(), { page: 1, paginated: 25, search: '', status: 'pending' });
    assert.equal(p.node('#eaReload').props.disabled, true);
    assert.match(body(p), /Loading/);
});

check('the requests are drawn: who asked, which cell, what it was and becomes, what became of it', () => {
    const p = loaded([
        row(7, { cell: 'C7', old: 'Budi Santoso', new: 'Budi S.' }),
        row(6, { status: 'applied', status_label: 'Diterapkan', decided_by: 'Doc Controller Satu', decided_at: '12-10-2026 11:00', applied_at: '12-10-2026 11:01', can_approve: false, can_reject: false }),
        row(5, { status: 'rejected', status_label: 'Ditolak', decided_by: 'Doc Controller Satu', decided_at: '12-10-2026 11:05', note: 'Nama salah', can_approve: false, can_reject: false }),
        row(4, { status: 'conflict', status_label: 'Konflik', failure: 'Isi sel C4 sudah berubah di SharePoint.', can_approve: false }),
        row(3, { old: '(kosong)', new: '(dikosongkan)' }),
    ], { others: { pending: 2, applied: 1, rejected: 1, conflict: 1 } });
    const rows = body(p);
    assertWellFormed(rows, 'the rows');
    assert.equal(p.node('#eaReload').props.disabled, false);

    // The first: who asked and when, the cell (a link to its row in the workbook), and the values: before, struck, and after.
    assert.match(rows, /<td class="text-muted">7<\/td>/);
    assert.match(rows, /<div class="rm-strong">Admin Radio Satu<\/div><div class="rm-line2">12-10-2026 10:00<\/div>/);
    assert.match(rows, /<a href="\/documents\/ict-masterlist\?sheet=SRF&amp;line=7" title="Lihat baris ini di ICT Masterlist"><b>C7<\/b> <span class="ea-muted">SRF<\/span><\/a>/);
    assert.match(rows, /<div class="ea-muted">Requested By · baris ICT\/SRF\/7<\/div>/);
    assert.match(rows, /<span class="ea-old">Budi Santoso<\/span><\/div><div><span class="ea-new">Budi S\.<\/span>/);
    // An empty cell, and one that is to be emptied, are said in words (and in italics).
    assert.match(rows, /<span class="ea-old ea-empty">\(kosong\)<\/span>/);
    assert.match(rows, /<span class="ea-new ea-empty">\(dikosongkan\)<\/span>/);
    // What became of it: the badge, and who decided, why, or why nothing was written.
    assert.match(rows, /<span class="badge bg-yellow-lt">Menunggu<\/span>/);
    assert.match(rows, /<span class="badge bg-green-lt">Diterapkan<\/span><div class="ea-muted mt-1">Disetujui Doc Controller Satu, 12-10-2026 11:01<\/div>/);
    assert.match(rows, /<span class="badge bg-red-lt">Ditolak<\/span><div class="ea-muted mt-1">Ditolak Doc Controller Satu, 12-10-2026 11:05<br>Alasan: Nama salah<\/div>/);
    assert.match(rows, /<span class="badge bg-orange-lt">Konflik<\/span><div class="ea-muted mt-1">Isi sel C4 sudah berubah di SharePoint\.<\/div>/);
});

check('the buttons of a row are the ones its reader may press', () => {
    const p = loaded([
        row(7),
        row(6, { can_approve: false }),
        row(5, { mine: true, can_approve: false }),
        row(4, { can_approve: false, can_reject: false, mine: false }),
        row(3, { status: 'failed', status_label: 'Gagal', failure: 'Workbook sedang dikunci.' }),
    ]);
    const rows = body(p).split('</tr>');
    const [first, turnDownOnly, own, none, again] = rows;
    assert.match(first, /<button type="button" class="btn-rm sm success ea-approve" data-id="7"><i class="ti ti-check"><\/i> Setujui<\/button> <button type="button" class="btn-rm sm secondary ea-reject" data-id="7">/);
    assert.doesNotMatch(turnDownOnly, /ea-approve/);
    assert.match(turnDownOnly, /ea-reject" data-id="6"/);
    // The one who asked is told that somebody else decides (and may still turn it down, when the menu lets them).
    assert.match(own, /ea-reject" data-id="5"/);
    assert.doesNotMatch(own, /ea-approve/);
    assert.doesNotMatch(none, /<button/);
    // A failed one can be approved again.
    assert.match(again, /ea-approve" data-id="3"/);
    assert.match(again, /Workbook sedang dikunci\./);

    const waiting = loaded([row(5, { mine: true, can_approve: false, can_reject: false })]);
    assert.match(body(waiting), /<span class="ea-muted">Menunggu orang lain<\/span>/);
});

check('what comes from the database is shown as text, and only an address of this site is a link', () => {
    const p = loaded([row(7, {
        sheet: '<img src=x onerror=alert(1)>', cell: '"><script>alert(2)</script>', column: "O'Neil & <strong>co</strong>", row_key: '<mark>k</mark>',
        old: '<s>lama</s>', new: '" onmouseover="alert(3)', requested_by: '<u>Dia</u>', status_label: '<kbd>Menunggu</kbd>', failure: '<script>alert(4)</script>',
        workbook: '<em>Buku</em>', open: '//evil.example/x',
    }), row(6, { open: 'javascript:alert(5)' }), row(5, { open: null })]);
    const out = p.written();
    for (const raw of ['<img', '<script', '<strong>', '<mark>', '<kbd>', '<s>', '<u>', '<em>', 'onmouseover="alert']) {
        assert.equal(out.includes(raw), false, `unescaped: ${raw}`);
    }
    assert.doesNotMatch(body(p), /href="\/\/evil|href="javascript/);
    // Without an address of this site the cell is only named.
    assert.match(body(p), /<div><b>C6<\/b> <span class="ea-muted">SRF<\/span><\/div>/);
    assertWellFormed(body(p), 'the rows');
    assert.equal(p.page('eaUi').isPath('/documents/x?a=1'), true);
    for (const odd of ['//evil.example', '/\\evil', 'https://evil.example', '/a b', 'x', null, undefined]) {
        assert.equal(p.page('eaUi').isPath(odd), false, String(odd));
    }
});

// ---------------------------------------------------------------- the tabs and the search
check('one tab for each status says how many there are, and a click shows only those', () => {
    const p = loaded([row(7)], { others: { pending: 3, applied: 2, conflict: 1 } });
    const cards = p.node('#eaTabs').html;
    assertWellFormed(cards, 'the tabs');
    // "Semua", Menunggu, Diterapkan and Ditolak; Diproses, Konflik and Gagal only while some requests have them.
    assert.deepEqual([...cards.matchAll(/data-status="([^"]*)"/g)].map(match => match[1]), ['', 'pending', 'applied', 'rejected', 'conflict']);
    assert.match(cards, />Semua<span class="rm-tab-count">6<\/span><\/button>/);
    assert.match(cards, /<button type="button" class="ea-tab is-on" data-status="pending" aria-pressed="true">Menunggu<span class="rm-tab-count">3</);
    assert.match(cards, /<button type="button" class="ea-tab" data-status="conflict" aria-pressed="false">Konflik<span class="rm-tab-count">1</);

    // Somebody is writing one: its card is there.
    const busy = loaded([row(7)], { others: { processing: 1 } });
    assert.match(busy.node('#eaTabs').html, /data-status="processing"/);
    assert.doesNotMatch(busy.node('#eaTabs').html, /data-status="(conflict|failed)"/);
    // The tab that is open stays, even with none (a link of a notification may open it).
    busy.page('eaPage').status = 'failed';
    busy.page('eaPage').tabs(counts());
    assert.match(busy.node('#eaTabs').html, /<button type="button" class="ea-tab is-on" data-status="failed" aria-pressed="true">Gagal<span class="rm-tab-count">0</);

    press(p, '#eaTabs', 'click button.ea-tab', { 'data-status': 'conflict' });
    assert.equal(p.sent().status, 'conflict');
    assert.equal(p.sent().page, 1);
    p.answer(p.requests.at(-1), reply([row(4, { status: 'conflict', status_label: 'Konflik' })], { others: { conflict: 1 } }));
    // The same card again shows all; "Semua" itself stays all.
    press(p, '#eaTabs', 'click button.ea-tab', { 'data-status': 'conflict' });
    assert.equal(p.sent().status, '');
    p.answer(p.requests.at(-1), reply([row(4)]));
    press(p, '#eaTabs', 'click button.ea-tab', { 'data-status': '' });
    assert.equal(p.sent().status, '');
});

check('a search is sent after a pause, or at once with Enter, from the first page; the reset button clears it', () => {
    const p = loaded();
    p.node('#eaSearch').value = '  C7  ';
    p.fire('#eaSearch', 'input', {});
    assert.equal(p.requests.length, 1, 'not before the pause');
    p.runTimers();
    assert.equal(p.requests.length, 2);
    assert.equal(p.sent().search, 'C7');
    p.answer(p.requests.at(-1), reply([row(7)]));

    p.node('#eaSearch').value = 'Budi';
    p.fire('#eaSearch', 'keydown', { key: 'Enter' });
    assert.equal(p.sent().search, 'Budi');
    p.answer(p.requests.at(-1), reply([row(7)]));

    p.page('eaPage').status = 'applied';
    p.page('eaPage').reset();
    assert.deepEqual([p.sent().search, p.sent().status, p.sent().page], ['', 'pending', 1]);
    assert.equal(p.node('#eaSearch').value, '');
});

check('an answer that comes late is not shown over a newer one', () => {
    const p = open();
    p.page('eaPage').load();
    p.answer(p.requests[0], reply([row(1)]));
    assert.doesNotMatch(body(p), /data-id="1"/);
    p.answer(p.requests[1], reply([row(2)]));
    assert.match(body(p), /data-id="2"/);
});

check('a reader who may only look is told so, and a failure to load is said in words', () => {
    const p = loaded([row(7, { can_approve: false, can_reject: false })], { may: false });
    assert.match(p.node('#eaNotice').html, /alert-info[^>]*>Role Anda hanya bisa melihat pengajuan/);
    assert.doesNotMatch(body(p), /<button/);
    p.page('eaPage').load();
    p.answer(p.requests.at(-1), reply([row(7)]));
    assert.equal(p.node('#eaNotice').html, '');

    const failed = open();
    failed.refuse(failed.requests[0], { status: 403 });
    assert.match(failed.node('#eaNotice').html, /alert-danger[^>]*>Role Anda tidak punya izin untuk menu ini\./);
    assert.match(body(failed), /Daftar pengajuan tidak bisa dimuat\./);
    assert.equal(failed.node('#eaReload').props.disabled, false);

    const hostile = open();
    hostile.refuse(hostile.requests[0], { status: 500, responseJSON: { message: '<b>galat</b>' } });
    assert.equal(hostile.node('#eaNotice').html.includes('<b>galat</b>'), false);
    const page = hostile.page('eaPage');
    assert.equal(page.failure({ status: 401 }), 'Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.');
    assert.equal(page.failure({ status: 500 }), 'Terjadi kesalahan. Coba lagi beberapa saat lagi.');
});

check('what is searched for is told in an empty table', () => {
    const p = loaded([]);
    assert.equal(p.page('eaPage').empty(), 'Tidak ada pengajuan dengan status "Menunggu".');
    p.page('eaPage').search = 'xyz';
    assert.equal(p.page('eaPage').empty(), 'Tidak ada pengajuan dengan status "Menunggu" yang cocok dengan pencarian.');
    p.page('eaPage').status = '';
    assert.equal(p.page('eaPage').empty(), 'Tidak ada pengajuan yang cocok dengan pencarian.');
});

// ---------------------------------------------------------------- deciding
check('approving asks first, shows what is changed, and only then sends; the answer says what happened', () => {
    const p = loaded([row(7, { new: '<b>Budi S.</b>' })]);
    const before = p.requests.length;
    press(p, '#eaTable', 'click button.ea-approve', { 'data-id': '7' });
    assert.equal(p.dialogs.length, 1);
    assert.equal(p.requests.length, before, 'nothing is sent by the click itself');
    const dialog = p.dialogs[0];
    assert.equal(dialog.options.title, 'Setujui perubahan ini?');
    assert.equal(dialog.options.showCancelButton, true);
    // The question says where the cell is, what it holds and what it becomes, as text; and that the workbook is changed at once.
    assert.match(dialog.options.html, /sel <b>C7<\/b> \(Requested By\)/);
    assert.match(dialog.options.html, /<span class="ea-diff-head">Sekarang<\/span><span class="ea-diff-head">Menjadi<\/span><span class="ea-old">Budi Santoso<\/span><span class="ea-new">&lt;b&gt;Budi S\.&lt;\/b&gt;<\/span>/);
    // In the design's dialog: a green tile, and the button of a step that succeeds.
    assert.equal(dialog.options.customClass.icon, 'rm-swal-icon tone-green');
    assert.equal(dialog.options.customClass.confirmButton, 'btn-rm success');
    assert.match(dialog.options.html, /Workbook di SharePoint akan langsung diubah/);

    // Confirmed: the dialog's own function sends the approval (a PATCH, as a form field), and keeps the dialog open while it waits.
    assert.equal(dialog.options.showLoaderOnConfirm, true);
    dialog.options.preConfirm();
    const request = p.requests.at(-1);
    assert.match(request.url, /\/documents\/edit-approvals\/7\/approve$/);
    assert.equal(request.type, 'POST');
    assert.deepEqual(p.sent(), { _method: 'PATCH' });
    // Whatever the answer, the dialog gets it as a value (a refusal is an answer too).
    let value = null;
    request.chained[0].success && (value = request.chained[0].success({ message: 'x' }));
    // (Objects made inside the page's own context are compared by what they hold.)
    const plain = given => JSON.parse(JSON.stringify(given));
    assert.deepEqual(plain(value), { ok: true, response: { message: 'x' } });
    assert.deepEqual(plain(request.chained[0].failure({ status: 409 })), { ok: false, xhr: { status: 409 } });

    // The person answers: written, and the list is read again.
    p.resolve({ isConfirmed: true, value: { ok: true, response: { message: 'Perubahan sel C7 sudah ditulis ke SharePoint.' } } });
    assert.equal(p.dialogs.length, 2);
    // Said in a toast, as text.
    assert.equal(p.dialogs[1].options.toast, true);
    assert.equal(p.dialogs[1].options.titleText, 'Perubahan sel C7 sudah ditulis ke SharePoint.');
    assert.match(p.requests.at(-1).url, /\/list$/);
});

check('a conflict, a failure and a refusal are told as what they are, and the list is read again', () => {
    const answerWith = value => {
        const p = loaded([row(7)]);
        press(p, '#eaTable', 'click button.ea-approve', { 'data-id': '7' });
        const sent = p.requests.length;
        p.resolve({ isConfirmed: true, value });

        return { p, listed: p.requests.length === sent + 1 && /\/list$/.test(p.requests.at(-1).url), told: p.dialogs.at(-1).args };
    };

    const conflict = answerWith({ ok: false, xhr: { status: 409, responseJSON: { message: 'Isi sel C7 sudah berubah.', status: 'conflict' } } });
    assert.deepEqual([...conflict.told], ['Tidak ditulis', 'Isi sel C7 sudah berubah.', 'warning']);
    assert.equal(conflict.listed, true);

    const failed = answerWith({ ok: false, xhr: { status: 502, responseJSON: { message: 'Workbook sedang dikunci.', status: 'failed' } } });
    assert.deepEqual([...failed.told], ['Gagal', 'Workbook sedang dikunci.', 'warning']);

    const refused = answerWith({ ok: false, xhr: { status: 403, responseJSON: { message: 'Pengajuan Anda sendiri tidak bisa Anda setujui.' } } });
    assert.deepEqual([...refused.told], ['Gagal', 'Pengajuan Anda sendiri tidak bisa Anda setujui.', 'error']);

    const markup = answerWith({ ok: false, xhr: { status: 409, responseJSON: { message: '<img src=x onerror=alert(1)>' } } });
    assert.equal(markup.told[1], '&lt;img src=x onerror=alert(1)&gt;');

    // Cancelled: nothing is sent and nothing is read again.
    const p = loaded([row(7)]);
    press(p, '#eaTable', 'click button.ea-approve', { 'data-id': '7' });
    const sent = p.requests.length;
    p.resolve({ isConfirmed: false });
    assert.equal(p.requests.length, sent);
    assert.equal(p.dialogs.length, 1);
});

check('turning down asks for a reason that may be left empty, and sends it', () => {
    const p = loaded([row(7)]);
    press(p, '#eaTable', 'click button.ea-reject', { 'data-id': '7' });
    const dialog = p.dialogs[0];
    assert.equal(dialog.options.title, 'Tolak perubahan ini?');
    assert.equal(dialog.options.input, 'textarea');
    assert.equal(dialog.options.inputAttributes.maxlength, 500);
    assert.equal(dialog.options.inputLabel, 'Alasan (boleh dikosongkan)');
    assert.equal(dialog.options.showLoaderOnConfirm, true);
    assert.equal(dialog.options.customClass.confirmButton, 'btn-rm destructive');

    dialog.options.preConfirm('Nama salah');
    assert.match(p.requests.at(-1).url, /\/documents\/edit-approvals\/7\/reject$/);
    assert.deepEqual(p.sent(), { _method: 'PATCH', note: 'Nama salah' });
    dialog.options.preConfirm(undefined);
    assert.deepEqual(p.sent(), { _method: 'PATCH', note: '' });

    p.resolve({ isConfirmed: true, value: { ok: true, response: { message: 'Pengajuan perubahan sel C7 ditolak.' } } });
    assert.equal(p.dialogs[1].options.toast, true);
    assert.equal(p.dialogs[1].options.titleText, 'Pengajuan perubahan sel C7 ditolak.');
    assert.match(p.requests.at(-1).url, /\/list$/);

    const refused = loaded([row(7)]);
    press(refused, '#eaTable', 'click button.ea-reject', { 'data-id': '7' });
    refused.resolve({ isConfirmed: true, value: { ok: false, xhr: { status: 409, responseJSON: { message: 'Pengajuan ini sudah diputuskan.' } } } });
    assert.deepEqual([...refused.dialogs[1].args], ['Gagal', 'Pengajuan ini sudah diputuskan.', 'error']);
});

check('a button of a row that is no longer on screen does nothing', () => {
    const p = loaded([row(7)]);
    press(p, '#eaTable', 'click button.ea-approve', { 'data-id': '99' });
    press(p, '#eaTable', 'click button.ea-reject', { 'data-id': '99' });
    assert.equal(p.dialogs.length, 0);
});

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const p = loaded();
    const used = p.ids();
    assert.ok(used.size >= 5, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
    assert.ok(/class="[^"]*\bpagination-container\b/.test(p.html), 'the page has no .pagination-container');
    assert.ok(/<table[^>]*id="eaTable"[^>]*>\s*<thead>[\s\S]*?<\/thead>\s*<tbody><\/tbody>/.test(p.html), 'the table has no head and empty body to fill');
    // The columns the script draws are the columns the head names, in that order.
    const heads = [...p.html.matchAll(/<th [^>]*data-key="(\w+)"/g)].map(match => match[1]);
    assert.deepEqual(heads, [...p.page('eaPage').columns].map(column => column.key));
    // The addresses of the buttons carry the id of the request in place of ":id".
    assert.match(p.page('eaPage').approve, /\/documents\/edit-approvals\/:id\/approve$/);
    assert.match(p.page('eaPage').reject, /\/documents\/edit-approvals\/:id\/reject$/);
});

done();
