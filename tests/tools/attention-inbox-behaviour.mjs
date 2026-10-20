// Runs the script of "Perlu Perhatian" (attnPage) without a browser and checks what it does: the checks with their
// counts, the items of the one chosen, the item at the right (its radios side by side, the fixes), and what is sent when
// an item is fixed, set aside or given to somebody. Everything from the server is shown as text.
//
// usage: node attention-inbox-behaviour.mjs <file with the rendered page, for Super Admin>
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from AttentionInboxTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const rmPickers = {', 'const RadioUi = {', 'const attnPage = {'];
const open = (href = 'http://localhost/radio-management/attention') => {
    const p = loadPage(process.argv[2], markers, { href });
    p.page('attnPage').me = 5;
    p.page('attnPage').people = [{ id: 5, name: 'Admin <i>Contoh</i>' }, { id: 6, name: 'Petugas Dua' }];
    return p;
};
const { check, done } = checker();
/** An address without its scheme and host (the page is rendered for APP_URL). */
const path = url => String(url).replace(/^https?:\/\/[^/]+/, '');

const item = (subject, other = {}) => ({
    check: 'needs_review', subject, head: `ID ${subject} · 867TZB${subject}`, side: 'XiR P8668i', who: 'Pemakai <b>Satu</b>',
    assignee: null, first_seen: '2026-10-12', ...other,
});
const counts = { needs_review: 2, sn_duplicate: 1, id_duplicate: 0, verify_stale: 0 };
const listed = (p, body = {}) => {
    const asked = p.requests.filter(request => /\/attention\/list$/.test(request.url)).at(-1);
    assert.ok(asked, 'the list was asked for');
    p.answer(asked, { check: 'needs_review', counts, total: 2, data: [item('2001'), item('2002', { assignee: { id: 6, name: 'Petugas Dua' } })], more: false, page: 1, ready: true, ...body });
    return asked;
};
const detail = (other = {}) => ({
    check: 'sn_duplicate', subject: '867TZB5309', head: '867TZB5309', side: 'Radio 2001 dan 2002', who: 'Pemakai Satu dan Pemakai Dua',
    assignee: { id: 5, name: 'Admin <i>Contoh</i>' }, first_seen: '2026-10-12',
    label: 'SN dobel', color: 'red', title: 'SN 867TZB5309 dipakai <b>2</b> radio', note: null, dismissed: null,
    records: [
        { id: 1, head: 'Radio 2001', tag: 'Dibuat 14-03-2025', suspect: false, rows: [
            { label: 'SN', value: '867TZB5309', mono: true, marked: false },
            { label: 'User/Unit', value: 'Pemakai <b>Satu</b>', mono: false, marked: true },
        ] },
        { id: 2, head: 'Radio 2002', tag: 'Dibuat 10-10-2026', suspect: true, rows: [
            { label: 'SN', value: '867TZB5309', mono: true, marked: false },
            { label: 'User/Unit', value: 'Pemakai Dua', mono: false, marked: true },
        ] },
    ],
    options: [
        { id: 'sn:2', title: 'Betulkan SN radio 2002', detail: 'Cek stiker di radionya.', field: { kind: 'text', mono: true, value: '"x"', placeholder: '867TZB5309', max: 50 } },
        { id: 'archive:2', title: 'Arsipkan radio 2002 sebagai duplikat', detail: 'Riwayatnya tetap ada.' },
        { id: 'type:2', title: 'Pilih tipe radio', detail: '', field: { kind: 'select', value: '', choices: [{ value: '7', label: 'XiR <P8668i>' }] } },
        { id: 'dismiss', title: 'Radio-radio ini memang berbeda', detail: 'Abaikan cek ini, dengan alasannya.' },
    ],
    ...other,
});
/** Answers the latest request for an item. */
const shown = (p, body = detail()) => {
    const asked = p.requests.filter(request => /\/attention\/item$/.test(request.url)).at(-1);
    assert.ok(asked, 'an item was asked for');
    p.answer(asked, { data: body });
    return asked;
};
const panel = p => p.node('#attnDetail').html;

check('the checks with their counts, the first one open; its items as text; the first item opens; the address names them', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.deepEqual({ ...p.requests[0].data }, { check: 'needs_review', who: 'all', page: 1 });
    assert.equal(p.node('#attnRefresh').props.disabled, true, 'busy while it is read');
    listed(p);
    assert.equal(p.node('#attnRefresh').props.disabled, false);

    const checks = p.node('#attnChecks').html;
    assertWellFormed(checks, 'the checks');
    assert.equal((checks.match(/data-check=/g) || []).length, 12);
    assert.match(checks, /^<button type="button" class="rm-inbox-check is-on" data-check="needs_review" aria-current="true"><span class="rm-inbox-dot is-red" aria-hidden="true"><\/span><span class="rm-inbox-check-label">Perlu dicek<\/span><span class="rm-inbox-check-count">2<\/span><\/button>/);
    assert.match(checks, /class="rm-inbox-check is-none" data-check="verify_stale"><span class="rm-inbox-dot is-gray"/);
    assert.match(checks, /data-check="reprogram"><span class="rm-inbox-dot is-yellow"/);
    assert.equal(p.node('#attnChecksHead').text, 'Cek · 3 terbuka');
    assert.equal(p.node('#attnItemsTitle').text, 'Perlu dicek');
    assert.equal(p.node('#attnItemsCount').html, '<span class="rm-badge red">2 item</span>');

    const items = p.node('#attnItems').html;
    assertWellFormed(items, 'the items');
    assert.ok(items.includes('Pemakai &lt;b&gt;Satu&lt;/b&gt;') && !items.includes('<b>Satu'));
    assert.match(items, /^<button type="button" class="rm-inbox-item is-on" data-subject="2001" aria-current="true">/);
    assert.match(items, /data-subject="2001"[\s\S]*?<span class="rm-inbox-item-foot">Belum ditugaskan<\/span>/);
    assert.match(items, /data-subject="2002"[\s\S]*?<span class="rm-inbox-av" aria-hidden="true">PD<\/span>Petugas Dua/);
    assert.equal(p.node('#attnMore').classes.has('d-none'), true);

    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/attention\/item$/);
    assert.deepEqual({ ...asked.data }, { check: 'needs_review', subject: '2001' });
    assert.equal(p.addresses().at(-1), '/radio-management/attention?check=needs_review&item=2001');
});

check('the item at the right: what is wrong, its radios side by side with the marked rows, the fixes, who has it', () => {
    const p = open();
    listed(p);
    shown(p);
    const html = panel(p);
    assertWellFormed(html, 'the item');
    assert.match(html, /<h2 class="rm-inbox-detail-title">SN 867TZB5309 dipakai &lt;b&gt;2&lt;\/b&gt; radio<\/h2><div class="rm-inbox-detail-sub">Terlihat pertama 12 Okt 2026<\/div><\/div><span class="rm-badge red">SN dobel<\/span>/);
    assert.match(html, /<div class="rm-inbox-records"><div class="rm-inbox-record"><div class="rm-inbox-record-head">Radio 2001<span class="rm-inbox-record-tag">Dibuat 14-03-2025<\/span>/);
    assert.match(html, /<div class="rm-inbox-record is-suspect"><div class="rm-inbox-record-head">Radio 2002/);
    assert.match(html, /<div class="rm-inbox-row is-marked"><div class="rm-inbox-row-label">User\/Unit<\/div><div class="rm-inbox-row-value">Pemakai &lt;b&gt;Satu&lt;\/b&gt;<\/div><\/div>/);
    assert.match(html, /<div class="rm-inbox-row"><div class="rm-inbox-row-label">SN<\/div><div class="rm-inbox-row-value is-mono">867TZB5309<\/div>/);
    // The first fix is chosen, with its field (the value as text); the others without.
    assert.match(html, /<label class="rm-inbox-fix is-on"><input type="radio" name="attnFix" id="attnFix0" value="sn:2" checked>/);
    assert.match(html, /<input type="text" class="form-control radio-mono" id="attnField" value="&quot;x&quot;" placeholder="867TZB5309" maxlength="50"/);
    assert.equal((html.match(/id="attnField"/g) || []).length, 1);
    // Who has it: the person chosen, the account itself named.
    assert.match(html, /<select class="form-select" id="attnAssign"><option value="">Belum ditugaskan<\/option><option value="5" selected>Admin &lt;i&gt;Contoh&lt;\/i&gt; \(saya\)<\/option><option value="6">Petugas Dua<\/option><\/select>/);
    assert.match(html, /<button type="button" class="btn-rm primary" id="attnApply"><i class="ti ti-check" aria-hidden="true"><\/i> Terapkan<\/button>/);
});

check('another fix chosen shows its own field; "memang berbeda" turns the button into Abaikan', () => {
    const p = open();
    listed(p);
    shown(p);
    p.node('(object)').value = 'type:2';
    p.fire('#attnDetail', 'change input[name="attnFix"]');
    assert.match(panel(p), /<label class="rm-inbox-fix is-on"><input type="radio" name="attnFix" id="attnFix2" value="type:2" checked>/);
    assert.match(panel(p), /<select class="form-select" id="attnField" aria-label="Pilih tipe radio"><option value="">Pilih…<\/option><option value="7">XiR &lt;P8668i&gt;<\/option><\/select>/);
    assert.ok(p.node('#attnField').triggered.includes('focus'));

    p.node('(object)').value = 'dismiss';
    p.fire('#attnDetail', 'change input[name="attnFix"]');
    assert.doesNotMatch(panel(p), /id="attnField"/);
    assert.match(panel(p), /id="attnApply"><i class="ti ti-check" aria-hidden="true"><\/i> Abaikan<\/button>/);
});

check('applying a fix sends the item, the fix and what was typed; without the field nothing goes; refused, the reason shows as text', () => {
    const p = open();
    listed(p);
    shown(p);
    const before = p.requests.length;
    p.node('#attnField').value = '   ';
    p.fire('#attnDetail', 'click #attnApply');
    assert.equal(p.requests.length, before, 'an empty field is not sent');
    assert.equal(p.dialogs.at(-1).options.titleText, 'Isi dulu kolomnya.');

    p.node('#attnField').value = ' 867tzb5311 ';
    p.fire('#attnDetail', 'click #attnApply');
    const asked = p.requests.at(-1);
    assert.equal(asked.type, 'POST');
    assert.match(asked.url, /\/radio-management\/attention\/fix$/);
    assert.deepEqual({ ...asked.data }, { _method: 'PUT', check: 'sn_duplicate', subject: '867TZB5309', option: 'sn:2', value: '867tzb5311' });
    assert.equal(p.node('#attnApply').props.disabled, true, 'busy');

    p.refuse(asked, { status: 422, responseJSON: { errors: ['SN <b>867TZB5311</b> sudah terdaftar.'] } });
    assert.deepEqual(p.dialogs.at(-1).args, ['Tidak diterapkan', 'SN &lt;b&gt;867TZB5311&lt;/b&gt; sudah terdaftar.', 'error']);
    assert.equal(p.node('#attnApply').props.disabled, false);

    p.fire('#attnDetail', 'click #attnApply');
    p.answer(p.requests.at(-1), { message: 'SN radio 2002 sekarang 867TZB5311.' });
    assert.equal(p.dialogs.at(-1).options.titleText, 'SN radio 2002 sekarang 867TZB5311.');
    assert.equal(p.page('attnPage').subject, null, 'the next item opens');
    assert.deepEqual({ ...p.requests.at(-1).data }, { check: 'needs_review', who: 'all', page: 1 });
});

check('setting an item aside asks for the reason, sends it, and reads the list again', () => {
    const p = open();
    listed(p);
    shown(p);
    p.fire('#attnDetail', 'click #attnDismiss');
    const dialog = p.dialogs.at(-1).options;
    assert.equal(dialog.title, 'Abaikan item ini?');
    assert.equal(dialog.input, 'textarea');
    assert.equal(dialog.inputValidator('  '), 'Tulis alasannya.');
    assert.equal(dialog.inputValidator('Cadangan'), undefined);

    dialog.preConfirm(' Radio cadangan ');
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/attention\/dismiss$/);
    assert.deepEqual({ ...asked.data }, { _method: 'PUT', check: 'sn_duplicate', subject: '867TZB5309', reason: 'Radio cadangan' });

    p.resolve({ isConfirmed: true, value: { ok: true, response: { message: 'Item diabaikan. Muncul lagi bila data radionya berubah.' } } });
    assert.equal(p.dialogs.at(-1).options.titleText, 'Item diabaikan. Muncul lagi bila data radionya berubah.');
    assert.match(p.requests.at(-1).url, /\/attention\/list$/);

    // "Memang berbeda" chosen, the main button does the same.
    p.page('attnPage').choice = 'dismiss';
    p.fire('#attnDetail', 'click #attnApply');
    assert.equal(p.dialogs.at(-1).options.title, 'Abaikan item ini?');
});

