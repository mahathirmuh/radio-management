// Runs the inline JavaScript of the list of users (User Management) as it is rendered in Indonesian, the language a
// page has until another one is chosen, and checks that what the script says comes from the language files: the bar of
// the users ticked and its questions, the rows and their buttons, the time since the last sign-in. What the script
// does is checked in English by user-bulk-behaviour.mjs.
//
// usage: node user-language-behaviour.mjs <file with the rendered page>
//
// PHPUnit calls this from UserManagementLanguageTest.
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const { node, page, fire, dialogs, resolve } = loadPage(
    process.argv[2],
    ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const urlUserRegisteredList', 'let filterList = {', 'const userBulk = {', 'const columns = ['],
    { href: 'http://localhost/user-management/user-registered' }
);
const { check, done } = checker();
const [userBulk, columns] = ['userBulk', 'columns'].map(name => page(name));

const user = (id, extra = {}) => ({ id, name: `User ${id}`, email: `user${id}@merdekabattery.com`, active: true, manageable: true, is_self: false, ...extra });
const render = (key, item) => columns.find(column => column.key === key).render(item, 0);
const pick = (...ids) => {
    userBulk.picked.clear();
    userBulk.shown(ids.map(id => user(id)));
    ids.forEach(id => userBulk.pick(id, `user${id}@merdekabattery.com`, true));
};

check('the bar counts the users ticked, with one form for one and for more', () => {
    pick(1);
    assert.equal(node('#userBulkCount').text, '1 user dipilih');
    pick(1, 2);
    assert.equal(node('#userBulkCount').text, '2 user dipilih');
});

check('its questions and what they answer', () => {
    pick(1, 2);
    fire('#userBulkDeactivate', 'click');
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.text], ['Nonaktifkan 2 user?',
        'Mereka tidak bisa masuk lagi, dan sesi yang sedang terbuka berakhir. Semua yang pernah mereka lakukan tetap tersimpan.']);
    resolve({ isConfirmed: false });
    fire('#userBulkActivate', 'click');
    assert.equal(dialogs.at(-1).options.title, 'Aktifkan 2 user?');
    resolve({ isConfirmed: false });
    fire('#userBulkDelete', 'click');
    assert.equal(dialogs.at(-1).options.title, 'Hapus 2 user?');
    assert.match(dialogs.at(-1).options.html, /Ketik <b>2<\/b> untuk menghapus\.$/);
    resolve({ isConfirmed: true, value: '5' });
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.html], ['Tidak dihapus', 'Yang diketik bukan 2.']);

    userBulk.finished({ message: '1 user dihapus. 1 dilewati.', done: 1, skipped: [{ user: 'a@b.c', reason: 'Anda tidak bisa menghapus akun Anda sendiri' }] });
    assert.equal(dialogs.at(-1).options.title, 'Sebagian dilewati');
    userBulk.failed({});
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.text], ['Gagal!', 'User tidak bisa diubah.']);
});

check('more than a hundred: the buttons say why they wait', () => {
    userBulk.picked.clear();
    for (let id = 1; id <= 101; id++) userBulk.pick(id, `u${id}@x`, true);
    assert.equal(node('#userBulkDelete').attrs.title, 'Paling banyak 100 user sekaligus.');
    userBulk.picked.clear();
    userBulk.refresh();
});

check('a row: its box, the account of the viewer, the status, how they sign in and the buttons', () => {
    assert.match(render('pick', user(1)), / aria-label="Pilih user1@merdekabattery.com" /);
    assert.match(render('pick', user(4, { manageable: false, is_self: true })), / disabled title="Akun Anda sendiri">$/);
    assert.match(render('pick', user(5, { manageable: false })), / disabled title="Anda tidak diizinkan mengelola user ini">$/);
    assert.match(render('name', user(1, { is_self: true })), /<div class="rm-line2">Anda<\/div>/);
    assert.equal(render('active', user(1)), '<span class="badge bg-green-lt">Aktif</span>');
    assert.equal(render('active', user(1, { active: false })), '<span class="badge bg-red-lt">Nonaktif</span>');
    assert.match(render('sign_in', user(1, { sign_in: 'Pending' })), /^<span class="badge bg-yellow-lt" title="Dibuat untuk masuk lewat Microsoft[^"]*">Belum dipakai<\/span>$/);
    assert.match(render('sign_in', user(1, { sign_in: 'Both' })), />Keduanya<\/span>$/);
    assert.match(render('sign_in', user(1, { sign_in: 'LDAP' })), />LDAP<\/span>$/);
    assert.equal(render('last_login_at', user(1, { last_sign_in: null })), '<span class="user-muted">Belum pernah</span>');
    const titles = item => [...render('actions', item).matchAll(/data-bs-title="([^"]+)"/g)].map(match => match[1]);
    assert.deepEqual(titles(user(2, { directory: false })), ['Edit', 'Nonaktifkan', 'Atur kata sandi', 'Hapus']);
    assert.deepEqual(titles(user(2, { active: false, directory: true })), ['Edit', 'Aktifkan', 'Hapus']);
});

check('the last sign-in as the time since then', () => {
    const ago = page('userAgo');
    const now = new Date(2026, 9, 20, 10, 30);
    assert.equal(ago('2026-10-20 10:30', now), 'baru saja');
    assert.equal(ago('2026-10-20 10:29', now), '1 menit lalu');
    assert.equal(ago('2026-10-20 10:25', now), '5 menit lalu');
    assert.equal(ago('2026-10-20 08:00', now), '2 jam lalu');
    assert.equal(ago('2026-10-19 08:00', now), 'kemarin');
    assert.equal(ago('2026-10-15 08:00', now), '5 hari lalu');
    assert.equal(ago('2026-08-01 08:00', now), '01-08-2026');
});

done();
