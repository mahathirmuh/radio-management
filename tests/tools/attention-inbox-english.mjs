// Runs the script of "Perlu Perhatian" (attnPage) of the page rendered in English, and checks the words it writes: the
// counts, the item at the right, the buttons, the dialog, what it says when something is missing or refused. What it
// does is checked in Indonesian by attention-inbox-behaviour.mjs; this is only about the words.
//
// usage: node attention-inbox-english.mjs <file with the rendered page, for Super Admin, in English>
//
// PHPUnit calls this from AttentionHistoryLanguageTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const markers = ['function debounce(', 'function renderTableWithFeatures(', 'const rmFeedback = {', 'const rmPickers = {', 'const RadioUi = {', 'const attnPage = {'];
const open = () => {
    const p = loadPage(process.argv[2], markers, { href: 'http://localhost/radio-management/attention' });
    p.page('attnPage').me = 5;
    p.page('attnPage').people = [{ id: 5, name: 'Admin <i>Contoh</i>' }, { id: 6, name: 'Petugas Dua' }];
    return p;
};
const { check, done } = checker();

const item = (subject, other = {}) => ({
    check: 'needs_review', subject, head: `ID ${subject} · 867TZB${subject}`, side: 'XiR P8668i', who: 'Pemakai Satu',
    assignee: null, first_seen: '2026-10-12', ...other,
});
const listed = (p, body = {}) => {
    const asked = p.requests.filter(request => /\/attention\/list$/.test(request.url)).at(-1);
    p.answer(asked, { check: 'needs_review', counts: { needs_review: 2, sn_duplicate: 1 }, total: 2, data: [item('2001'), item('2002')], more: false, page: 1, ready: true, ...body });
};
const detail = (other = {}) => ({
    check: 'sn_duplicate', subject: '867TZB5309', head: '867TZB5309', side: 'Radios 2001 and 2002', who: 'Pemakai Satu and Pemakai Dua',
    assignee: { id: 5, name: 'Admin <i>Contoh</i>' }, first_seen: '2026-10-12',
    label: 'Duplicate SN', color: 'red', title: 'SN 867TZB5309 is used by 2 radios', note: null, dismissed: null,
    records: [{ id: 1, head: 'Radio 2001', tag: 'Created 14-03-2025', suspect: false, rows: [{ label: 'SN', value: '867TZB5309', mono: true, marked: false }] }],
    options: [
        { id: 'sn:2', title: 'Correct the SN of radio 2002', detail: 'Check the sticker.', field: { kind: 'text', mono: true, value: '', placeholder: '867TZB5309', max: 50 } },
        { id: 'type:2', title: 'Choose the radio type', detail: '', field: { kind: 'select', value: '', choices: [{ value: '7', label: 'XiR P8668i' }] } },
        { id: 'dismiss', title: 'These radios really are different', detail: 'Dismiss this check, with the reason.' },
    ],
    ...other,
});
const shown = (p, body = detail()) => {
    const asked = p.requests.filter(request => /\/attention\/item$/.test(request.url)).at(-1);
    p.answer(asked, { data: body });
};
const panel = p => p.node('#attnDetail').html;

check('the checks and the items count in English: one item, more items', () => {
    const p = open();
    listed(p);
    assert.equal(p.node('#attnChecksHead').text, 'Checks · 3 open');
    assert.equal(p.node('#attnItemsCount').html, '<span class="rm-badge red">2 items</span>');
    assert.match(p.node('#attnItems').html, /<span class="rm-inbox-item-foot">Unassigned<\/span>/);

    const q = open();
    listed(q, { total: 1, data: [item('2001')] });
    assert.equal(q.node('#attnItemsCount').html, '<span class="rm-badge red">1 item</span>');
});

