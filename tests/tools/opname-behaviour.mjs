// Runs the script of "Stock Opname" (opnamePage, the page for a phone) without a browser and checks what it does: the first
// screen (the sessions, the outlook of a department), starting and going on with a session, a radio typed and looked up,
// its check (who has it, its condition) sent, a problem reported, the summary sent. Everything from the server is text.
// There is no BarcodeDetector here: the camera stays hidden and the Radio ID or SN is typed (K13a).
//
// usage: node opname-behaviour.mjs <file with the rendered page, for Super Admin>
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from OpnameTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const markers = ['const rmFeedback = {', 'const rmPickers = {', 'const RadioUi = {', 'const opnamePage = {'];
const open = (href = 'http://localhost/radio-management/opname') => loadPage(process.argv[2], markers, { href });
const { check, done } = checker();
/** An address without its scheme and host (the page is rendered for APP_URL). */
const path = url => String(url).replace(/^https?:\/\/[^/]+/, '');
const hidden = (p, selector) => p.node(selector).classes.has('d-none');

const state = (other = {}) => ({
    session: { id: 7, place: 'Bagian <b>Pabrik</b>', status: 'open', date: '2026-10-20' },
    expected: 38, found: 24, scanned: 26,
    recent: [{ radio: '1043 · MDC 413', who: 'Pemakai <i>Satu</i>, HSE', at: '2026-10-20 09:00:00' }],
    not_found: [{ radio: '1047 · MDC 417', who: 'POS <b>LABOTA</b>, Security', last_verified: '2026-02-02' }],
    other_user: [{ radio: '1051 · MDC 41B', register: 'LV 22', holder: 'Peminjam <b>Satu</b>' }],
    elsewhere: [{ radio: '1060 · MDC 424', department: 'Bagian Lab' }],
    summary: null,
    ...other,
});
const card = (other = {}) => ({
    id: 31, dmr_id: 1042, mdc: '412', sn: '867TZB5309', type: 'MotoTrbo XiR P6600i · HT', user: 'Pemakai <b>Lama</b>', department: 'Bagian Pabrik',
    last_verified: '2026-08-12', condition: 'perlu_service', placement: 'Di User', archived: false, here: true, scanned: null, ...other,
});
/** Into the scan screen of session 7. */
const scanning = (p, body = state()) => {
    p.page('opnamePage').enter(body);
};
/** A radio looked up and answered. */
const checking = (p, body = card()) => {
    scanning(p);
    p.node('#opnTyped').value = '1042';
    p.fire('#opnFind', 'click');
    p.answer(p.requests.at(-1), { data: body });
};

check('the first screen: the open sessions to go on with, the latest sent, as text', () => {
    const p = open();
    p.page('opnamePage').overview = {
        open: [{ id: 9, place: 'Bagian <b>Lab</b>', date: '2026-10-20', verified: 3, expected: 12 }],
        recent: [{ id: 4, place: 'HSE', date: '2026-10-12', verified: 18, expected: 18 }, { id: 3, place: 'Lab', date: '2026-10-02', verified: 9, expected: 12 }],
    };
    p.page('opnamePage').drawSessions();
    const html = p.node('#opnSessions').html;
    assertWellFormed(html, 'the sessions');
    assert.match(html, /^<div class="rm-phone-label">Sesi terbuka<\/div><button type="button" class="rm-phone-session" data-session="9"><span class="rm-phone-session-top">Bagian &lt;b&gt;Lab&lt;\/b&gt;<span>Terbuka<\/span><\/span>/);
    assert.match(html, /<div class="rm-phone-label">Sesi terakhir<\/div><div class="rm-phone-session"><span class="rm-phone-session-top">HSE<span>12 Okt 2026<\/span><\/span><span class="rm-phone-session-bar"><span class="rm-phone-bar"><span style="width: 100%"><\/span><\/span>18 dari 18<\/span><\/div>/);
    assert.match(html, /<span class="rm-phone-bar is-low"><span style="width: 75%"><\/span><\/span>9 dari 12/);

    p.node('(object)').data = { session: 9 };
    p.fire('#opnSessions', 'click [data-session]');
    assert.match(p.requests.at(-1).url, /\/radio-management\/opname\/9$/);
});

