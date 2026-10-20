// Runs the script of "Servis & Perbaikan" (serviceBoard and its dialogs) without a browser and checks what it does: the
// board and the list drawn from the open tickets (as text), the four numbers, "N lagi", the search and the service center
// filtering the cards and their counts, the drawer of a ticket, a new ticket, the next step and a note.
//
// usage: node service-board-behaviour.mjs <file with the rendered page, for Super Admin>
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from ServiceTicketTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const rmPickers = {', 'const RadioUi = {', 'const serviceBoard = {'];
const open = (href = 'http://localhost/radio-management/service') => loadPage(process.argv[2], markers, { href });
const { check, done } = checker();
/** An address without its scheme and host (the page is rendered for APP_URL). */
const path = url => String(url).replace(/^https?:\/\/[^/]+/, '');

const today = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

const row = (id, status, other = {}) => ({
    id,
    number: `SRV-000${id}`,
    radio: String(750 + id),
    dmr_id: 750 + id,
    sn: `903KXM201${id}`,
    type: 'P8668i',
    issue: `Masalah ${id}`,
    reported_by: `Pemakai ${id} · Lab`,
    service_center: status === 'reported' ? null : 'Pusat A',
    status,
    status_label: { reported: 'Dilaporkan', sent: 'Dikirim ke servis', in_repair: 'Dalam perbaikan', ready: 'Siap diambil' }[status],
    days: id,
    overdue: false,
    ...other,
});
const rows = [
    row(1, 'reported', { issue: 'LCD <b>retak</b>' }),
    row(2, 'reported'),
    row(3, 'reported'),
    row(4, 'reported'),
    row(5, 'sent', { overdue: true, days: 20, service_center: 'Pusat "B"' }),
    row(6, 'sent'),
    row(7, 'in_repair'),
];
const stats = { open: 7, reported: 4, away: 3, overdue: 1, turnaround: null, closed: 3, cost: 14200000 };
const listed = (p, body = { data: rows, stats, centers: ['Pusat A', 'Pusat "B"'] }) => {
    const asked = p.requests.filter(request => /\/service\/list$/.test(request.url)).at(-1);
    assert.ok(asked, 'the list was asked for');
    p.answer(asked, body);
};
const board = p => p.node('#serviceBoard').html;
const column = (p, status) => {
    const match = new RegExp(`<div class="rm-svc-col" data-status="${status}">([\\s\\S]*?)</div>(?=<div class="rm-svc-col"|$)`).exec(board(p));
    assert.ok(match, `column ${status}`);
    return match[1];
};
const cards = html => (html.match(/class="rm-svc-card[" ]/g) || []).length;
const ticket = (other = {}) => ({
    ...row(5, 'sent'),
    radio_label: 'ID 755 · 903KXM2015',
    document_no: 'BA2300418',
    created_at: '2026-10-17',
    sent_at: '2026-10-18',
    expected_at: '2026-10-28',
    returned_at: null,
    quote: 1850000,
    cost: null,
    placement_before: 'stok_it',
    next: 'in_repair',
    radio_now: { gone: false, archived: false, placement: 'Service', condition: 'Perlu Service', url: 'http://localhost/radio-management/radio/5/detail' },
    events: [
        { kind: 'note', status: null, note: 'Menunggu <img src=x onerror=alert(1)>', user: 'Petugas Satu', at: '2026-10-19 08:05' },
        { kind: 'status', status: 'sent', note: 'Dikirim ke Pusat A · BA BA2300418', user: 'Petugas Satu', at: '2026-10-18 16:40' },
        { kind: 'status', status: 'reported', note: 'Dilaporkan: Masalah 5', user: 'Petugas Dua', at: '2026-10-17 09:12' },
    ],
    ...other,
});
/** Opens the drawer of ticket 5 and answers it. */
const drawer = (p, other = {}) => {
    p.node('(object)').data = { ticket: 5 };
    p.fire('#serviceBoard', 'click [data-ticket]');
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/service\/5$/);
    p.answer(asked, { data: ticket(other) });
};

check('the board is drawn from the open tickets: a column for each step, the counts, three cards and "N lagi"', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    listed(p);
    const html = board(p);
    assertWellFormed(html, 'the board');
    assert.deepEqual([...html.matchAll(/data-status="(\w+)"/g)].map(match => match[1]), ['reported', 'sent', 'in_repair', 'ready']);
    assert.match(column(p, 'reported'), /<span class="rm-svc-count">4<\/span>/);
    assert.equal(cards(column(p, 'reported')), 3, 'three cards, the fourth behind "1 lagi"');
    assert.match(column(p, 'reported'), /data-more="reported" aria-expanded="false">1 lagi<i class="ti ti-chevron-down"/);
    assert.match(column(p, 'ready'), /<span class="rm-svc-count">0<\/span><\/div><div class="rm-svc-none">Tidak ada tiket<\/div>/);
    // Text stays text; a ticket away too long is red; one not sent yet says so.
    assert.ok(html.includes('LCD &lt;b&gt;retak&lt;/b&gt;') && !html.includes('<b>retak'));
    assert.match(column(p, 'sent'), /class="rm-svc-card is-late" data-ticket="5">.*Pusat &quot;B&quot;<\/span><span class="rm-svc-days is-late">20 hari<\/span>/);
    assert.match(column(p, 'reported'), /<span class="rm-svc-vendor">Belum dikirim<\/span><span class="rm-svc-days">1 hari<\/span>/);
    assert.match(column(p, 'reported'), /<span class="rm-svc-radio">751<\/span><span class="rm-svc-no">SRV-0001<\/span><span class="rm-badge gray">P8668i<\/span>/);
    assert.equal(p.node('#serviceClear').classes.has('d-none'), true);
});

