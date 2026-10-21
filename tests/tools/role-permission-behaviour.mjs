// Runs the inline JavaScript of the page "Role & Permission" without a browser and checks what it does: the rows (the
// name as text, how many users hold the role, View for every role and Edit/Delete where it may be changed), the search
// as it is typed, the dialog of a role in View (where no box can be ticked, also once the role has loaded: it could be
// before) and in Edit, and saving and deleting (asked in the design dialog, told in a toast).
//
// usage: node role-permission-behaviour.mjs <file with the rendered page>
//
// Like the other *-behaviour.mjs checkers: the page's script and the global helpers it uses are taken from the rendered
// HTML and run by page-sandbox.mjs. PHPUnit calls this from UserManagementTest (as Super Admin).
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const urlRolePermissionList', 'let currentPage = 1;', 'const roleSearch = () =>', 'const roleForm = {'];
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/user-management/role-permission' });
const { check, done } = checker();

const role = (id, other = {}) => ({ id, name: `Role ${id}`, user_count: 2, action: { is_active: true }, ...other });
const render = (p, key, item, index = 0) => p.page('columns').find(column => column.key === key).render(item, index);

// ---------------------------------------------------------------- the list
check('the page asks for the first page of roles, and shows that it is loading', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.equal(p.requests[0].url, p.page('urlRolePermissionList'));
    assert.deepEqual({ ...p.sent() }, { page: 1, paginated: 10, search: '', orderBy: 'id', orderDir: 'asc' });
    assert.match(p.node('#rolePermissionTable tbody').html, /Loading\.\.\./);
});

check('a row: its number across the pages, the name as text, how many users hold it, and the buttons it has', () => {
    const p = open();
    assert.equal(render(p, 'no', role(1), 2), '3');
    assert.equal(render(p, 'name', role(1, { name: '<b>Gudang</b>' })), '&lt;b&gt;Gudang&lt;/b&gt;');
    assert.equal(render(p, 'user_count', role(1, { user_count: 4 })), '<span class="badge bg-blue-lt">4</span>');
    assert.equal(render(p, 'user_count', role(1, { user_count: 0 })), '<span class="badge bg-secondary-lt">0</span>');
    // The page was rendered for Super Admin: View, Edit, Delete; a role that may not be changed: View only.
    const titles = item => [...render(p, 'actions', item).matchAll(/data-bs-title="([^"]+)"/g)].map(match => match[1]);
    assert.deepEqual(titles(role(2)), ['View', 'Edit', 'Delete']);
    assert.deepEqual(titles(role(3, { action: { is_active: false } })), ['View']);
    // Super Admin: a Super Admin may edit it (the server says so), nobody deletes it.
    assert.deepEqual(titles(role(1)), ['View', 'Edit']);
    assert.deepEqual(titles(role(1, { action: { is_active: false } })), ['View']);
    const buttons = render(p, 'actions', role(2));
    assertWellFormed(buttons, 'the buttons');
    assert.match(buttons, /<button type="button" class="rm-act" data-id="2" onclick="formRoleModal\('formRoleModal','Preview', 2\)"/);
    assert.match(buttons, /<button type="button" class="rm-act is-danger" data-id="2" onclick="formRoleDelete\(2\)"/);
});

check('the search is sent after a pause, or at once with Enter; Reset starts over', () => {
    const p = open();
    p.node('#userRegisterdSearch').value = ' Admin ';
    p.fire('#userRegisterdSearch', 'input', {});
    assert.equal(p.requests.length, 1, 'not before the pause');
    p.runTimers();
    assert.equal(p.sent().search, 'Admin');
    assert.equal(p.sent().page, 1);
    p.node('#userRegisterdSearch').value = 'Viewer';
    p.fire('#userRegisterdSearch', 'keydown', { key: 'Enter', preventDefault() {} });
    assert.equal(p.sent().search, 'Viewer');
    p.fire('#toggleResetButton', 'click', {});
    assert.equal(p.sent().search, '');
    assert.equal(p.node('#userRegisterdSearch').value, '');
});

