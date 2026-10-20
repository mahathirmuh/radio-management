// Runs the inline JavaScript of the page "API Tokens" (apiTokens) without a browser and checks what it does: the rows of
// tokens and API clients (a row that no longer works is faded), the owners a token can be given, making a token (shown
// once, then taken off the page), revoking and deleting (asked first, then told in a toast), and escaping of what comes
// from the database.
//
// usage: node api-tokens-behaviour.mjs <file with the rendered page>
//
// Like the other *-behaviour.mjs checkers: the page's script and the global helpers it uses are taken from the rendered
// HTML and run by page-sandbox.mjs. PHPUnit calls this from ApiTokensPageTest (as Super Admin).
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['const rmFeedback = {', 'const apiTokens = {'];
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/settings/api-tokens' });
const { check, done } = checker();

const person = (other = {}) => ({ id: 1, name: 'Admin Satu', kind: 'person', email: 'admin.satu@example.test', me: true, deleted: false, active: true, ...other });
const client = (id, other = {}) => ({ id, name: `Aplikasi ${id}`, role: { id: 3, name: 'Viewer Radio' }, active: true, tokens: 2, created_at: '01-10-2026', ...other });
const token = (id, other = {}) => ({
    id, name: `Token ${id}`, owner: person(), access: 'Read only', writes: false, created_at: '19-10-2026 10:12', expires_at: '19-10-2027',
    expired: false, last_used_at: '20-10-2026 08:00', last_used_ip: '192.0.2.7', ...other,
});
const loaded = (tokens = [token(1), token(2, { writes: true, owner: { ...person({ me: false, kind: 'client', email: null, name: 'Aplikasi 5' }), id: 5 } })], clients = [client(5), client(6, { active: false })]) => {
    const p = open();
    p.answer(p.requests[0], { tokens, clients });

    return p;
};
const rows = (p, table) => p.node(`${table} tbody`).html.split('</tr>');

// ---------------------------------------------------------------- the lists
check('the page asks for the tokens and the clients once, and shows that it is loading', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.match(p.requests[0].url, /\/settings\/api-tokens\/list$/);
    assert.match(p.node('#tokenTable tbody').html, /Loading\.\.\./);
});

check('a token: its name, its owner (you, or an API client), its access, when made, until when, when last used', () => {
    const p = loaded();
    const html = p.node('#tokenTable tbody').html;
    assertWellFormed(html, 'the tokens');
    const [mine, theirs] = rows(p, '#tokenTable');
    assert.match(mine, /^<tr><td class="rm-strong">Token 1<\/td>/);
    assert.match(mine, /<div>Admin Satu <span class="rm-tag ms-1">You<\/span><\/div><div class="rm-line2">admin\.satu@example\.test<\/div>/);
    assert.match(mine, /<span class="badge bg-green-lt">Read only<\/span>/);
    assert.match(mine, /<td class="text-nowrap"><div>19-10-2026<\/div><div class="rm-line2">10:12<\/div><\/td>/);
    assert.match(mine, /<td class="text-nowrap"><div>20-10-2026 08:00<\/div><div class="rm-line2">192\.0\.2\.7<\/div><\/td>/);
    assert.match(mine, /<button type="button" class="rm-act is-danger" data-revoke="1" data-name="Token 1" data-bs-toggle="tooltip" data-bs-title="Revoke" aria-label="Revoke Token 1">/);
    assert.match(theirs, /<div>Aplikasi 5<\/div><div class="rm-line2">API client<\/div>/);
    assert.match(theirs, /<span class="badge bg-orange-lt">Read &amp; write<\/span>/);
});

check('a token that no longer works is faded: expired, or its owner inactive, deleted or gone', () => {
    const p = loaded([
        token(1, { expired: true, expires_at: '01-01-2026' }),
        token(2, { owner: person({ active: false }) }),
        token(3, { owner: person({ deleted: true, active: false }) }),
        token(4, { owner: null, last_used_at: null }),
        token(5),
    ]);
    const [expired, inactive, deleted, gone, fine] = rows(p, '#tokenTable');
    assert.match(expired, /^<tr class="rm-dim">/);
    assert.match(expired, /<div>01-01-2026<\/div><div class="rm-line2 token-expired">expired<\/div>/);
    assert.match(inactive, /^<tr class="rm-dim">[\s\S]*admin\.satu@example\.test · inactive/);
    assert.match(deleted, /^<tr class="rm-dim">[\s\S]*admin\.satu@example\.test · deleted/);
    assert.match(gone, /^<tr class="rm-dim">[\s\S]*<span class="rm-none">—<\/span>[\s\S]*<span class="rm-none">Never<\/span>/);
    assert.match(fine, /^<tr>/);
});