check('a department chosen: how many radios, its last session, and whether one is open there', () => {
    const p = open();
    p.node('#opnDepartment').value = '5';
    p.fire('#opnDepartment, #opnCompany', 'change');
    let asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/opname\/outlook$/);
    assert.deepEqual({ ...asked.data }, { department_id: '5' });
    p.answer(asked, { data: { expected: 38, last: { date: '2026-08-12', verified: 36 }, open: null } });
    assert.equal(p.node('#opnExpect').html, '<span class="rm-phone-big">38</span><span class="rm-phone-muted">radio diharapkan di sini<br>Opname terakhir 12 Agu 2026, 36 terverifikasi</span>');
    assert.equal(p.node('#opnStartButton').props.disabled, false);
    assert.equal(p.node('#opnStartButton').html, '<i class="ti ti-camera" aria-hidden="true"></i> Mulai sesi');

    p.node('#opnCompany').value = '2';
    p.fire('#opnDepartment, #opnCompany', 'change');
    asked = p.requests.at(-1);
    assert.deepEqual({ ...asked.data }, { department_id: '5', company_id: '2' });
    p.answer(asked, { data: { expected: 0, last: null, open: 9 } });
    assert.match(p.node('#opnExpect').html, /Belum pernah ada opname di sini/);
    assert.equal(p.node('#opnStartButton').html, '<i class="ti ti-player-play" aria-hidden="true"></i> Lanjutkan sesi');

    const before = p.requests.length;
    p.node('#opnDepartment').value = '';
    p.fire('#opnDepartment, #opnCompany', 'change');
    assert.equal(p.requests.length, before);
    assert.equal(p.node('#opnStartButton').props.disabled, true);
});

check('starting a session goes to the scan screen: the count so far, the latest scans as text; the address names it', () => {
    const p = open();
    p.node('#opnDepartment').value = '5';
    p.fire('#opnStartButton', 'click');
    const asked = p.requests.at(-1);
    assert.equal(asked.type, 'POST');
    assert.match(asked.url, /\/radio-management\/opname$/);
    assert.deepEqual({ ...asked.data }, { department_id: '5' });
    p.answer(asked, { data: state() });

    assert.equal(hidden(p, '#opnScan'), false);
    assert.equal(hidden(p, '#opnStart'), true);
    assert.equal(p.addresses().at(-1), '/radio-management/opname?session=7');
    assert.equal(p.node('#opnCamPlace, #opnScanPlace').text, 'Bagian <b>Pabrik</b>');
    assert.equal(p.node('#opnProgress').html, '<div class="rm-phone-progress-top">24 dari 38 terverifikasi<span>63%</span></div><div class="rm-phone-bar lg"><span style="width: 63%"></span></div>');
    const recent = p.node('#opnRecent').html;
    assertWellFormed(recent, 'the latest scans');
    assert.match(recent, /<div class="rm-phone-done-id">1043 · MDC 413<\/div><div class="rm-phone-done-who">Pemakai &lt;i&gt;Satu&lt;\/i&gt;, HSE<\/div>/);
    assert.equal(p.node('#opnToSummary').text, 'Selesai, lihat ringkasan (26 radio)');
    // No barcode reader in this browser: the camera stays hidden, the field is there.
    assert.equal(hidden(p, '#opnCam'), true);
    assert.equal(hidden(p, '#opnNoCam'), false);
});

check('a Radio ID or SN typed (Find, or Enter as a handheld scanner sends it) is looked up; not there, it says so', () => {
    const p = open();
    scanning(p);
    p.node('#opnTyped').value = ' 1042 ';
    p.fire('#opnFind', 'click');
    let asked = p.requests.at(-1);
    assert.match(path(asked.url), /^\/radio-management\/opname\/7\/lookup$/);
    assert.deepEqual({ ...asked.data }, { q: '1042' });
    p.fire('#opnFind', 'click');
    assert.equal(p.requests.at(-1), asked, 'one at a time');
    p.refuse(asked, { status: 404, responseJSON: { message: 'x' } });
    assert.equal(p.dialogs.at(-1).options.titleText, '1042: tidak ada di register.');

    let prevented = false;
    p.node('#opnTyped').value = '867TZB5309';
    p.fire('#opnTyped', 'keydown', { key: 'Enter', preventDefault: () => { prevented = true; } });
    asked = p.requests.at(-1);
    assert.deepEqual({ ...asked.data }, { q: '867TZB5309' });
    assert.ok(prevented);
});