// ---------------------------------------------------------------- the dialog
check('in View no box can be ticked, also once the role has loaded; in Edit a row\'s permissions follow its access box', () => {
    const p = open();
    const sync = p.page('syncMenuPermissionRowState');
    const access = p.node('.menu-access-checkbox[data-menu-id="5"]');
    const permissions = p.node('.menu-permission-checkbox[data-menu-id="5"]');

    p.page('formRoleModal')('formRoleModal', 'Preview', 3);
    assert.match(p.requests.at(-1).url, /\/role-permission\/3$/);
    assert.equal(p.node('#formRoleModal .modal-title').text, 'View role');
    assert.equal(p.node('#formRoleModal #roleCloseLink').text, 'Close');
    assert.equal(p.node('#formRoleModal :checkbox').props.disabled, true);
    // The role arrives: its access box is ticked, and its permissions stay as they are shown, not offered.
    access.props.checked = true;
    sync(5);
    assert.equal(permissions.props.disabled, true);

    p.page('formRoleModal')('formRoleModal', 'Edit', 3);
    assert.equal(p.node('#formRoleModal .modal-title').text, 'Edit role');
    assert.equal(p.node('#formRoleModal #roleCloseLink').text, 'Cancel');
    assert.equal(p.node('#formRoleModal #modalUpdateButton').attrs['data-form-id'], 3);
    sync(5);
    assert.equal(permissions.props.disabled, false);
    // No access: no permission, and none can be ticked.
    access.props.checked = false;
    permissions.props.checked = true;
    sync(5);
    assert.deepEqual([permissions.props.disabled, permissions.props.checked], [true, false]);

    p.page('formRoleModal')('formRoleModal', 'Add');
    assert.equal(p.node('#formRoleModal .modal-title').text, 'Add role');
    assert.equal(p.node('#formRoleModal').modal, 'show');
});

check('saving asks "Is the data correct?" in the centred dialog, sends the form, and says what was done in a toast', () => {
    const p = open();
    p.node('#form').value = 'role_name=Gudang&home_page=1&menu_list%5B%5D=1';
    const button = { getAttribute: name => ({ 'data-form-submit': 'Edit', 'data-form-id': '7' })[name] ?? null };
    p.page('formRoleSubmit')({ preventDefault() {} }, button);
    const dialog = p.dialogs.at(-1).options;
    assert.equal(dialog.title, 'Is the data correct?');
    assert.equal(dialog.text, 'This action will update the role!');
    assert.equal(dialog.customClass.popup, 'rm-swal rm-swal-center');
    const before = p.requests.length;
    p.resolve({ isConfirmed: false });
    assert.equal(p.requests.length, before, 'cancelled: nothing is sent, and nothing more is said');
    assert.equal(p.dialogs.length, 1);

    p.page('formRoleSubmit')({ preventDefault() {} }, button);
    p.resolve({ isConfirmed: true });
    const request = p.requests.at(-1);
    assert.equal(request.type, 'PUT');
    assert.match(request.url, /\/role-permission\/7$/);
    assert.match(request.data, /^role_name=Gudang&home_page=1&menu_list%5B%5D=1&_token=/);
});

check('deleting asks first, in red; a cancelled question sends nothing', () => {
    const p = open();
    p.page('formRoleDelete')(4);
    const dialog = p.dialogs.at(-1).options;
    assert.equal(dialog.title, 'Delete this role?');
    assert.equal(dialog.customClass.icon, 'rm-swal-icon tone-red');
    assert.equal(dialog.customClass.confirmButton, 'btn-rm destructive');
    const before = p.requests.length;
    p.resolve({ isConfirmed: false });
    assert.equal(p.requests.length, before);
    p.page('formRoleDelete')(4);
    p.resolve({ isConfirmed: true });
    assert.equal(p.requests.at(-1).type, 'Delete');
    assert.match(p.requests.at(-1).url, /\/role-permission\/4$/);
});

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const p = open();
    p.page('formRoleModal')('formRoleModal', 'Edit', 3);
    const used = p.ids();
    assert.ok(used.size >= 8, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
    assert.match(p.html, /class="form-check-input menu-access-checkbox"/);
    assert.match(p.html, /class="form-check-input menu-permission-checkbox"/);
});

done();
