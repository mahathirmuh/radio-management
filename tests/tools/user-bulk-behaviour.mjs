// Runs the inline JavaScript of the list of users (User Management) without a browser and checks the bar of the users
// ticked (userBulk): a box on the rows that may be managed only, the number of the row, the box of the header for the
// page, what is ticked kept while the page changes, and what activating, deactivating and deleting send.
//
// usage: node user-bulk-behaviour.mjs <file with the rendered page>
//
// The page's own scripts are taken from the rendered HTML and run by page-sandbox.mjs, where "$" is a small stand-in for
// jQuery. PHPUnit calls this from UserManagementTest.
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const { node, page, fire, requests, dialogs, resolve, answer } = loadPage(
    process.argv[2],
    ['function debounce(', 'function renderTableWithFeatures(', 'const urlUserRegisteredList', 'let filterList = {', 'const userBulk = {', 'const columns = ['],
    { href: 'http://localhost/user-management/user-registered' }
);
const { check, done } = checker();
const [userBulk, columns] = ['userBulk', 'columns'].map(name => page(name));
// What the page made (in the sandbox's own realm) as plain data of this one, to be compared.
const plain = value => JSON.parse(JSON.stringify(value));
const noDefault = { preventDefault() {} };

const user = (id, extra = {}) => ({ id, name: `User ${id}`, email: `user${id}@merdekabattery.com`, active: true, manageable: true, is_self: false, ...extra });
const rows = [user(1), user(2), user(3), user(4, { manageable: false, is_self: true })];
const reset = (shown = rows) => {
    userBulk.picked.clear();
    userBulk.shown(shown);
};
const tick = (item, on = true) => {
    Object.assign(node('(object)'), { data: { id: item.id, email: item.email }, props: { checked: on } });
    fire('#userTable', 'change input.user-pick');
};
const cell = item => columns.find(column => column.key === 'pick').render(item);
const shown = selector => !node(selector).classes.has('d-none');
const lastTo = url => requests.filter(request => request.url === url).at(-1);

check('the list starts with the box and the number of the row, counted across the pages', () => {
    assert.equal(columns[0].key, 'pick');
    assert.equal(columns[1].key, 'no');
    assert.equal(columns[2].key, 'name');
    assert.equal(columns[1].render(user(1), 0), '1');
    assert.equal(columns[1].render(user(1), 4), '5');
});

check('a box on the rows that may be managed; none to tick on one\'s own, or on one not managed', () => {
    assert.match(cell(user(1)), /^<input type="checkbox" class="form-check-input m-0 user-pick" aria-label="Select user1@merdekabattery.com" data-id="1" data-email="user1@merdekabattery.com">$/);
    assert.match(cell(user(4, { manageable: false, is_self: true })), / disabled title="Your own account">$/);
    assert.match(cell(user(5, { manageable: false })), / disabled title="You are not allowed to manage this user">$/);
    assert.match(cell(user(6, { email: '"><img src=x onerror=alert(1)>' })), /data-email="&quot;&gt;&lt;img src=x onerror=alert\(1\)&gt;"/);
});

check('ticking counts; the bar and the box of the header say so', () => {
    reset();
    assert.equal(shown('#userBulkBar'), false);
    tick(rows[0]);
    assert.equal(node('#userBulkCount').text, '1 user selected');
    tick(rows[1]);
    assert.equal(node('#userBulkCount').text, '2 users selected');
    assert.equal(shown('#userBulkBar'), true);
    assert.deepEqual([node('#userPickPage').props.checked, node('#userPickPage').props.indeterminate], [false, true]);
    assert.match(cell(rows[0]), / checked>$/);
    tick(rows[0], false);
    assert.deepEqual([...userBulk.picked.keys()], [2]);
});

check('the box of the header ticks the rows that may be ticked, and unticks them', () => {
    reset();
    node('(object)').props = { checked: true };
    fire('#userPickPage', 'change');
    assert.deepEqual([...userBulk.picked.keys()], [1, 2, 3]);
    assert.deepEqual([node('#userPickPage').props.checked, node('#userPickPage').props.indeterminate], [true, false]);
    node('(object)').props = { checked: false };
    fire('#userPickPage', 'change');
    assert.equal(userBulk.picked.size, 0);
    // A page without a row to tick: the box of the header is off.
    reset([user(4, { manageable: false, is_self: true })]);
    assert.equal(node('#userPickPage').props.disabled, true);
});