check('the radio found: what the register says (as text), who has it and its condition chosen from it', () => {
    const p = open();
    checking(p);
    assert.equal(hidden(p, '#opnVerify'), false);
    assert.equal(p.node('#opnRadioTitle').text, 'Radio 1042');
    assert.equal(p.node('#opnRadioSub').text, 'MDC 412 · SN 867TZB5309');
    assert.equal(p.node('#opnRadioBadge').html, '<span class="rm-badge green">Ditemukan</span>');
    const facts = p.node('#opnRadioFacts').html;
    assertWellFormed(facts, 'the facts');
    assert.match(facts, /<div><dt>Di register<\/dt><dd><b>Pemakai &lt;b&gt;Lama&lt;\/b&gt;, Bagian Pabrik<\/b><\/dd><\/div>/);
    assert.match(facts, /<dt>Verifikasi terakhir<\/dt><dd>12 Agu 2026<\/dd>/);
    assert.equal(p.node('#opnHolderUser').text, 'Pemakai <b>Lama</b>');
    assert.equal(p.node('#opnHolderRegister').props.checked, true);
    assert.equal(hidden(p, '#opnHolderNameWrap'), true);
    assert.equal(p.node('#opnCond-perlu_service').props.checked, true, 'the condition of the register');
    assert.equal(p.node('#opnCond-layak').props.checked, false);
    assert.equal(p.node('#opnVerifyButton').props.disabled, false);
    assert.equal(hidden(p, '#opnReport'), false);
    assert.equal(hidden(p, '#opnArchived'), true);
    assert.equal(p.node('#opnTyped').value, '', 'the field is emptied for the next one');
});

check('a radio scanned before comes with what was saved; an archived one cannot be verified; one of another department says so', () => {
    const p = open();
    checking(p, card({ scanned: { holder: 'other', holder_name: 'Peminjam', condition: 'rusak', note: 'Antena retak', ticket: 'SRV-0001' } }));
    assert.equal(p.node('#opnRadioBadge').html, '<span class="rm-badge blue">Sudah discan</span>');
    assert.equal(p.node('#opnHolderOther').props.checked, true);
    assert.equal(hidden(p, '#opnHolderNameWrap'), false);
    assert.equal(p.node('#opnHolderName').value, 'Peminjam');
    assert.equal(p.node('#opnCond-rusak').props.checked, true);
    assert.equal(p.node('#opnNote').value, 'Antena retak');
    assert.equal(hidden(p, '#opnReport'), true, 'a ticket is there already');
    assert.equal(p.node('#opnTicket').text, 'Tiket servis SRV-0001 sudah dibuat.');

    const q = open();
    checking(q, card({ archived: true, dmr_id: null, mdc: null }));
    assert.equal(q.node('#opnRadioTitle').text, 'Radio 867TZB5309');
    assert.equal(q.node('#opnRadioBadge').html, '<span class="rm-badge gray">Diarsipkan</span>');
    assert.equal(hidden(q, '#opnArchived'), false);
    assert.equal(q.node('#opnVerifyButton').props.disabled, true);
    assert.equal(hidden(q, '#opnReport'), true);

    const r = open();
    checking(r, card({ here: false, condition: 'belum_dicek' }));
    assert.equal(r.node('#opnRadioBadge').html, '<span class="rm-badge orange">Department lain</span>');
    assert.equal(r.node('#opnCond-layak').props.checked, true, '"Belum dicek" is no condition a radio is found in');
});