check('an API client: its role, status and tokens; an inactive one is faded and is not offered as an owner', () => {
    const p = loaded();
    const html = p.node('#clientTable tbody').html;
    assertWellFormed(html, 'the clients');
    const [active, inactive] = rows(p, '#clientTable');
    assert.match(active, /^<tr><td class="rm-strong">Aplikasi 5<\/td><td>Viewer Radio<\/td><td><span class="badge bg-green-lt">Active<\/span><\/td><td>2<\/td>/);
    assert.match(active, /data-edit-client="5"[\s\S]*data-delete-client="5" data-name="Aplikasi 5"/);
    assert.match(inactive, /^<tr class="rm-dim">[\s\S]*<span class="badge bg-secondary-lt">Inactive<\/span>/);
    // "Me" first, then the active clients.
    assert.match(p.node('#tokenOwner').html, /^<option value="me">Me \([^<]*\)<\/option><option value="5">Aplikasi 5 \(API client\)<\/option>$/);

    const none = loaded([], []);
    assert.match(none.node('#tokenTable tbody').html, /No tokens yet\./);
    assert.match(none.node('#clientTable tbody').html, /No API clients yet\./);
});

// ---------------------------------------------------------------- making a token
check('a new token is sent with its owner, access and life, then shown once with an example; "I have copied it" takes it off the page', () => {
    const p = loaded();
    p.fire('#tokenAddButton', 'click', {});
    assert.deepEqual(p.node('#tokenForm').triggered, ['reset']);
    assert.equal(p.node('#tokenModal').modal, 'show');

    // Without a name nothing is sent.
    p.node('#tokenName').value = '   ';
    const before = p.requests.length;
    p.fire('#tokenForm', 'submit', { preventDefault() {} });
    assert.equal(p.requests.length, before);
    assert.equal(p.dialogs.at(-1).options.text, 'The name of the token is empty.');

    p.node('#tokenName').value = ' Power BI ';
    p.node('#tokenOwner').value = '5';
    p.node('#tokenDays').value = '90';
    p.node('input[name="tokenAccess"]:checked').value = 'read_write';
    p.fire('#tokenForm', 'submit', { preventDefault() {} });
    const request = p.requests.at(-1);
    assert.match(request.url, /\/settings\/api-tokens\/tokens$/);
    assert.equal(request.type, 'POST');
    assert.deepEqual(p.sent(), { name: 'Power BI', owner: '5', access: 'read_write', days: '90' });
    assert.equal(p.node('#tokenSave').props.disabled, true, 'busy while it is sent');

    p.answer(request, { token: '7|mtiradio_secret' });
    assert.equal(p.node('#tokenSave').props.disabled, false);
    assert.equal(p.node('#tokenModal').modal, 'hide');
    assert.equal(p.node('#tokenSecret').value, '7|mtiradio_secret');
    assert.equal(p.node('#tokenExample').text, `curl -H "Authorization: Bearer 7|mtiradio_secret" ${p.page('apiTokens').apiBase}/me`);
    assert.equal(p.node('#tokenShownModal').modal, 'show');
    assert.match(p.requests.at(-1).url, /\/list$/, 'the lists are read again');

    p.fire('#tokenShownDone', 'click', {});
    assert.equal(p.node('#tokenSecret').value, '');
    assert.equal(p.node('#tokenExample').text, '');
    assert.equal(p.node('#tokenShownModal').modal, 'hide');
});