check('the item is given to somebody, or to nobody', () => {
    const p = open();
    listed(p);
    shown(p);
    p.node('#attnAssign').value = '6';
    p.fire('#attnDetail', 'change #attnAssign');
    let asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/attention\/assign$/);
    assert.deepEqual({ ...asked.data }, { _method: 'PUT', check: 'sn_duplicate', subject: '867TZB5309', user_id: 6 });
    p.answer(asked, { message: 'Item ditugaskan ke Petugas Dua.' });
    assert.equal(p.dialogs.at(-1).options.titleText, 'Item ditugaskan ke Petugas Dua.');

    p.node('#attnAssign').value = '';
    p.fire('#attnDetail', 'change #attnAssign');
    asked = p.requests.at(-1);
    assert.equal(asked.data.user_id, '');
    p.refuse(asked, { status: 422, responseJSON: { errors: ['Item hanya bisa ditugaskan ke akun aktif yang boleh mengubah data radio.'] } });
    assert.deepEqual([p.dialogs.at(-1).options.titleText, p.dialogs.at(-1).options.icon], ['Item hanya bisa ditugaskan ke akun aktif yang boleh mengubah data radio.', 'error']);
});

check('another check, the filter of who has it, and "Tampilkan lagi"', () => {
    const p = open();
    listed(p);
    p.node('(object)').data = { check: 'sn_duplicate' };
    p.fire('#attnChecks', 'click [data-check]');
    assert.deepEqual({ ...p.requests.at(-1).data }, { check: 'sn_duplicate', who: 'all', page: 1 });

    p.node('#attnWho').value = 'mine';
    p.fire('#attnWho', 'change');
    assert.deepEqual({ ...p.requests.at(-1).data }, { check: 'sn_duplicate', who: 'mine', page: 1 });
    listed(p, { check: 'sn_duplicate', total: 3, more: true });
    assert.equal(p.addresses().at(-1), '/radio-management/attention?check=sn_duplicate&item=2001&who=mine');
    assert.equal(p.node('#attnMore').classes.has('d-none'), false);

    p.fire('#attnMore', 'click');
    assert.deepEqual({ ...p.requests.at(-1).data }, { check: 'sn_duplicate', who: 'mine', page: 2 });
    p.answer(p.requests.at(-1), { counts, total: 3, data: [item('2003')], more: false, page: 2 });
    assert.equal((p.node('#attnItems').html.match(/data-subject=/g) || []).length, 3, 'added to the list');
    assert.equal(p.node('#attnMore').classes.has('d-none'), true);
});