check('verifying sends who has it and the condition; somebody else needs a name; then back to scanning, the count moved on', () => {
    const p = open();
    checking(p);
    p.node('(object)').value = 'other';
    p.fire('#opnVerify', 'change input[name="opnHolder"]');
    assert.equal(hidden(p, '#opnHolderNameWrap'), false);
    assert.ok(p.node('#opnHolderName').triggered.includes('focus'));
    const before = p.requests.length;
    p.node('#opnHolderName').value = '  ';
    p.fire('#opnVerifyButton', 'click');
    assert.equal(p.requests.length, before);
    assert.equal(p.dialogs.at(-1).options.titleText, 'Tulis nama yang memegang radionya.');

    p.node('#opnHolderName').value = ' Peminjam Sementara ';
    p.node('(object)').value = 'rusak';
    p.fire('#opnVerify', 'change input[name="opnCondition"]');
    p.node('#opnNote').value = ' Antena retak ';
    p.fire('#opnVerifyButton', 'click');
    const asked = p.requests.at(-1);
    assert.match(path(asked.url), /^\/radio-management\/opname\/7\/scan$/);
    assert.deepEqual({ ...asked.data }, { _method: 'PUT', radio_id: 31, holder: 'other', holder_name: 'Peminjam Sementara', condition: 'rusak', note: 'Antena retak' });
    assert.equal(p.node('#opnVerifyButton').props.disabled, true, 'busy');

    p.answer(asked, { message: 'Radio 1042 · MDC 412 terverifikasi.', data: state({ found: 25, scanned: 27 }) });
    assert.equal(p.dialogs.at(-1).options.titleText, 'Radio 1042 · MDC 412 terverifikasi.');
    assert.equal(hidden(p, '#opnScan'), false);
    assert.match(p.node('#opnProgress').html, /25 dari 38 terverifikasi<span>66%<\/span>/);
    assert.equal(p.page('opnamePage').radio, null);
});

check('a problem is reported with its note: a service ticket; refused, the reason shows as text', () => {
    const p = open();
    checking(p);
    const before = p.requests.length;
    p.node('#opnNote').value = '';
    p.fire('#opnReport', 'click');
    assert.equal(p.requests.length, before);
    assert.equal(p.dialogs.at(-1).options.titleText, 'Tulis masalahnya di catatan dulu.');

    p.node('#opnNote').value = 'Tombol <PTT> macet';
    p.fire('#opnReport', 'click');
    const asked = p.requests.at(-1);
    assert.match(path(asked.url), /^\/radio-management\/opname\/7\/report$/);
    assert.equal(asked.data.note, 'Tombol <PTT> macet');
    p.refuse(asked, { status: 422, responseJSON: { errors: ['Radio ini masih punya tiket servis yang terbuka: <b>SRV-0003</b>.'] } });
    assert.deepEqual([p.dialogs.at(-1).options.titleText, p.dialogs.at(-1).options.icon], ['Radio ini masih punya tiket servis yang terbuka: <b>SRV-0003</b>.', 'error']);
    assert.equal(hidden(p, '#opnVerify'), false, 'it stays on the radio');
});