check('the four numbers above the board', () => {
    const p = open();
    listed(p);
    const shown = p.node('#serviceStats').html;
    assertWellFormed(shown, 'the numbers');
    assert.deepEqual([...shown.matchAll(/rm-svc-stat-value( is-red)?">([^<]*)</g)].map(match => [match[2], !!match[1]]),
        [['7', false], ['—', false], ['1', true], ['Rp 14,2 jt', false]]);
    assert.ok(textOf(shown).includes('4 dilaporkan, 3 di pusat servis'));
    assert.ok(textOf(shown).includes('Lebih dari 14 hari di servis'));
    assert.ok(textOf(shown).includes('3 tiket selesai'));

    const q = open();
    listed(q, { data: [], stats: { open: 0, reported: 0, away: 0, overdue: 0, turnaround: 9, closed: 0, cost: 850000 }, centers: [] });
    assert.deepEqual([...q.node('#serviceStats').html.matchAll(/rm-svc-stat-value( is-red)?">([^<]*)</g)].map(match => [match[2], !!match[1]]),
        [['0', false], ['9 hari', false], ['0', false], ['Rp 850.000', false]], 'none overdue is not red');
    assert.equal(q.page('serviceBoard').money(1250000000), 'Rp 1,3 M');
});

check('the service centers fill the filter and the suggestions of the dialog, as text', () => {
    const p = open();
    listed(p);
    assert.equal(p.node('#serviceCenter').html, '<option value="">Semua pusat servis</option><option value="Pusat A">Pusat A</option><option value="Pusat &quot;B&quot;">Pusat &quot;B&quot;</option>');
    assert.equal(p.node('#serviceCenterList').html, '<option value="Pusat A"></option><option value="Pusat &quot;B&quot;"></option>');
});

check('"N lagi" shows the rest, which rise in turn; "Sembunyikan" folds the column again', () => {
    const p = open();
    listed(p);
    p.node('(object)').data = { more: 'reported' };
    p.fire('#serviceBoard', 'click [data-more]');
    assert.equal(cards(column(p, 'reported')), 4);
    assert.match(column(p, 'reported'), /class="rm-svc-card is-extra" data-ticket="4" style="--i: 0"/);
    assert.equal((column(p, 'reported').match(/is-extra/g) || []).length, 1, 'only the card that was hidden rises');
    assert.match(column(p, 'reported'), /aria-expanded="true">Sembunyikan<i class="ti ti-chevron-up"/);

    p.node('(object)').data = { more: 'reported' };
    p.fire('#serviceBoard', 'click [data-more]');
    assert.equal(cards(column(p, 'reported')), 3);
});

check('the search filters the cards and the counts after a pause; "Hapus filter" shows, and clears it', () => {
    const p = open();
    listed(p);
    p.node('#serviceSearch').value = '  903KXM2016 ';
    p.fire('#serviceSearch', 'input');
    assert.equal(cards(board(p)), 7 - 1, 'not before the pause (the fourth reported card is folded)');
    p.runTimers();
    assert.equal(cards(board(p)), 1);
    assert.match(column(p, 'sent'), /<span class="rm-svc-count">1<\/span>/);
    assert.match(column(p, 'reported'), /<span class="rm-svc-count">0<\/span><\/div><div class="rm-svc-none">Tidak ada yang cocok<\/div>/);
    assert.equal(p.node('#serviceClear').classes.has('d-none'), false);
    assert.equal(path(p.node('#serviceExport').attrs.href), '/radio-management/service/export?q=903KXM2016');

    // While a filter is on, every match is shown: no "N lagi".
    p.node('#serviceSearch').value = 'masalah';
    p.fire('#serviceSearch', 'input');
    p.runTimers();
    assert.equal(cards(column(p, 'reported')), 3);
    assert.doesNotMatch(board(p), /data-more/);

    p.fire('#serviceClear', 'click');
    assert.equal(p.node('#serviceSearch').value, '');
    assert.equal(p.node('#serviceClear').classes.has('d-none'), true);
    assert.equal(path(p.node('#serviceExport').attrs.href), '/radio-management/service/export');
    assert.equal(cards(board(p)), 6);
});

check('the service center filters the cards too, and goes into the export', () => {
    const p = open();
    listed(p);
    p.node('#serviceCenter').value = 'Pusat "B"';
    p.fire('#serviceCenter', 'change');
    assert.equal(cards(board(p)), 1);
    assert.match(board(p), /data-ticket="5"/);
    assert.equal(path(p.node('#serviceExport').attrs.href), '/radio-management/service/export?center=Pusat+%22B%22');
});

check('the list: a row for each ticket, as text, the status and the days as badges; the address keeps the view', () => {
    const p = open();
    listed(p);
    p.node('(object)').value = 'list';
    p.fire('input[name="serviceView"]', 'change');
    assert.equal(p.node('#serviceListWrap').classes.has('d-none'), false);
    assert.equal(p.node('#serviceBoard').classes.has('d-none'), true);
    assert.ok(p.node('#serviceListWrap').classes.has('rm-fade-a') || p.node('#serviceListWrap').classes.has('rm-fade-b'), 'the view fades in');
    const body = p.node('#serviceTable tbody').html;
    assertWellFormed(body, 'the list');
    assert.equal((body.match(/<tr data-ticket=/g) || []).length, 7);
    assert.ok(body.includes('LCD &lt;b&gt;retak&lt;/b&gt;'));
    assert.match(body, /<tr data-ticket="5"><td><button type="button" class="rm-svc-rowlink">SRV-0005<\/button><\/td><td class="rm-svc-two"><b>755<\/b><span>P8668i<\/span><\/td>/);
    assert.match(body, /<span class="rm-badge blue radio-badge">Dikirim ke servis<\/span><\/td><td class="text-end"><span class="rm-badge red">20 hari<\/span>/);
    assert.match(body, /<span class="radio-none">Belum dikirim<\/span><\/td><td><span class="rm-badge gray radio-badge">Dilaporkan<\/span>/);
    assert.equal(p.node('#serviceListFoot').text, 'Semua 7 tiket terbuka');
    assert.equal(p.addresses().at(-1), '/radio-management/service?view=list');

    p.node('#serviceSearch').value = 'tidak ada';
    p.fire('#serviceSearch', 'input');
    p.runTimers();
    assert.match(p.node('#serviceTable tbody').html, /colspan="7"[^>]*>Tidak ada tiket yang cocok\.</);
    assert.equal(p.node('#serviceListFoot').text, 'Tidak ada tiket yang cocok');

    p.node('(object)').value = 'board';
    p.fire('input[name="serviceView"]', 'change');
    assert.equal(p.addresses().at(-1), '/radio-management/service');
});

check('a card opens the drawer: the steps, what is known and the activity, as text; the address names the ticket', () => {
    const p = open();
    listed(p);
    drawer(p);
    assert.equal(p.node('#serviceDrawer').offcanvas, 'show');
    assert.equal(p.node('#serviceDrawerNo').text, 'SRV-0005');
    assert.equal(p.node('#serviceDrawerTitle').text, 'Radio 755 · Masalah 5');
    assert.equal(p.node('#serviceDrawerSub').text, 'P8668i · SN 903KXM2015 · Pemakai 5 · Lab');
    const body = p.node('#serviceDrawerBody').html;
    assertWellFormed(body, 'the drawer');
    assert.deepEqual([...body.matchAll(/<div class="rm-step( is-done)?( is-on)?"/g)].map(match => (match[1] ? 'done' : match[2] ? 'on' : '-')), ['done', 'on', '-', '-', '-']);
    assert.match(body, /aria-current="step"><span class="rm-step-dot">2<\/span><span class="rm-step-label">Dikirim<\/span>/);
    assert.match(body, /<dt>Dikirim<\/dt><dd class="is-mono">18 Okt 2026 · BA2300418<\/dd>/);
    assert.match(body, /<dt>Perkiraan kembali<\/dt><dd>28 Okt 2026<\/dd><dt>Penawaran<\/dt><dd>Rp 1\.850\.000<\/dd>/);
    assert.match(body, /<dt>Status radio<\/dt><dd>Lokasi: Service · Kondisi: Perlu Service · <a href="http:\/\/localhost\/radio-management\/radio\/5\/detail">Buka radio<\/a><\/dd>/);
    assert.ok(body.includes('Menunggu &lt;img src=x onerror=alert(1)&gt;') && !body.includes('<img'));
    assert.match(body, /rm-svc-act-dot is-note[\s\S]*?Catatan · 19 Okt 2026, 08:05 · Petugas Satu/);
    assert.match(body, /rm-svc-act-dot is-sent[\s\S]*?Dikirim ke Pusat A · BA BA2300418<\/div><div class="rm-svc-act-meta">18 Okt 2026, 16:40 · Petugas Satu/);
    assert.equal(p.node('#serviceNextButton').classes.has('d-none'), false);
    assert.equal(p.node('#serviceNextButton').html, '<i class="ti ti-tool" aria-hidden="true"></i> Tandai dalam perbaikan');
    assert.equal(p.addresses().at(-1), '/radio-management/service?ticket=5');

    // Closed, the address forgets it.
    p.fire('#serviceDrawer', 'hidden.bs.offcanvas');
    assert.equal(p.page('serviceBoard').ticket, null);
    assert.equal(p.addresses().at(-1), '/radio-management/service');
});

check('a ticket that is back: every step done, no next step; a radio gone from the register says so', () => {
    const p = open();
    listed(p);
    drawer(p, { status: 'returned', next: null, returned_at: '2026-10-27', cost: 2050000, radio_now: { gone: true, archived: false, placement: 'Stok IT', condition: 'Layak', url: null } });
    const body = p.node('#serviceDrawerBody').html;
    assert.equal((body.match(/<div class="rm-step is-done"/g) || []).length, 5);
    assert.doesNotMatch(body, /is-on|aria-current/);
    assert.match(body, /<dt>Biaya<\/dt><dd>Rp 2\.050\.000<\/dd><dt>Kembali<\/dt><dd>27 Okt 2026<\/dd><dt>Status radio<\/dt><dd>Sudah dihapus dari register<\/dd>/);
    assert.equal(p.node('#serviceNextButton').classes.has('d-none'), true);
});

check('a link with the view and a ticket opens them', () => {
    const p = open('http://localhost/radio-management/service?view=list&ticket=5');
    assert.equal(p.node('#serviceViewList').props.checked, true);
    assert.deepEqual(p.requests.map(request => path(request.url)), ['/radio-management/service/list', '/radio-management/service/5']);
    p.refuse(p.requests[1], { status: 404, responseJSON: { message: 'Not found' } });
    assert.equal(p.dialogs.at(-1).options.titleText, 'Tiket itu tidak ditemukan.');
    assert.equal(p.node('#serviceDrawer').offcanvas, undefined);
});

check('a new ticket: the radio is looked up after a pause, one with an open ticket cannot be picked, the reporter is filled in', () => {
    const p = open();
    listed(p);
    p.fire('#serviceNew', 'click');
    assert.equal(p.node('#serviceNewModal').modal, 'show');
    assert.equal(p.node('#serviceNewSave').props.disabled, true);

    p.node('#serviceRadioSearch').value = ' 751 ';
    p.fire('#serviceRadioSearch', 'input');
    assert.equal(p.requests.length, 1, 'not before the pause');
    p.runTimers();
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/service\/radios$/);
    assert.deepEqual({ ...asked.data }, { q: '751' });
    p.answer(asked, { data: [
        { id: 31, dmr_id: 751, sn: '903KXM2011', type: 'P8668i', holder: 'Pengguna <i>Satu</i> · Lab', placement: 'Di User', open: null },
        { id: 32, dmr_id: 7510, sn: null, type: null, holder: null, placement: 'Stok IT', open: 'SRV-0009' },
    ] });
    const found = p.node('#serviceRadioMatches').html;
    assertWellFormed(found, 'the radios found');
    assert.match(found, /<button type="button" class="rm-match" data-pick="0"><span class="rm-match-name">ID 751 · P8668i<\/span><span class="rm-match-line">SN 903KXM2011 · Pengguna &lt;i&gt;Satu&lt;\/i&gt; · Lab<\/span><span class="rm-match-id">Di User<\/span>/);
    assert.match(found, /data-pick="1" disabled><span class="rm-match-name">ID 7510<\/span><span class="rm-match-line">tanpa SN<\/span><span class="rm-match-id">Tiket SRV-0009 masih terbuka<\/span>/);
    // A radio without a Radio ID goes by its SN, which is not said twice.
    assert.equal(p.page('serviceForm').name({ dmr_id: null, sn: '867DOC0001', type: 'P6620i' }), 'SN 867DOC0001 · P6620i');
    assert.equal(p.page('serviceForm').line({ dmr_id: null, sn: '867DOC0001', holder: 'Gudang IT' }), 'Gudang IT');

    p.node('(object)').data = { pick: 1 };
    p.fire('#serviceRadioMatches', 'click [data-pick]');
    assert.equal(p.page('serviceForm').radio, null, 'a radio with an open ticket is not taken');
    p.node('(object)').data = { pick: 0 };
    p.fire('#serviceRadioMatches', 'click [data-pick]');
    assert.equal(p.page('serviceForm').radio.id, 31);
    assert.equal(p.node('#serviceRadioSearchWrap').classes.has('d-none'), true);
    assert.ok(p.node('#serviceRadioPicked').html.includes('Pengguna &lt;i&gt;Satu&lt;/i&gt; · Lab'));
    assert.equal(p.node('#serviceReporter').value, 'Pengguna <i>Satu</i> · Lab');
    assert.equal(p.node('#serviceNewSave').props.disabled, true, 'not without the issue');
    p.node('#serviceIssue').value = 'Layar retak';
    p.fire('#serviceIssue', 'input');
    assert.equal(p.node('#serviceNewSave').props.disabled, false);

    // "Ganti": back to the search.
    p.fire('#serviceRadioPicked', 'click [data-change]');
    assert.equal(p.page('serviceForm').radio, null);
    assert.equal(p.node('#serviceRadioSearchWrap').classes.has('d-none'), false);
    assert.equal(p.node('#serviceNewSave').props.disabled, true);
});

check('saving a new ticket sends what was typed; refused, the reason shows as text; saved, the board is read again and the ticket opens', () => {
    const p = open();
    listed(p);
    const form = p.page('serviceForm');
    form.found = [{ id: 31, dmr_id: 751, sn: '903KXM2011', type: 'P8668i', holder: 'Pengguna Satu · Lab', placement: 'Di User', open: null }];
    form.pick(0);
    p.node('#serviceIssue').value = '  Layar retak ';
    p.node('#serviceReporter').value = 'Pengguna Satu · Lab';
    p.fire('#serviceNewSave', 'click');
    const asked = p.requests.at(-1);
    assert.equal(asked.type, 'POST');
    assert.match(asked.url, /\/radio-management\/service$/);
    assert.deepEqual({ ...asked.data }, { radio_id: 31, issue: 'Layar retak', reported_by: 'Pengguna Satu · Lab' });
    assert.equal(p.node('#serviceNewSave').props.disabled, true, 'busy');

    p.refuse(asked, { status: 422, responseJSON: { errors: ['Radio ini masih punya tiket servis yang terbuka: <b>SRV-0001</b>.'] } });
    assert.equal(p.node('#serviceNewErrorText').text, 'Radio ini masih punya tiket servis yang terbuka: <b>SRV-0001</b>.');
    assert.equal(p.node('#serviceNewError').classes.has('d-none'), false);
    assert.equal(p.node('#serviceNewSave').props.disabled, false);

    p.fire('#serviceNewSave', 'click');
    p.answer(p.requests.at(-1), { message: 'Tiket SRV-0010 dibuat.', data: { id: 10, number: 'SRV-0010' } });
    assert.equal(p.node('#serviceNewModal').modal, 'hide');
    assert.equal(p.dialogs.at(-1).options.titleText, 'Tiket SRV-0010 dibuat.');
    assert.deepEqual(p.requests.slice(-2).map(request => path(request.url)), ['/radio-management/service/list', '/radio-management/service/10']);
});

check('the next step asks for what it needs: sending without a service center is not sent; what is sent', () => {
    const p = open();
    listed(p);
    drawer(p, { status: 'reported', next: 'sent', service_center: null, sent_at: null, document_no: null, expected_at: null, quote: null });
    p.fire('#serviceNextButton', 'click');
    assert.equal(p.node('#serviceMoveModal').modal, 'show');
    // The drawer steps aside for the dialog, and keeps its ticket meanwhile.
    assert.equal(p.node('#serviceDrawer').offcanvas, 'hide');
    p.fire('#serviceDrawer', 'hidden.bs.offcanvas');
    assert.equal(p.page('serviceBoard').ticket.id, 5);
    assert.equal(p.addresses().at(-1), '/radio-management/service?ticket=5');
    assert.equal(p.node('#serviceMoveTitle').text, 'Kirim ke servis');
    assert.equal(p.node('#serviceMoveSub').text, 'SRV-0005 · Radio 755');
    assert.equal(p.node('#serviceMoveModal .svc-field').classes.has('d-none'), true);
    assert.equal(p.node('#serviceMoveModal .svc-for-sent').classes.has('d-none'), false);
    assert.equal(p.node('#serviceSentAt').value, today);
    assert.equal(p.node('#servicePlacement').value, 'stok_it', 'back where it was before it went');

    const before = p.requests.length;
    p.node('#serviceCenterInput').value = '  ';
    p.fire('#serviceMoveSave', 'click');
    assert.equal(p.requests.length, before);
    assert.equal(p.node('#serviceMoveErrorText').text, 'Isi pusat servis tujuan.');

    p.node('#serviceCenterInput').value = ' Pusat A ';
    p.node('#serviceBa').value = 'BA2300418';
    p.node('#serviceQuote').value = '1850000';
    p.node('#serviceExpected').value = '';
    p.fire('#serviceMoveSave', 'click');
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/service\/5\/status$/);
    assert.deepEqual({ ...asked.data }, { _method: 'PUT', status: 'sent', service_center: 'Pusat A', sent_at: today, document_no: 'BA2300418', quote: '1850000' });

    p.refuse(asked, { status: 422, responseJSON: { errors: ['Tiket ini sudah dipindah orang lain. Muat ulang halaman.'] } });
    assert.equal(p.node('#serviceMoveErrorText').text, 'Tiket ini sudah dipindah orang lain. Muat ulang halaman.');
    p.fire('#serviceMoveSave', 'click');
    p.answer(p.requests.at(-1), { message: 'Tiket SRV-0005: dikirim ke servis.' });
    assert.equal(p.node('#serviceMoveModal').modal, 'hide');
    assert.equal(p.dialogs.at(-1).options.titleText, 'Tiket SRV-0005: dikirim ke servis.');
    assert.deepEqual(p.requests.slice(-2).map(request => path(request.url)), ['/radio-management/service/list', '/radio-management/service/5']);
    // Closed, the dialog brings the drawer back (read again), once.
    p.fire('#serviceMoveModal, #serviceNoteModal', 'hidden.bs.modal');
    assert.equal(p.node('#serviceDrawer').offcanvas, 'show');
    assert.equal(p.page('serviceBoard').returning, false);
    p.node('#serviceDrawer').offcanvas = null;
    p.fire('#serviceMoveModal, #serviceNoteModal', 'hidden.bs.modal');
    assert.equal(p.node('#serviceDrawer').offcanvas, null, 'a dialog that was not opened from the drawer brings nothing back');
});

check('coming back sends the day, where the radio goes and its condition', () => {
    const p = open();
    listed(p);
    drawer(p, { status: 'ready', next: 'returned', placement_before: 'service', cost: 2050000 });
    p.fire('#serviceNextButton', 'click');
    assert.equal(p.node('#serviceMoveTitle').text, 'Radio kembali');
    assert.equal(p.node('#serviceMoveModal .svc-for-returned').classes.has('d-none'), false);
    assert.equal(p.node('#servicePlacement').value, 'di_user', 'a radio that was not with a user or in stock goes to a user');
    assert.equal(p.node('#serviceCondition').value, 'layak');
    assert.equal(p.node('#serviceCost').value, 2050000);
    p.node('#serviceCost').value = '';
    p.fire('#serviceMoveSave', 'click');
    assert.deepEqual({ ...p.requests.at(-1).data }, { _method: 'PUT', status: 'returned', returned_at: today, placement: 'di_user', condition: 'layak' });
});

check('a note is sent as typed, and the drawer is read again', () => {
    const p = open();
    listed(p);
    drawer(p);
    p.fire('#serviceNoteButton', 'click');
    assert.equal(p.node('#serviceNoteModal').modal, 'show');
    assert.equal(p.node('#serviceDrawer').offcanvas, 'hide');
    assert.equal(p.node('#serviceNoteSave').props.disabled, true);
    p.node('#serviceNoteText').value = ' Menunggu suku cadang ';
    p.fire('#serviceNoteText', 'input');
    assert.equal(p.node('#serviceNoteSave').props.disabled, false);
    p.fire('#serviceNoteSave', 'click');
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/service\/5\/note$/);
    assert.deepEqual({ ...asked.data }, { _method: 'PUT', note: 'Menunggu suku cadang' });
    p.answer(asked, { message: 'Catatan ditambahkan ke SRV-0005.' });
    assert.equal(p.node('#serviceNoteModal').modal, 'hide');
    assert.match(p.requests.at(-1).url, /\/radio-management\/service\/5$/);
});

done();