check('what is ticked stays while the page changes', () => {
    reset();
    tick(rows[0]);
    tick(rows[2]);
    userBulk.shown([user(7), user(8)]);
    assert.equal(node('#userBulkCount').text, '2 users selected');
    assert.deepEqual([node('#userPickPage').props.checked, node('#userPickPage').props.indeterminate], [false, false]);
});

check('deactivating is asked first, then sent with the ids; the choice is let go and the list loads again', () => {
    reset();
    tick(rows[0]);
    tick(rows[1]);
    const before = requests.length;
    fire('#userBulkDeactivate', 'click');
    assert.equal(dialogs.at(-1).options.title, 'Deactivate 2 users?');
    resolve({ isConfirmed: false });
    assert.equal(requests.length, before);
    fire('#userBulkDeactivate', 'click');
    resolve({ isConfirmed: true });
    const sent = lastTo(userBulk.urls.status);
    assert.equal(sent.type, 'POST');
    assert.deepEqual(plain(sent.data), { _method: 'PUT', ids: [1, 2], active: 0 });
    const loads = requests.length;
    answer(sent, { message: '2 users deactivated.', done: 2, skipped: [] });
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.icon], ['Done', 'success']);
    assert.equal(userBulk.picked.size, 0);
    assert.equal(requests.length, loads + 1);
});

check('activating sends active=1', () => {
    reset();
    tick(rows[2]);
    fire('#userBulkActivate', 'click');
    assert.equal(dialogs.at(-1).options.title, 'Activate 1 user?');
    resolve({ isConfirmed: true });
    assert.deepEqual(plain(lastTo(userBulk.urls.status).data), { _method: 'PUT', ids: [3], active: 1 });
});

check('deleting asks for the number to be typed, and only that number deletes', () => {
    reset();
    tick(rows[0]);
    tick(rows[1]);
    const before = requests.length;
    fire('#userBulkDelete', 'click');
    assert.deepEqual([dialogs.at(-1).options.title, dialogs.at(-1).options.input], ['Delete 2 users?', 'text']);
    resolve({ isConfirmed: true, value: '5' });
    assert.equal(requests.length, before);
    assert.equal(dialogs.at(-1).options.title, 'Not deleted');
    fire('#userBulkDelete', 'click');
    resolve({ isConfirmed: true, value: '2 ' });
    assert.deepEqual(plain(lastTo(userBulk.urls.destroy).data), { _method: 'DELETE', ids: [1, 2], confirm: 2 });
});

check('what was left out is said as text', () => {
    reset();
    userBulk.finished({ message: '<b>1</b> user deleted. 1 skipped.', done: 1, skipped: [{ user: '<img src=x onerror=alert(1)>', reason: 'You cannot delete your own account' }] });
    const dialog = dialogs.at(-1).options;
    assert.deepEqual([dialog.title, dialog.icon], ['Some were skipped', 'warning']);
    assert.match(dialog.html, /&lt;b&gt;1&lt;\/b&gt; user deleted/);
    assert.match(dialog.html, /<li>&lt;img src=x onerror=alert\(1\)&gt;: You cannot delete your own account<\/li>/);
});

check('"Clear selection" lets go of everything', () => {
    reset();
    tick(rows[0]);
    fire('#userBulkClear', 'click', noDefault);
    assert.deepEqual([userBulk.picked.size, shown('#userBulkBar')], [0, false]);
});

check('more than a hundred: the buttons wait', () => {
    reset();
    for (let id = 1; id <= 101; id++) userBulk.pick(id, `u${id}@x`, true);
    assert.equal(node('#userBulkDelete').props.disabled, true);
    assert.equal(node('#userBulkDeactivate').attrs.title, 'At most 100 users at a time.');
    userBulk.pick(101, '', false);
    assert.equal(node('#userBulkDelete').props.disabled, false);
});

done();