check('the summary: the counts and what needs a decision, as text; sending asks first, then says what was done', () => {
    const p = open();
    scanning(p);
    p.fire('#opnToSummary', 'click');
    p.answer(p.requests.at(-1), { data: state() });
    assert.equal(hidden(p, '#opnSummary'), false);
    assert.equal(p.node('#opnSummaryKicker').text, 'Bagian <b>Pabrik</b> · 20 Okt 2026');
    assert.equal(p.node('#opnSums').html, '<div class="rm-phone-sum"><div class="rm-phone-sum-value is-green">26</div><div class="rm-phone-sum-label">Terverifikasi</div></div>' +
        '<div class="rm-phone-sum"><div class="rm-phone-sum-value is-red">1</div><div class="rm-phone-sum-label">Tidak ditemukan</div></div>' +
        '<div class="rm-phone-sum"><div class="rm-phone-sum-value is-orange">1</div><div class="rm-phone-sum-label">Pemegang lain</div></div>');
    const flags = p.node('#opnFlags').html;
    assertWellFormed(flags, 'the flags');
    assert.ok(!flags.includes('<b>'));
    assert.match(flags, /1047 · MDC 417<span class="rm-badge red">Tidak ditemukan<\/span><\/div><div class="rm-phone-flag-text">POS &lt;b&gt;LABOTA&lt;\/b&gt;, Security. Terakhir diverifikasi 02 Feb 2026. Tandai hilang/);
    assert.match(flags, /<span class="rm-badge orange">Pemegang lain<\/span><\/div><div class="rm-phone-flag-text">Register: LV 22. Dipegang Peminjam &lt;b&gt;Satu&lt;\/b&gt;. /);
    assert.match(flags, /<span class="rm-badge blue">Department lain<\/span><\/div><div class="rm-phone-flag-text">Terdaftar di Bagian Lab. Tetap diverifikasi.<\/div>/);
    assert.equal(p.node('#opnSubmit').props.disabled, false);

    p.fire('#opnSubmit', 'click');
    const dialog = p.dialogs.at(-1).options;
    assert.equal(dialog.title, 'Kirim ke register?');
    assert.equal(dialog.text, '26 radio diverifikasi hari ini. Sesi ini tidak bisa diubah lagi sesudahnya.');
    dialog.preConfirm();
    assert.match(path(p.requests.at(-1).url), /^\/radio-management\/opname\/7\/submit$/);
    assert.deepEqual({ ...p.requests.at(-1).data }, { _method: 'PUT' });

    p.resolve({ isConfirmed: true, value: { ok: true, response: {
        message: '25 radio terverifikasi di register.',
        data: state({ session: { id: 7, place: 'Bagian Pabrik', status: 'submitted', date: '2026-10-20' }, summary: { verified: 25, not_found: 1, other_user: 1, skipped: [{ radio: 'ID 9 · <b>x</b>', reason: 'sudah diarsipkan' }] } }),
    } } });
    assert.equal(hidden(p, '#opnDone'), false);
    assert.equal(p.node('#opnDoneText').text, '25 radio terverifikasi di register.');
    assert.equal(p.node('#opnSkipped').html, '<div class="rm-phone-flag-text">ID 9 · &lt;b&gt;x&lt;/b&gt;: sudah diarsipkan</div>');
    assert.equal(p.addresses().at(-1), '/radio-management/opname', 'a sent session leaves the address');

    p.fire('#opnAgain', 'click');
    assert.equal(hidden(p, '#opnStart'), false);
});

check('a link with a session goes on with it; a sent one shows its summary; one that is gone goes back to the start', () => {
    const p = open('http://localhost/radio-management/opname?session=7');
    assert.match(p.requests[0].url, /\/radio-management\/opname\/7$/);
    p.answer(p.requests[0], { data: state() });
    assert.equal(hidden(p, '#opnScan'), false);

    const q = open('http://localhost/radio-management/opname?session=7');
    q.answer(q.requests[0], { data: state({ session: { id: 7, place: 'Bagian Pabrik', status: 'submitted', date: '2026-10-19' }, summary: { verified: 24, not_found: 2, other_user: 0, skipped: [] } }) });
    assert.equal(hidden(q, '#opnSummary'), false);
    assert.equal(q.node('#opnFlags').html, '<div class="rm-phone-muted">Sesi ini sudah dikirim ke register.</div>');
    assert.equal(hidden(q, '#opnSubmit'), true);
    assert.match(q.node('#opnSums').html, /is-green">24<[\s\S]*is-red">2</);

    const r = open('http://localhost/radio-management/opname?session=99');
    r.refuse(r.requests[0], { status: 404, responseJSON: {} });
    assert.equal(r.dialogs.at(-1).options.titleText, 'Sesi itu tidak ditemukan.');
    assert.equal(hidden(r, '#opnStart'), false);
});

check('who may not scan cannot verify or send; how long ago reads short', () => {
    const p = open();
    p.page('opnamePage').may = { start: false, scan: false, report: false };
    checking(p);
    assert.equal(p.node('#opnVerifyButton').props.disabled, true);
    assert.equal(hidden(p, '#opnReport'), true);
    p.page('opnamePage').drawSummary();
    assert.equal(hidden(p, '#opnSubmit'), true);

    const ago = p.page('opnamePage').ago;
    const stamp = minutes => {
        const d = new Date(Date.now() - minutes * 60000);
        const two = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${two(d.getMonth() + 1)}-${two(d.getDate())} ${two(d.getHours())}:${two(d.getMinutes())}:${two(d.getSeconds())}`;
    };
    assert.equal(ago(stamp(0)), 'baru saja');
    assert.equal(ago(stamp(2)), '2 mnt');
    assert.equal(ago(stamp(90)), stamp(90).slice(11, 16));
    assert.equal(ago(''), '');
    assert.ok(textOf('<b>x</b>') === 'x');
});

done();