check('a check without items says so, at the right too; an item that is gone says so', () => {
    const p = open();
    listed(p, { counts: {}, total: 0, data: [] });
    assert.match(p.node('#attnItems').html, /<div class="rm-inbox-none">Tidak ada item terbuka di cek ini.<\/div>/);
    assert.equal(panel(p), '<div class="rm-inbox-empty">Tidak ada item di cek ini.</div>');
    assert.equal(p.addresses().at(-1), '/radio-management/attention?check=needs_review');

    const q = open();
    listed(q);
    q.refuse(q.requests.at(-1), { status: 404, responseJSON: { message: 'x' } });
    assert.equal(panel(q), '<div class="rm-inbox-empty">Item ini sudah tidak ada: datanya sudah dibetulkan atau berubah.</div>');
});

check('a link opens its check, its filter and its item', () => {
    const p = open('http://localhost/radio-management/attention?check=sn_duplicate&item=867TZB5309&who=none');
    assert.deepEqual({ ...p.requests[0].data }, { check: 'sn_duplicate', who: 'none', page: 1 });
    assert.equal(p.node('#attnWho').value, 'none');
    listed(p, { check: 'sn_duplicate', data: [item('867TZB0001'), item('867TZB5309')] });
    assert.deepEqual({ ...p.requests.at(-1).data }, { check: 'sn_duplicate', subject: '867TZB5309' });
});