check('the item at the right: first seen, the fixes, who has it (me), the buttons', () => {
    const p = open();
    listed(p);
    shown(p);
    const html = panel(p);
    assertWellFormed(html, 'the item');
    assert.match(html, /<div class="rm-inbox-detail-sub">First seen 12 Oct 2026<\/div>/);
    assert.match(html, /<div class="rm-inbox-fixes-title">Suggested fixes<\/div>/);
    assert.match(html, /<label for="attnAssign" class="mb-0">Assigned to<\/label>/);
    assert.match(html, /<option value="">Unassigned<\/option><option value="5" selected>Admin &lt;i&gt;Contoh&lt;\/i&gt; \(me\)<\/option>/);
    assert.match(html, /id="attnDismiss">Dismiss<\/button>/);
    assert.match(html, /id="attnApply"><i class="ti ti-check" aria-hidden="true"><\/i> Apply<\/button>/);

    p.node('(object)').value = 'type:2';
    p.fire('#attnDetail', 'change input[name="attnFix"]');
    assert.match(panel(p), /<option value="">Choose…<\/option>/);
    p.node('(object)').value = 'dismiss';
    p.fire('#attnDetail', 'change input[name="attnFix"]');
    assert.match(panel(p), /id="attnApply"><i class="ti ti-check" aria-hidden="true"><\/i> Dismiss<\/button>/);
});

check('an item set aside says by whom and why, as text; without a name only why', () => {
    const p = open();
    listed(p);
    shown(p, detail({ dismissed: { reason: 'Spare <b>radio</b>', by: 'Petugas Dua', at: '2026-10-12 08:00' } }));
    assert.ok(panel(p).includes('<span>Dismissed by Petugas Dua: Spare &lt;b&gt;radio&lt;/b&gt;</span>'));
    p.page('attnPage').open('867TZB5309');
    shown(p, detail({ dismissed: { reason: 'Spare radio', by: null, at: '2026-10-12 08:00' } }));
    assert.ok(panel(p).includes('<span>Dismissed: Spare radio</span>'));
});

check('who may only read sees "nobody yet"; who may not fix is told why', () => {
    const p = open();
    p.page('attnPage').may = { update: false, fix: false };
    listed(p);
    shown(p, detail({ assignee: null }));
    assert.match(panel(p), /<div class="rm-inbox-detail-foot">Assigned to <span class="rm-inbox-holder">nobody yet<\/span><\/div>/);

    const q = open();
    q.page('attnPage').may = { update: true, fix: false };
    listed(q);
    shown(q);
    assert.match(panel(q), /id="attnApply" disabled title="Fixing radio data needs the Update permission on Register Radio">/);
});

check('a fix without its field, a refusal, setting aside: the words of the toasts and dialogs', () => {
    const p = open();
    listed(p);
    shown(p);
    p.node('#attnField').value = ' ';
    p.fire('#attnDetail', 'click #attnApply');
    assert.equal(p.dialogs.at(-1).options.titleText, 'Fill in the field first.');

    p.node('#attnField').value = '867TZB5311';
    p.fire('#attnDetail', 'click #attnApply');
    p.refuse(p.requests.at(-1), { status: 403, responseJSON: {} });
    assert.deepEqual(p.dialogs.at(-1).args, ['Not applied', 'You are not allowed to do this.', 'error']);

    p.fire('#attnDetail', 'click #attnDismiss');
    const dialog = p.dialogs.at(-1).options;
    assert.equal(dialog.title, 'Dismiss this item?');
    assert.equal(dialog.text, 'The item comes back when the data of its radios changes.');
    assert.equal(dialog.inputLabel, 'Reason');
    assert.equal(dialog.inputValidator(' '), 'Write the reason.');
    p.resolve({ isConfirmed: true, value: { ok: false, xhr: { status: 422, responseJSON: { errors: ['No.'] } } } });
    assert.deepEqual(p.dialogs.at(-1).args, ['Not dismissed', 'No.', 'error']);
});

check('nothing to show, a filter without matches, an item that is gone, a fresh run', () => {
    const p = open();
    listed(p, { counts: {}, total: 0, data: [] });
    assert.equal(textOf(p.node('#attnItems').html), 'No open items in this check.');
    assert.equal(panel(p), '<div class="rm-inbox-empty">No items in this check.</div>');

    p.node('#attnWho').value = 'mine';
    p.fire('#attnWho', 'change');
    listed(p, { counts: {}, total: 0, data: [] });
    assert.equal(textOf(p.node('#attnItems').html), 'No matching items.');

    const q = open();
    listed(q);
    q.refuse(q.requests.at(-1), { status: 404, responseJSON: { message: 'x' } });
    assert.equal(panel(q), '<div class="rm-inbox-empty">This item is gone: its data has been fixed or has changed.</div>');
    q.fire('#attnRefresh', 'click');
    listed(q);
    assert.equal(q.dialogs.at(-1).options.titleText, 'The checks have been run again.');
});

done();
