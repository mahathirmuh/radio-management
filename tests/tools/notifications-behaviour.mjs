// Runs the inline JavaScript of the bell (main.navbar.notifications) and of the page "Notifikasi" without a browser, and
// checks what they do: the count on the bell, the rows (the colour of the kind, a dot while unread, the texts as text),
// "Tandai semua dibaca" in the bell, and on the page without leaving it.
//
// usage: node notifications-behaviour.mjs <file with the rendered page "Notifikasi", with unread notifications>
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from NotificationsTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['const rmFeedback = {', 'const notifBell = {', 'const notifPage = {'];
const open = (href = 'http://localhost/notifications') => loadPage(process.argv[2], markers, { href });
const { check, done } = checker();

const item = (id, other = {}) => ({
    id, kind: 'edit_requested', icon: 'ti-file-pencil', title: `Judul ${id}`, body: `Isi ${id}`, read: false, when: '5 menit yang lalu',
    at: '20-10-2026 10:00', open: `/notifications/${id}/open`, ...other,
});

// ---------------------------------------------------------------- the bell
check('the bell asks for the summary at once, and shows how many are unread', () => {
    const p = open();
    const first = p.requests[0];
    assert.match(first.url, /\/notifications\/summary$/);
    p.answer(first, { ready: true, unread: 3, items: [item(1)] });
    assert.equal(p.node('#notifCount').text, '3');
    assert.equal(p.node('#notifCount').classes.has('d-none'), false);
    assert.equal(p.node('#notifReadAll').props.hidden, false);
    p.page('notifBell').show({ ready: true, unread: 120, items: [] });
    assert.equal(p.node('#notifCount').text, '99+');
    p.page('notifBell').show({ ready: true, unread: 0, items: [] });
    assert.equal(p.node('#notifCount').classes.has('d-none'), true);
    assert.equal(p.node('#notifReadAll').props.hidden, true);
    assert.equal(p.node('#notifList').html, '<div class="rm-notif-empty">Belum ada notifikasi</div>');
});

check('a row: the icon in the colour of its kind, the title and when, the text, and a dot while it is unread', () => {
    const p = open();
    p.page('notifBell').show({ ready: true, unread: 1, items: [item(1), item(2, { kind: 'token_expiring', icon: 'ti-key', read: true, body: '' }), item(3, { kind: 'mystery', icon: 'bad"><b' })] });
    const html = p.node('#notifList').html;
    assertWellFormed(html, 'the rows of the bell');
    const [unread, read, other] = html.split('</a>');
    assert.match(unread, /^<a class="rm-notif-item is-unread" href="\/notifications\/1\/open"><span class="rm-notif-disc tone-orange" aria-hidden="true"><i class="ti ti-file-pencil"><\/i><\/span>/);
    assert.match(unread, /<span class="rm-notif-title">Judul 1<\/span><span class="rm-notif-time" title="20-10-2026 10:00">5 menit yang lalu<\/span>/);
    assert.match(unread, /<span class="rm-notif-body">Isi 1<\/span><\/span><span class="rm-notif-dot" aria-label="Belum dibaca"><\/span>$/);
    assert.match(read, /^<a class="rm-notif-item" href="\/notifications\/2\/open"><span class="rm-notif-disc tone-red"/);
    assert.doesNotMatch(read, /rm-notif-body|rm-notif-dot/);
    // A kind without a colour is grey; an icon that is not a Tabler icon's name is the bell.
    assert.match(other, /<span class="rm-notif-disc tone-gray" aria-hidden="true"><i class="ti ti-bell"><\/i><\/span>/);
});

check('the texts of a notification are shown as text', () => {
    const p = open();
    const hostile = '<img src=x onerror=alert(1)>';
    p.page('notifBell').show({ ready: true, unread: 1, items: [item(1, { title: hostile, body: hostile, when: hostile, at: `"${hostile}`, open: `" onclick="x` })] });
    const html = p.node('#notifList').html;
    assert.equal(html.includes('<img'), false);
    assert.equal(html.includes('" onclick="x'), false);
    assertWellFormed(html, 'a hostile row');
});

check('"Tandai semua dibaca" in the bell sends it, then asks for the summary again', () => {
    const p = open();
    p.answer(p.requests[0], { ready: true, unread: 2, items: [] });
    p.fire('#notifReadAll', 'click', {});
    const sent = p.requests.at(-1);
    assert.match(sent.url, /\/notifications\/read-all$/);
    assert.equal(sent.type, 'POST');
    p.answer(sent, { unread: 0 });
    assert.match(p.requests.at(-1).url, /\/notifications\/summary$/);
});

// ---------------------------------------------------------------- the page
check('on the page, "Tandai semua dibaca" marks everything read without leaving it', () => {
    const p = open();
    // (The bell has had its answer: it asks again only when it is not waiting for one.)
    p.answer(p.requests[0], { ready: true, unread: 2, items: [] });
    let left = false;
    p.fire('#notifPageReadAll', 'submit', { preventDefault() { left = true; } });
    assert.equal(left, true, 'the form is not sent the old way');
    const sent = p.requests.at(-1);
    assert.match(sent.url, /\/notifications\/read-all$/);
    assert.equal(sent.type, 'POST');
    assert.equal(p.node('#notifPageReadAllButton').props.disabled, true);
    p.answer(sent, { unread: 0 });
    assert.equal(p.node('#notifPageList .rm-notif-item').classes.has('is-unread'), false);
    assert.equal(p.node('#notifPageList .rm-notif-dot').removed, 1);
    assert.equal(p.node('#notifPageReadAll').removed, 1);
    assert.equal(p.node('#notifPageSub').text, 'Semua notifikasi sudah dibaca.');
    assert.equal(p.dialogs.at(-1).options.toast, true);
    // The bell is asked again.
    assert.match(p.requests.at(-1).url, /\/notifications\/summary$/);
    // On "Semua" the rows stay.
    assert.equal(p.node('#notifPageList').writes, 0);
});

check('a refusal is said in a toast, and the button can be used again', () => {
    const p = open();
    p.fire('#notifPageReadAll', 'submit', { preventDefault() {} });
    p.refuse(p.requests.at(-1), { status: 500 });
    assert.equal(p.dialogs.at(-1).options.toast, true);
    assert.match(p.dialogs.at(-1).options.customClass.popup, /rm-toast-error/);
    assert.equal(p.node('#notifPageReadAllButton').props.disabled, false);
});

check('every element the scripts reach for by id is in the page', () => {
    const p = open();
    p.fire('#notifPageReadAll', 'submit', { preventDefault() {} });
    p.answer(p.requests.at(-1), { unread: 0 });
    const used = p.ids();
    assert.ok(used.size >= 6, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
});

done();