check('"Jalankan cek sekarang" reads everything again and says so', () => {
    const p = open();
    listed(p);
    p.fire('#attnRefresh', 'click');
    assert.equal(p.node('#attnRefresh').props.disabled, true);
    listed(p);
    assert.equal(p.dialogs.at(-1).options.titleText, 'Cek sudah dijalankan ulang.');
});

check('who may only read sees who has it, without the fixes or the buttons; who may not fix can still set aside', () => {
    const p = open();
    p.page('attnPage').may = { update: false, fix: false };
    listed(p);
    shown(p);
    const html = panel(p);
    assert.doesNotMatch(html, /attnApply|attnDismiss|attnAssign|name="attnFix"/);
    assert.match(html, /<div class="rm-inbox-detail-foot">Ditugaskan ke <span class="rm-inbox-holder">Admin &lt;i&gt;Contoh&lt;\/i&gt;<\/span><\/div>/);

    const q = open();
    q.page('attnPage').may = { update: true, fix: false };
    listed(q);
    shown(q);
    assert.match(panel(q), /id="attnApply" disabled title="Memperbaiki data radio butuh izin Update di Register Radio">/);
    q.node('(object)').value = 'dismiss';
    q.fire('#attnDetail', 'change input[name="attnFix"]');
    assert.match(panel(q), /id="attnApply" title="Memperbaiki data radio butuh izin Update di Register Radio">/, 'setting aside needs no fix permission');
    assert.ok(textOf(panel(q)).includes('Abaikan'));
});

done();
