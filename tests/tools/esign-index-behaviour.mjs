// Runs the inline JavaScript of the page "E-Sign Master" (esignUi / esignTable / esignForm) without a browser and checks
// what it does: what a row shows (a picture only when it is one the server keeps), what registering and changing a signature
// send, that the signature is read out of the pad's frame and never sent while the pad is still open, and what the person is told.
//
// usage: node esign-index-behaviour.mjs <file with the rendered page>
//
// The page's script and the global helpers it uses are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from ESignTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const RadioUi = {', 'const esignUrls = {'];
const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
const { check, done } = checker();

/** The page, with the frame of the pad findable by its id (the sandbox keeps what a script appended; the page asks the document for it). */
const open = () => {
    const p = loadPage(process.argv[2], markers, { href: 'http://localhost/radio-management/e-sign' });
    const document = p.page('document');
    const original = document.getElementById;
    document.getElementById = id => (id === 'esignPadFrame' ? (p.inside('esignPadHost').at(-1) ?? null) : original(id));

    return p;
};
const meta = total => ({ current_page: 1, from: total ? 1 : null, last_page: 1, per_page: 25, to: total || null, total });
const row = (id, other = {}) => ({
    id, user_id: 100 + id, account: `Teknisi ${id}`, email: `teknisi${id}@test.local`, account_gone: false, display_name: `Teknisi ${id}`,
    active: true, image: PNG, uses: 0, last_used: null, updated_at: '12-10-2026 09:00', ...other,
});
const candidates = [{ id: 201, name: 'Budi <b>', email: 'budi@test.local' }, { id: 202, name: 'Citra', email: 'citra@test.local' }];
const loaded = (rows = [row(1), row(2)], users = candidates) => {
    const p = open();
    p.answer(p.requests[0], { data: rows, meta: meta(rows.length), candidates: users });

    return p;
};
const body = p => p.node('#esignTable tbody').html;
/** What the pad in the frame holds: a picture that was taken, and whether the pad is still open. */
const pad = (p, { image = null, open: isOpen = false } = {}) => {
    const frame = p.inside('esignPadHost').at(-1);
    frame.contentDocument = {
        getElementById: id => (id === 'signOverlay' ? { hidden: !isOpen } : null),
        querySelector: selector => (selector === '.sig-img' && image !== null ? { getAttribute: name => (name === 'src' ? image : null) } : null),
    };

    return frame;
};
const press = (p, action, id) => {
    Object.assign(p.node('(object)').attrs, { 'data-action': action, 'data-id': String(id) });

    return p.fire('#esignTable', 'click button[data-action]', {});
};
const plain = value => JSON.parse(JSON.stringify(value));

