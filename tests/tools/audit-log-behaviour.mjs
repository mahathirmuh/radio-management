// Runs the inline JavaScript of the page "Audit log" (auditLog) without a browser and checks what it does: which request
// the filters send and what the address of the page says, how an action is shown (a word, a colour, an icon, its key
// kept), the table and Log activity, the details of an entry that open beside the list, and escaping of everything that
// comes from the trail.
//
// usage: node audit-log-behaviour.mjs <file with the rendered page>
//
// Like the other *-behaviour.mjs checkers: the page's script and the global helpers it uses are taken from the rendered
// HTML and run by page-sandbox.mjs. PHPUnit calls this from AuditLogTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const auditLog = {'];
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/audit-log' });
const { check, done } = checker();

const meta = (total, current = 1) => ({ current_page: current, from: total ? 1 : null, last_page: Math.max(1, Math.ceil(total / 25)), per_page: 25, to: total || null, total });
// One entry as the list gives it.
const entry = (id, other = {}) => ({
    id, at: '19-10-2026 10:12:41', ago: '18 minutes ago', user: 'Admin Satu', email: 'admin.satu@example.test', action: 'radio.updated',
    activity: 'updated radio', entity: `Radio #${id}`, label: `ID 10${id} · SN SN00${id}`,
    changes: [{ field: 'User', old: 'Pemakai Lama', new: 'Pemakai Baru' }, { field: 'Location', old: null, new: 'Gudang' }],
    ip: '192.0.2.10', via: null, ...other,
});
const loaded = (rows = [entry(1), entry(2, { action: 'auth.login', activity: 'signed in', entity: null, label: null, changes: [] })]) => {
    const p = open();
    p.answer(p.requests[0], { data: rows, meta: meta(rows.length) });

    return p;
};
const body = p => p.node('#auditTable tbody').html;
// A click (or a key) on a row or an entry, as the browser does: the element carries its place in the list.
const press = (p, selector, event, index, argument = {}) => {
    p.node('(object)').attrs['data-index'] = String(index);

    return p.fire(selector, event, argument);
};
const shown = (p, selector) => !p.node(selector).classes.has('d-none');

// ---------------------------------------------------------------- first load and the filters
check('the page asks for the first page with the filters of its address, and shows that it is loading', () => {
    const p = open();
    assert.equal(p.requests.length, 1);
    assert.match(p.requests[0].url, /\/audit-log\/list$/);
    assert.deepEqual(p.sent(), { page: 1, paginated: 25, search: p.page('auditLog').filters.search, action: p.page('auditLog').filters.action });
    assert.match(body(p), /Loading\.\.\./);
    assert.equal(p.node('#auditPagination').hidden, true);
    // Nothing is open yet.
    assert.equal(shown(p, '#auditDetails'), false);
});

check('a search is sent after a pause, or at once with Enter; a chosen action at once; the address says what is shown', () => {
    const p = loaded();
    p.node('#auditSearch').value = '  siti@example.test ';
    p.fire('#auditSearch', 'input', {});
    assert.equal(p.requests.length, 1, 'not before the pause');
    p.runTimers();
    assert.equal(p.sent().search, 'siti@example.test');
    assert.equal(p.sent().page, 1);
    assert.equal(p.addresses().at(-1), '/audit-log?q=siti%40example.test');

    p.node('#auditAction').value = 'auth.login_failed';
    p.fire('#auditAction', 'change', {});
    assert.equal(p.sent().action, 'auth.login_failed');
    assert.equal(p.addresses().at(-1), '/audit-log?action=auth.login_failed&q=siti%40example.test');

    p.node('#auditSearch').value = 'budi';
    p.fire('#auditSearch', 'keydown', { key: 'Enter' });
    assert.equal(p.sent().search, 'budi');
});

// ---------------------------------------------------------------- how an action is shown
check('an action is a word in its colour, with its key in the tooltip; one without a word shows its key', () => {
    const kind = action => JSON.parse(JSON.stringify(open().page('auditLog').kind(action)));
    assert.deepEqual(kind('radio.updated'), { label: 'Updated', tone: 'orange', icon: 'edit' });
    assert.deepEqual(kind('user.created'), { label: 'Created', tone: 'green', icon: 'plus' });
    assert.deepEqual(kind('radio.deleted'), { label: 'Deleted', tone: 'red', icon: 'trash' });
    assert.deepEqual(kind('auth.login'), { label: 'Signed in', tone: 'blue', icon: 'login' });
    assert.deepEqual(kind('auth.login_failed'), { label: 'Sign-in failed', tone: 'red', icon: 'lock' });
    assert.deepEqual(kind('radio.imported'), { label: 'Imported', tone: 'purple', icon: 'upload' });
    assert.deepEqual(kind('radio.archived'), { label: 'Archived', tone: 'gray', icon: 'archive' });
    assert.deepEqual(kind('something.new'), { label: 'something.new', tone: 'gray', icon: 'activity' });

    const p = loaded();
    assert.match(body(p), /<span class="badge bg-orange-lt" title="radio\.updated">Updated<\/span>/);
    assert.match(body(p), /<span class="badge bg-blue-lt" title="auth\.login">Signed in<\/span>/);
    assert.equal(p.page('auditLog').badge('radio.archived'), '<span class="badge bg-secondary-lt" title="radio.archived">Archived</span>');
});