// ---------------------------------------------------------------- revoking, and the clients
check('revoking asks first, in the design dialog; done, a toast says so and the lists are read again', () => {
    const p = loaded();
    p.node('(object)').data.revoke = 1;
    p.node('(object)').data.name = 'Token 1';
    p.fire('#tokenTable', 'click [data-revoke]', {});
    const dialog = p.dialogs.at(-1).options;
    assert.equal(dialog.title, 'Revoke this token?');
    assert.equal(dialog.text, '"Token 1" stops working at once. An application that uses it gets 401.');
    assert.equal(dialog.customClass.confirmButton, 'btn-rm destructive');
    const sent = p.requests.length;
    p.resolve({ isConfirmed: false });
    assert.equal(p.requests.length, sent, 'cancelled: nothing is sent');

    p.node('(object)').data.revoke = 1;
    p.node('(object)').data.name = 'Token 1';
    p.fire('#tokenTable', 'click [data-revoke]', {});
    p.resolve({ isConfirmed: true });
    const request = p.requests.at(-1);
    assert.match(request.url, /\/settings\/api-tokens\/tokens\/1$/);
    assert.equal(request.type, 'DELETE');
    p.answer(request, {});
    assert.equal(p.dialogs.at(-1).options.toast, true);
    assert.equal(p.dialogs.at(-1).options.titleText, 'Revoked! The token no longer works.');
    assert.match(p.requests.at(-1).url, /\/list$/);
});

check('a client is made, changed (with its status) and deleted; each is told in a toast', () => {
    const p = loaded();
    p.fire('#clientAddButton', 'click', {});
    assert.equal(p.node('#clientModalTitle').text, 'New API client');
    assert.equal(p.node('#clientStatusRow').classes.has('d-none'), true);
    p.node('#clientName').value = ' Power BI ';
    p.node('#clientRole').value = '3';
    p.fire('#clientForm', 'submit', { preventDefault() {} });
    assert.match(p.requests.at(-1).url, /\/settings\/api-tokens\/clients$/);
    assert.deepEqual(p.sent(), { name: 'Power BI', role_id: '3' });
    p.answer(p.requests.at(-1), {});
    assert.equal(p.dialogs.at(-1).options.titleText, 'The API client has been made. Now make a token for it.');

    p.node('(object)').data['edit-client'] = 6;
    p.fire('#clientTable', 'click [data-edit-client]', {});
    assert.equal(p.node('#clientModalTitle').text, 'Edit API client');
    assert.equal(p.node('#clientModalIcon').classes.has('tone-orange'), true);
    assert.equal(p.node('#clientName').value, 'Aplikasi 6');
    assert.equal(p.node('#clientActive').value, '0');
    assert.equal(p.node('#clientStatusRow').classes.has('d-none'), false);
    p.fire('#clientForm', 'submit', { preventDefault() {} });
    assert.match(p.requests.at(-1).url, /\/settings\/api-tokens\/clients\/6$/);
    assert.deepEqual(p.sent(), { name: 'Aplikasi 6', role_id: '3', active: '0', _method: 'PUT' });
    p.answer(p.requests.at(-1), {});
    assert.equal(p.dialogs.at(-1).options.titleText, 'The API client has been saved.');

    p.node('(object)').data['delete-client'] = 6;
    p.node('(object)').data.name = 'Aplikasi 6';
    p.fire('#clientTable', 'click [data-delete-client]', {});
    assert.equal(p.dialogs.at(-1).options.title, 'Delete this API client?');
    p.resolve({ isConfirmed: true });
    assert.match(p.requests.at(-1).url, /\/settings\/api-tokens\/clients\/6$/);
    assert.equal(p.requests.at(-1).type, 'DELETE');
    // Refused: said as an error, in a dialog.
    p.refuse(p.requests.at(-1), { responseJSON: { message: 'Not allowed.' } });
    assert.deepEqual([p.dialogs.at(-1).options.icon, p.dialogs.at(-1).options.text], ['error', 'Not allowed.']);
});

// ---------------------------------------------------------------- escaping
check('names and addresses from the database are shown as text', () => {
    const hostile = '<img src=x onerror=alert(1)>';
    const p = loaded(
        [token(1, { name: hostile, owner: person({ name: hostile, email: hostile }), last_used_ip: hostile, expires_at: hostile, created_at: `${hostile} x` })],
        [client(5, { name: hostile, role: { id: 3, name: hostile }, created_at: hostile })]
    );
    for (const selector of ['#tokenTable tbody', '#clientTable tbody', '#tokenOwner']) {
        const html = p.node(selector).html;
        assert.equal(html.includes('<img'), false, `${selector}: ${html.slice(0, 160)}`);
    }
});

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const p = loaded();
    p.fire('#tokenAddButton', 'click', {});
    p.fire('#clientAddButton', 'click', {});
    const used = p.ids();
    assert.ok(used.size >= 15, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
    assert.equal((p.html.match(/<input type="radio" name="tokenAccess"/g) || []).length, 2);
});

done();