check('the page asks for the signatures once, and draws who each belongs to', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.match(p.requests[0].url, /\/radio-management\/e-sign\/list$/);
    assert.deepEqual(plain(p.sent()), { page: 1, paginated: 25, search: '' });

    p.answer(p.requests[0], {
        data: [
            row(1, { uses: 3, last_used: '12-10-2026 09:30' }),
            row(2, { active: false }),
            row(3, { account_gone: true }),
        ],
        meta: meta(3),
        candidates,
    });
    const rows = body(p);
    assertWellFormed(rows, 'the rows');
    const [first, off, gone] = rows.split('</tr>');
    assert.match(first, /<div>Teknisi 1<\/div><div class="radio-muted">teknisi1@test\.local<\/div>/);
    assert.match(first, /<span class="esign-preview"><img src="data:image\/png;base64,[A-Za-z0-9+\/=]+" alt="Tanda tangan Teknisi 1"><\/span>/);
    assert.match(first, /radio-badge">Aktif<\/span>/);
    assert.match(first, /<div>3 kali<\/div><div class="radio-muted">terakhir 12-10-2026 09:30<\/div>/);
    for (const action of ['edit', 'uses', 'delete']) assert.match(first, new RegExp(`data-action="${action}" data-id="1"`));
    assert.match(off, /radio-badge">Nonaktif<\/span>/);
    assert.match(off, /<div>0 kali<\/div>/);
    // An account that was removed cannot sign in: its signature is shown as switched off, whatever its switch says.
    assert.match(gone, /Akun dihapus/);
    assert.match(gone, /radio-badge">Nonaktif<\/span>/);
});

check('what comes from the database is text, and a picture is shown only when it is a PNG inside the page', () => {
    const p = loaded([
        row(1, { account: '<img src=x onerror=alert(1)>', email: '"><script>alert(2)</script>', display_name: '<u>Nama</u>', last_used: '<s>x</s>', uses: '<b>9</b>', id: 1 }),
        row(2, { image: 'https://evil.example/x.png' }),
        row(3, { image: 'javascript:alert(3)' }),
        row(4, { image: 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=' }),
        row(5, { image: `${PNG}" onerror="alert(4)` }),
        row(6, { image: null }),
    ]);
    const out = p.written();
    for (const raw of ['<img src=x', '<script', '<u>', '<s>', '<b>9', 'evil.example', 'javascript:', 'svg+xml', 'onerror="alert']) {
        assert.equal(out.includes(raw), false, `unescaped: ${raw}`);
    }
    assert.equal((body(p).match(/<img /g) || []).length, 1, 'only the first row has a picture');
    assert.match(body(p), /<div>NaN kali<\/div>|<div>0 kali<\/div>/);
    assertWellFormed(body(p), 'the rows');
    const ui = p.page('esignUi');
    assert.equal(ui.isPicture(PNG), true);
    for (const odd of ['', 'data:image/png;base64,', 'data:image/png;base64,AAAA BBBB', ' ' + PNG, PNG + '\n', '/assets/x.png', null, undefined, 5]) {
        assert.equal(ui.isPicture(odd), false, String(odd));
    }
});

check('registering opens the dialog with the accounts that have no signature yet, and a pad of its own', () => {
    const p = loaded();
    p.page('esignForm').open();
    assert.equal(p.node('#esignModal').modal, 'show');
    assert.equal(p.node('#esignTitle').text, 'Daftarkan tanda tangan');
    const options = p.node('#esign_user').html;
    assert.equal(options, '<option value="">Pilih akun…</option><option value="201">Budi &lt;b&gt; · budi@test.local</option><option value="202">Citra · citra@test.local</option>');
    assert.equal(p.node('#esign_user').props.disabled, false);
    assert.equal(p.node('#esign_name').value, '');
    // What is only for changing a signature is not shown.
    assert.equal(p.node('#esignActiveWrap').classes.has('d-none'), true);
    assert.equal(p.node('#esignCurrentWrap').classes.has('d-none'), true);
    assert.equal(p.node('#esignPadLabel').text, 'Tanda tangan');
    assert.equal(p.node('#esignError').classes.has('d-none'), true);
    // The pad is the page of the pad, in a frame made for this opening.
    const frames = p.inside('esignPadHost');
    assert.equal(frames.length, 1);
    assert.equal(frames[0].tag, 'iframe');
    assert.match(frames[0].src, /\/radio-management\/e-sign\/pad$/);

    // Opened again: the frame of before is gone, with whatever was signed in it.
    p.page('esignForm').open();
    assert.equal(p.inside('esignPadHost').length, 1);
    assert.notEqual(p.inside('esignPadHost')[0], frames[0]);
    // And it goes with the dialog.
    p.fire('#esignModal', 'hidden.bs.modal', {});
    assert.equal(p.inside('esignPadHost').length, 0);
});

check('a signature is sent only when it was taken from the pad, for an account that was chosen', () => {
    const p = loaded();
    const form = p.page('esignForm');
    form.open();
    const before = p.requests.length;

    // Nobody chosen; nothing signed; ink on the pad that was not taken yet: each is said, and nothing is sent.
    pad(p, { image: PNG });
    form.submit();
    assert.equal(p.node('#esignError').text, 'Pilih akun pemilik tanda tangan.');
    p.node('#esign_user').value = '202';
    pad(p, { image: null });
    form.submit();
    assert.equal(p.node('#esignError').text, 'Tanda tangan belum ada. Klik kotaknya untuk tanda tangan di layar, atau unggah file gambarnya.');
    pad(p, { image: PNG, open: true });
    form.submit();
    assert.match(p.node('#esignError').text, /belum dipakai/);
    assert.equal(p.node('#esignError').classes.has('d-none'), false);
    assert.equal(p.requests.length, before);
    // A frame that cannot be read (it is on a sign-in page of another address, say) holds no signature.
    Object.defineProperty(p.inside('esignPadHost').at(-1), 'contentDocument', { configurable: true, get() { throw new Error('Blocked'); } });
    form.submit();
    assert.equal(p.requests.length, before);

    // Readable again, with a signature that was taken.
    Object.defineProperty(p.inside('esignPadHost').at(-1), 'contentDocument', { configurable: true, writable: true, value: null });
    pad(p, { image: PNG });
    p.node('#esign_name').value = 'Citra (ICT)';
    form.submit();
    assert.equal(p.requests.length, before + 1);
    const request = p.requests.at(-1);
    assert.match(request.url, /\/radio-management\/e-sign$/);
    assert.equal(request.type, 'POST');
    assert.deepEqual(plain(p.sent()), { display_name: 'Citra (ICT)', image: PNG, user_id: '202' });
    assert.equal(p.node('#esignError').classes.has('d-none'), true);
    assert.equal(p.node('#esignSave').props.disabled, true);
    form.submit();
    assert.equal(p.requests.length, before + 1, 'a second click while it waits sends nothing');

    p.answer(request, { message: 'Tanda tangan <Citra> berhasil didaftarkan.' });
    assert.equal(p.node('#esignModal').modal, 'hide');
    assert.deepEqual([...p.dialogs.at(-1).args], ['Berhasil', 'Tanda tangan &lt;Citra&gt; berhasil didaftarkan.', 'success']);
    assert.match(p.requests.at(-1).url, /\/list$/);
    assert.equal(p.node('#esignSave').props.disabled, false);
});

check('changing a signature keeps its owner, and sends a new picture only when one was drawn', () => {
    const p = loaded([row(7, { display_name: 'Pak Tujuh', active: false, account: 'Teknisi <7>' })]);
    press(p, 'edit', 7);
    assert.equal(p.node('#esignTitle').text, 'Edit tanda tangan');
    assert.equal(p.node('#esign_user').html, '<option value="107">Teknisi &lt;7&gt; · teknisi7@test.local</option>');
    assert.equal(p.node('#esign_user').props.disabled, true);
    assert.equal(p.node('#esign_name').value, 'Pak Tujuh');
    assert.equal(p.node('#esign_active').props.checked, false);
    assert.equal(p.node('#esignActiveWrap').classes.has('d-none'), false);
    assert.equal(p.node('#esignCurrentWrap').classes.has('d-none'), false);
    assert.match(p.node('#esignCurrent').html, /^<img src="data:image\/png;base64,[^"]+" alt="Tanda tangan sekarang">$/);
    assert.match(p.node('#esignPadLabel').text, /biarkan kosong/);

    // Only the name and the switch.
    pad(p, { image: null });
    p.node('#esign_name').value = 'Pak Tujuh (ICT)';
    p.node('#esign_active').props.checked = true;
    p.page('esignForm').submit();
    let request = p.requests.at(-1);
    assert.match(request.url, /\/radio-management\/e-sign\/7$/);
    assert.deepEqual(plain(p.sent()), { display_name: 'Pak Tujuh (ICT)', _method: 'PUT', active: 1 });
    p.answer(request, { message: 'ok' });

    // With a new signature, and switched off.
    p.answer(p.requests.at(-1), { data: [row(7)], meta: meta(1), candidates: [] });
    press(p, 'edit', 7);
    pad(p, { image: PNG });
    p.node('#esign_active').props.checked = false;
    p.page('esignForm').submit();
    assert.deepEqual(plain(p.sent()), { display_name: 'Teknisi 7', image: PNG, _method: 'PUT', active: 0 });
});

check('a refusal is said in the dialog, as text, and the dialog stays open', () => {
    const p = loaded();
    const form = p.page('esignForm');
    form.open();
    p.node('#esign_user').value = '201';
    pad(p, { image: PNG });
    form.submit();
    p.refuse(p.requests.at(-1), { status: 422, responseJSON: { message: 'Input tidak valid', errors: ['Akun itu sudah punya tanda tangan.', '<b>dua</b>'] } });
    assert.equal(p.node('#esignError').wrote, 'text');
    assert.equal(p.node('#esignError').text, 'Akun itu sudah punya tanda tangan. <b>dua</b>');
    assert.notEqual(p.node('#esignModal').modal, 'hide');
    assert.equal(p.node('#esignSave').props.disabled, false);
    assert.equal(p.dialogs.length, 0);

    assert.deepEqual(plain(form.messages({ status: 419 })), ['Sesi Anda sudah berakhir. Muat ulang halaman untuk masuk lagi.']);
    assert.deepEqual(plain(form.messages({ status: 403 })), ['Anda tidak memiliki izin untuk aksi ini.']);
    assert.deepEqual(plain(form.messages({ status: 503, responseJSON: { message: 'Tabel belum dibuat.' } })), ['Tabel belum dibuat.']);
    assert.deepEqual(plain(form.messages({ status: 422, responseJSON: { errors: { image: ['Tanda tangan masih kosong.'] } } })), ['Tanda tangan masih kosong.']);
    assert.deepEqual(plain(form.messages({ status: 500 })), ['Terjadi kesalahan, coba lagi.']);

    // A list that cannot be loaded says why.
    const failed = open();
    failed.refuse(failed.requests[0], { status: 503, responseJSON: { message: 'Tabel tanda tangan belum dibuat.' } });
    assert.match(failed.node('#esignNotice').html, /alert-danger[^>]*>Tabel tanda tangan belum dibuat\./);
    assert.match(body(failed), /Gagal memuat data\./);
});

check('removing asks first, and says that the record of where it was put stays', () => {
    const p = loaded([row(7, { display_name: '<i>Pak</i>' })]);
    const before = p.requests.length;
    press(p, 'delete', 7);
    assert.equal(p.dialogs.length, 1);
    assert.equal(p.dialogs[0].options.title, 'Hapus tanda tangan ini?');
    assert.match(p.dialogs[0].options.html, /^&lt;i&gt;Pak&lt;\/i&gt;<br>.*Riwayat pemakaiannya tetap tersimpan\./);
    assert.equal(p.requests.length, before);
    p.resolve({ isConfirmed: false });
    assert.equal(p.requests.length, before);

    press(p, 'delete', 7);
    p.resolve({ isConfirmed: true });
    const request = p.requests.at(-1);
    assert.match(request.url, /\/radio-management\/e-sign\/7$/);
    assert.deepEqual(plain(p.sent()), { _method: 'DELETE' });
    p.answer(request, { message: 'Tanda tangan Pak dihapus.' });
    assert.deepEqual([...p.dialogs.at(-1).args], ['Terhapus', 'Tanda tangan Pak dihapus.', 'success']);
    assert.match(p.requests.at(-1).url, /\/list$/);

    // A button of a row that is no longer on screen does nothing.
    press(p, 'delete', 99);
    press(p, 'edit', 99);
    assert.match(p.requests.at(-1).url, /\/list$/);
});

check('the record of a signature is shown: when, for which radio, by whom', () => {
    const p = loaded([row(7)]);
    press(p, 'uses', 7);
    assert.equal(p.node('#esignUsesModal').modal, 'show');
    assert.equal(p.node('#esignUsesLabel').text, 'Teknisi 7');
    assert.match(p.requests.at(-1).url, /\/radio-management\/e-sign\/7\/uses$/);
    p.answer(p.requests.at(-1), { data: [{ at: '12-10-2026 09:30', user: 'Teknisi 7', radio: 'ID 11 · SN <x> · Budi' }], total: 140 });
    const list = p.node('#esignUsesList').html;
    assertWellFormed(list, 'the record');
    assert.match(list, /<td class="text-nowrap">12-10-2026 09:30<\/td><td>ID 11 · SN &lt;x&gt; · Budi<\/td><td>Teknisi 7<\/td>/);
    assert.match(list, /140 kali dipakai, ditampilkan 1 terakhir\./);

    press(p, 'uses', 7);
    p.answer(p.requests.at(-1), { data: [], total: 0 });
    assert.match(p.node('#esignUsesList').html, /Belum pernah dipakai di form\./);
    press(p, 'uses', 7);
    p.refuse(p.requests.at(-1), { status: 403 });
    assert.match(p.node('#esignUsesList').html, /alert-danger[^>]*>Anda tidak memiliki izin untuk aksi ini\./);
});

check('a search starts from the first page, and every element the script reaches for by id is in the page', () => {
    const p = loaded();
    p.page('esignTable').page = 3;
    p.node('#esignSearch').value = '  budi  ';
    p.fire('#esignSearchButton', 'click', {});
    assert.deepEqual(plain(p.sent()), { page: 1, paginated: 25, search: 'budi' });
    p.fire('#esignSearch', 'keydown', { key: 'Enter' });
    assert.deepEqual(p.node('#esignSearchButton').triggered, ['click']);

    p.page('esignForm').open();
    press(p, 'uses', 1);
    const used = p.ids();
    assert.ok(used.size >= 15, `only ${used.size} ids seen`);
    for (const id of used) {
        // The frame of the pad is made by the script itself.
        if (id === 'esignPadFrame') continue;
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
    assert.ok(/class="[^"]*\bpagination-container\b/.test(p.html), 'the page has no .pagination-container');
    // Seven columns in the head, seven drawn.
    assert.equal((p.html.match(/<table[^>]*id="esignTable"[\s\S]*?<\/thead>/)[0].match(/<th /g) || []).length, [...p.page('esignTable').columns].length);
});

done();