// ---------------------------------------------------------------- the table
check('a row: the day and the time to the minute, who (the address in the tooltip, an API token), the record, the change in one line, the address', () => {
    const p = loaded([entry(1, { via: 'API · Power BI (#3)' }), entry(2, { action: 'auth.login', activity: 'signed in', entity: null, label: null, changes: [], ip: null })]);
    const rows = body(p).split('</tr>');
    assertWellFormed(body(p), 'the rows');
    assert.match(rows[0], /<tr data-index="0" tabindex="0">/);
    assert.match(rows[0], /<td class="text-nowrap"><div>19-10-2026<\/div><div class="rm-line2">10:12<\/div><\/td>/);
    assert.match(rows[0], /<td class="audit-user"><div class="rm-strong" title="admin\.satu@example\.test">Admin Satu<\/div><div class="mt-1"><span class="badge bg-purple-lt">via API · Power BI \(#3\)<\/span><\/div><\/td>/);
    assert.match(rows[0], /<div class="audit-label">ID 101 · SN SN001<\/div><div class="rm-line2 text-nowrap">Radio #1<\/div>/);
    assert.match(rows[0], /<td class="audit-summary"><span class="audit-clamp" title="User: Pemakai Lama → Pemakai Baru · Location: Gudang">User: Pemakai Lama → Pemakai Baru · Location: Gudang<\/span><\/td>/);
    assert.match(rows[0], /<td class="text-nowrap text-end rm-code audit-ip">192\.0\.2\.10<\/td>/);
    // An entry about no record, with no change and no address: dashes.
    assert.equal((rows[1].match(/<span class="rm-none">—<\/span>/g) || []).length, 3);
    assert.equal(p.node('#auditPagination').hidden, false);
    assert.equal(p.node('#auditPagination').movedTo, '#auditTableBox');
});

// ---------------------------------------------------------------- the details
check('a click on a row opens its details beside the list; a click on it again, or the cross, closes them', () => {
    const p = loaded();
    press(p, '#auditTable', 'click tbody tr[data-index]', 0);
    assert.equal(shown(p, '#auditDetails'), true);
    assert.equal(p.node('#auditBody').classes.has('has-details'), true);
    assert.equal(p.node('#auditTable tbody tr[data-index="0"]').classes.has('is-selected'), true);
    assert.equal(p.node('#auditDetailsSub').text, 'Updated · ID 101 · SN SN001');
    const metaHtml = p.node('#auditDetailsMeta').html;
    assertWellFormed(metaHtml, 'the details');
    assert.deepEqual([...metaHtml.matchAll(/<dt>([^<]*)<\/dt>/g)].map(match => match[1]), ['Time', 'User', 'Action', 'Entity', 'Record', 'IP']);
    // To the second, and the key of the action as it is stored.
    assert.match(metaHtml, /<dt>Time<\/dt><dd>19-10-2026 10:12:41<\/dd>/);
    assert.match(metaHtml, /<dt>Action<\/dt><dd class="font-monospace">radio\.updated<\/dd>/);
    // The change line by line: what was there struck in red, what came in green.
    assert.equal(p.node('#auditDetailsDiff').html,
        '<div class="is-old">- User: Pemakai Lama</div><div class="is-new">+ User: Pemakai Baru</div><div class="is-new">+ Location: Gudang</div>');

    // Another row: its details, and only it is marked.
    press(p, '#auditTable', 'click tbody tr[data-index]', 1);
    assert.equal(p.page('auditLog').open, 1);
    assert.equal(p.node('#auditDetailsSub').text, 'Signed in');
    assert.equal(p.node('#auditDetailsDiff').html, '<div class="is-none">No field was changed.</div>');
    assert.equal(p.node('#auditTable tbody tr[data-index="1"]').classes.has('is-selected'), true);

    press(p, '#auditTable', 'click tbody tr[data-index]', 1);
    assert.equal(shown(p, '#auditDetails'), false);
    assert.equal(p.node('#auditBody').classes.has('has-details'), false);
    assert.equal(p.page('auditLog').open, null);

    // With the keyboard, and closed with the cross.
    press(p, '#auditTable, #auditTimeline', 'keydown [data-index]', 0, { key: 'Enter', preventDefault() {} });
    assert.equal(p.page('auditLog').open, 0);
    p.fire('#auditDetailsClose', 'click', {});
    assert.equal(shown(p, '#auditDetails'), false);

    // Other entries come (a filter, another page): what was open closes.
    press(p, '#auditTable', 'click tbody tr[data-index]', 0);
    p.node('#auditAction').value = 'auth.login';
    p.fire('#auditAction', 'change', {});
    assert.equal(shown(p, '#auditDetails'), false);
    assert.equal(p.page('auditLog').open, null);
});

// ---------------------------------------------------------------- Log activity
check('Log activity: a disc in the colour of the action, a sentence, the change, then the time, the key and the address', () => {
    const p = loaded();
    p.node('(object)').data['audit-view'] = 'timeline';
    p.fire('[data-audit-view]', 'click', {});
    assert.equal(shown(p, '#auditTimelineView'), true);
    assert.equal(shown(p, '#auditTableView'), false);
    assert.equal(p.node('[data-audit-view="timeline"]').classes.has('is-on'), true);
    assert.equal(p.node('[data-audit-view="timeline"]').attrs['aria-pressed'], 'true');
    assert.equal(p.node('[data-audit-view="table"]').attrs['aria-pressed'], 'false');
    assert.equal(p.addresses().at(-1), '/audit-log?view=timeline');
    assert.equal(p.node('#auditPagination').movedTo, '#auditTimelineBox');

    const list = p.node('#auditTimeline').html;
    assertWellFormed(list, 'Log activity');
    const [first, second] = list.split('</li>');
    assert.match(first, /<li class="audit-event" data-index="0" tabindex="0"><span class="audit-disc tone-orange" aria-hidden="true"><i class="ti ti-edit"><\/i><\/span>/);
    assert.match(first, /<div class="audit-sentence"><b>Admin Satu<\/b> updated radio <b>ID 101 · SN SN001<\/b> <span class="audit-dot">•<\/span> <span class="audit-ago">18 minutes ago<\/span><\/div>/);
    assert.match(first, /<div class="audit-changes"><div><span class="audit-field">User:<\/span><s>Pemakai Lama<\/s><span class="audit-to">→<\/span><span>Pemakai Baru<\/span><\/div><div><span class="audit-field">Location:<\/span><span>Gudang<\/span><\/div><\/div>/);
    assert.match(first, /<div class="audit-stamp"><span>19-10-2026 10:12:41<\/span><span class="audit-key">radio\.updated<\/span><span>from 192\.0\.2\.10<\/span><\/div>/);
    assert.match(second, /tone-blue[\s\S]*ti-login[\s\S]*<b>Admin Satu<\/b> signed in <span class="audit-dot">/);
    assert.doesNotMatch(second, /audit-changes/);

    // An entry opens its details there too, and is marked.
    press(p, '#auditTimeline', 'click .audit-event', 1);
    assert.equal(shown(p, '#auditDetails'), true);
    assert.equal(p.node('#auditTimeline .audit-event[data-index="1"]').classes.has('is-open'), true);

    // Back to the table: the address no longer names a view.
    p.node('(object)').data['audit-view'] = 'table';
    p.fire('[data-audit-view]', 'click', {});
    assert.equal(p.addresses().at(-1), '/audit-log');
    assert.equal(shown(p, '#auditTableView'), true);
});

check('nothing there, a failure and a trail that is not switched on are said in words', () => {
    const p = open();
    p.answer(p.requests[0], { data: [], meta: meta(0) });
    assert.match(body(p), /No audit entries yet\./);
    const failed = open();
    failed.refuse(failed.requests[0], { status: 500 });
    assert.match(body(failed), /The audit log could not be loaded\./);
    const off = open();
    off.refuse(off.requests[0], { status: 503 });
    assert.match(body(off), /The audit log is not switched on yet\./);
});

// ---------------------------------------------------------------- escaping
check('everything that comes from the trail is shown as text, in the table, Log activity and the details', () => {
    const hostile = '<img src=x onerror=alert(1)>';
    const p = loaded([entry(1, {
        user: hostile, email: `${hostile}@x`, action: `x"><b>`, activity: hostile, entity: hostile, label: hostile, ip: hostile, via: hostile, at: hostile, ago: hostile,
        changes: [{ field: hostile, old: hostile, new: hostile }],
    })]);
    press(p, '#auditTable', 'click tbody tr[data-index]', 0);
    p.node('(object)').data['audit-view'] = 'timeline';
    p.fire('[data-audit-view]', 'click', {});
    for (const [what, html] of [['the table', body(p)], ['Log activity', p.node('#auditTimeline').html], ['the details', p.node('#auditDetailsMeta').html + p.node('#auditDetailsDiff').html]]) {
        assert.equal(html.includes('<img'), false, `${what}: ${html.slice(0, 200)}`);
        assert.equal(html.includes('x"><b>'), false, `${what}: the key of the action`);
        assertWellFormed(html, what);
    }
    // (Written as text.)
    assert.equal(p.node('#auditDetailsSub').text, `x"><b> · ${hostile}`);
});

// ---------------------------------------------------------------- the script and the page fit together
check('every element the script reaches for by id is in the page', () => {
    const p = loaded();
    press(p, '#auditTable', 'click tbody tr[data-index]', 0);
    const used = p.ids();
    assert.ok(used.size >= 10, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
    assert.ok(/<table[^>]*id="auditTable"[^>]*>\s*<thead>[\s\S]*?<\/thead>\s*<tbody><\/tbody>/.test(p.html), 'the table has no head and empty body to fill');
    // The columns the script draws are the columns the head names.
    assert.equal((p.html.match(/<th scope="col"/g) || []).length, p.page('auditLog').columns.length);
    assert.ok(/data-audit-view="table"/.test(p.html) && /data-audit-view="timeline"/.test(p.html));
});

done();
