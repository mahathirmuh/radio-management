// Runs the script of "Servis & Perbaikan" rendered in English and checks the words it writes: the four numbers (amounts
// as "Rp 14.2M"), the days ("1 day", "0 days"), "N more", the list's foot, the drawer of a ticket, the radios found for a
// new ticket and the dialog of the next step. What the script does is checked in Indonesian by service-board-behaviour.mjs.
//
// usage: node service-board-english-behaviour.mjs <file with the rendered page, for Super Admin, in English>
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from ServiceLanguageTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const rmPickers = {', 'const RadioUi = {', 'const serviceBoard = {'];
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/radio-management/service' });
const { check, done } = checker();

const row = (id, status, other = {}) => ({
    id,
    number: `SRV-000${id}`,
    radio: String(750 + id),
    dmr_id: 750 + id,
    sn: `903KXM201${id}`,
    type: 'P8668i',
    issue: `Issue ${id}`,
    reported_by: `User ${id} · Lab`,
    service_center: status === 'reported' ? null : 'Pusat A',
    status,
    status_label: { reported: 'Reported', sent: 'Sent for service', in_repair: 'In repair', ready: 'Ready for pickup' }[status],
    days: id,
    overdue: false,
    ...other,
});
const rows = [
    row(1, 'reported', { days: 0 }),
    row(2, 'reported', { days: 1 }),
    row(3, 'reported'),
    row(4, 'reported'),
    row(5, 'sent', { overdue: true, days: 20 }),
];
const listed = (p, stats = { open: 5, reported: 4, away: 1, overdue: 1, turnaround: 1, closed: 1, cost: 14200000 }) => {
    const asked = p.requests.filter(request => /\/service\/list$/.test(request.url)).at(-1);
    assert.ok(asked, 'the list was asked for');
    p.answer(asked, { data: rows, stats, centers: ['Pusat A'] });
};
const board = p => p.node('#serviceBoard').html;
/** Opens the drawer of ticket 5 and answers it. */
const drawer = (p, other = {}) => {
    p.node('(object)').data = { ticket: 5 };
    p.fire('#serviceBoard', 'click [data-ticket]');
    p.answer(p.requests.at(-1), { data: {
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
        radio_now: { gone: false, archived: true, placement: 'In service', condition: 'Needs service', url: 'http://localhost/radio-management/radio/5/detail' },
        events: [
            { kind: 'note', status: null, note: 'Waiting for parts', user: 'Officer One', at: '2026-10-19 08:05' },
            { kind: 'status', status: 'sent', note: 'Sent to Pusat A · BA BA2300418', user: 'Officer One', at: '2026-10-18 16:40' },
        ],
        ...other,
    } });
};

check('the board: the steps by their English names, the days, "N more", an empty column, the filter of service centers', () => {
    const p = open();
    listed(p);
    const html = board(p);
    assertWellFormed(html, 'the board');
    assert.match(html, /<span class="rm-svc-col-title">Reported<\/span><span class="rm-svc-count">4<\/span>/);
    assert.match(html, /<span class="rm-svc-col-title">Sent for service<\/span><span class="rm-svc-count">1<\/span>/);
    assert.match(html, /<span class="rm-svc-col-title">Ready for pickup<\/span><span class="rm-svc-count">0<\/span><\/div><div class="rm-svc-none">No tickets<\/div>/);
    assert.match(html, /<span class="rm-svc-vendor">Not sent yet<\/span><span class="rm-svc-days">0 days<\/span>/);
    assert.match(html, /<span class="rm-svc-days">1 day<\/span>/);
    assert.match(html, /<span class="rm-svc-days is-late">20 days<\/span>/);
    assert.match(html, /data-more="reported" aria-expanded="false">1 more<i class="ti ti-chevron-down"/);
    assert.equal(p.node('#serviceCenter').html, '<option value="">All service centers</option><option value="Pusat A">Pusat A</option>');

    p.node('(object)').data = { more: 'reported' };
    p.fire('#serviceBoard', 'click [data-more]');
    assert.match(board(p), /aria-expanded="true">Hide<i class="ti ti-chevron-up"/);

    p.node('#serviceSearch').value = 'nothing like it';
    p.fire('#serviceSearch', 'input');
    p.runTimers();
    assert.match(board(p), /<div class="rm-svc-none">No matches<\/div>/);
});

check('the four numbers: amounts in short ("Rp 14.2M", "Rp 1.3B"), days and tickets counted in English', () => {
    const p = open();
    listed(p);
    const shown = p.node('#serviceStats').html;
    assertWellFormed(shown, 'the numbers');
    assert.deepEqual([...shown.matchAll(/rm-svc-stat-label">([^<]*)</g)].map(match => match[1]), ['Open tickets', 'Average time in service', 'Overdue', 'Cost this quarter']);
    assert.deepEqual([...shown.matchAll(/rm-svc-stat-value(?: is-red)?">([^<]*)</g)].map(match => match[1]), ['5', '1 day', '1', 'Rp 14.2M']);
    for (const hint of ['4 reported, 1 at a service center', 'Last 90 days', 'More than 14 days in service', '1 ticket closed']) {
        assert.ok(textOf(shown).includes(hint), hint);
    }
    assert.equal(p.page('serviceBoard').money(1250000000), 'Rp 1.3B');
    assert.equal(p.page('serviceBoard').money(850000), 'Rp 850,000');

    const q = open();
    listed(q, { open: 0, reported: 0, away: 0, overdue: 0, turnaround: 9, closed: 3, cost: 1234567 });
    const again = q.node('#serviceStats').html;
    assert.deepEqual([...again.matchAll(/rm-svc-stat-value(?: is-red)?">([^<]*)</g)].map(match => match[1]), ['0', '9 days', '0', 'Rp 1.2M']);
    assert.ok(textOf(again).includes('3 tickets closed'));
});

check('the list: the status by its English name, and its foot', () => {
    const p = open();
    listed(p);
    p.node('(object)').value = 'list';
    p.fire('input[name="serviceView"]', 'change');
    const body = p.node('#serviceTable tbody').html;
    assertWellFormed(body, 'the list');
    assert.match(body, /<span class="rm-badge blue radio-badge">Sent for service<\/span><\/td><td class="text-end"><span class="rm-badge red">20 days<\/span>/);
    assert.match(body, /<span class="radio-none">Not sent yet<\/span><\/td><td><span class="rm-badge gray radio-badge">Reported<\/span>/);
    assert.equal(p.node('#serviceListFoot').text, 'All 5 open tickets');

    p.node('#serviceSearch').value = '903KXM2015';
    p.fire('#serviceSearch', 'input');
    p.runTimers();
    assert.equal(p.node('#serviceListFoot').text, '1 ticket matches');
    p.node('#serviceSearch').value = 'issue';
    p.fire('#serviceSearch', 'input');
    p.runTimers();
    assert.equal(p.node('#serviceListFoot').text, '5 tickets match');
    p.node('#serviceSearch').value = 'nothing like it';
    p.fire('#serviceSearch', 'input');
    p.runTimers();
    assert.match(p.node('#serviceTable tbody').html, /colspan="7"[^>]*>No tickets match\.</);
    assert.equal(p.node('#serviceListFoot').text, 'No tickets match');
});

check('the drawer: the stepper, the facts and the activity in English', () => {
    const p = open();
    listed(p);
    drawer(p);
    const body = p.node('#serviceDrawerBody').html;
    assertWellFormed(body, 'the drawer');
    assert.deepEqual([...body.matchAll(/<span class="rm-step-label">([^<]*)</g)].map(match => match[1]), ['Reported', 'Sent', 'In repair', 'Ready', 'Returned']);
    assert.match(body, /<dt>Issue<\/dt><dd>Issue 5<\/dd><dt>Reported<\/dt><dd>17 Oct 2026 · User 5 · Lab<\/dd><dt>Service center<\/dt><dd>Pusat A<\/dd>/);
    assert.match(body, /<dt>Sent<\/dt><dd class="is-mono">18 Oct 2026 · BA2300418<\/dd><dt>Expected back<\/dt><dd>28 Oct 2026<\/dd><dt>Quote<\/dt><dd>Rp 1,850,000<\/dd>/);
    assert.match(body, /<dt>Radio status<\/dt><dd>Location: In service · Condition: Needs service \(archived\) · <a href="http:\/\/localhost\/radio-management\/radio\/5\/detail">Open the radio<\/a><\/dd>/);
    assert.match(body, /<div class="rm-svc-acts-title">Activity<\/div>/);
    assert.match(body, /rm-svc-act-dot is-note[\s\S]*?Note · 19 Oct 2026, 08:05 · Officer One/);
    assert.match(body, /Sent to Pusat A · BA BA2300418<\/div><div class="rm-svc-act-meta">18 Oct 2026, 16:40 · Officer One/);
    assert.equal(p.node('#serviceNextButton').html, '<i class="ti ti-tool" aria-hidden="true"></i> Mark as in repair');

    const q = open();
    listed(q);
    drawer(q, { status: 'returned', next: null, events: [], radio_now: null });
    assert.match(q.node('#serviceDrawerBody').html, /<dt>Radio status<\/dt><dd>Not in the register<\/dd>[\s\S]*<div class="radio-none">No activity yet\.<\/div>/);
});

check('a new ticket: the radios found, in English', () => {
    const p = open();
    listed(p);
    p.fire('#serviceNew', 'click');
    p.node('#serviceRadioSearch').value = '751';
    p.fire('#serviceRadioSearch', 'input');
    p.runTimers();
    p.answer(p.requests.at(-1), { data: [
        { id: 31, dmr_id: 751, sn: '903KXM2011', type: 'P8668i', holder: 'User One · Lab', placement: 'With user', open: null },
        { id: 32, dmr_id: 7510, sn: null, type: null, holder: null, placement: 'IT stock', open: 'SRV-0009' },
    ] });
    const found = p.node('#serviceRadioMatches').html;
    assertWellFormed(found, 'the radios found');
    assert.match(found, /<span class="rm-match-id">With user<\/span>/);
    assert.match(found, /<span class="rm-match-line">no SN<\/span><span class="rm-match-id">Ticket SRV-0009 is still open<\/span>/);
    assert.equal(p.page('serviceForm').name({ dmr_id: null, sn: null, type: 'P6620i' }), 'Radio without an ID · P6620i');

    p.node('(object)').data = { pick: 0 };
    p.fire('#serviceRadioMatches', 'click [data-pick]');
    assert.match(p.node('#serviceRadioPicked').html, /<button type="button" class="btn-rm sm secondary" data-change="1">Change<\/button>/);

    p.fire('#serviceRadioPicked', 'click [data-change]');
    p.node('#serviceRadioSearch').value = 'nothing';
    p.fire('#serviceRadioSearch', 'input');
    p.runTimers();
    p.answer(p.requests.at(-1), { data: [] });
    assert.equal(p.node('#serviceRadioMatches').html, '<div class="rm-hint mt-2">No active radio matches.</div>');
});

check('the next step: its dialog in English, and what it says without a service center; a ticket that is not there', () => {
    const p = open();
    listed(p);
    drawer(p, { status: 'reported', next: 'sent', service_center: null, sent_at: null, document_no: null });
    p.fire('#serviceNextButton', 'click');
    assert.equal(p.node('#serviceMoveTitle').text, 'Send for service');
    assert.equal(p.node('#serviceMoveSave').html, '<i class="ti ti-check" aria-hidden="true"></i> Send');
    p.node('#serviceCenterInput').value = ' ';
    p.fire('#serviceMoveSave', 'click');
    assert.equal(p.node('#serviceMoveErrorText').text, 'Fill in the service center it goes to.');

    p.page('serviceBoard').openTicket(99);
    p.refuse(p.requests.at(-1), { status: 404, responseJSON: { message: 'Not found' } });
    assert.equal(p.dialogs.at(-1).options.titleText, 'That ticket was not found.');
});

done();
