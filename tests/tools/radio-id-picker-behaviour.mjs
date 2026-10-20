// Runs the Radio ID part of the radio form (radioForm: previewId, checkId, idWords, showIdStatus, renderPicker, openPicker,
// useId) without a browser and checks what it does: the MDC Code with its F in red, what is said under the field of an
// ID that cannot be used (and the button of the ID to use instead), the picker with the IDs around and the next free
// ones, and the ID chosen going into the field.
//
// usage: node radio-id-picker-behaviour.mjs <file with the rendered page "Register Radio">
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from RadioIdPickerTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'const RadioUi = {', 'const radioForm = {'];
// (The form sets itself up when the page is ready: $(radioForm.init).)
const open = () => loadPage(process.argv[2], markers, { href: 'http://localhost/radio-management/radio' });
const { check, done } = checker();

const type = (p, value) => {
    p.node('#dmr_id').value = value;
    p.page('radioForm').previewId();
};
const shown = (p, selector) => !p.node(selector).classes.has('d-none');
const answerOf = (other = {}) => ({
    id: 751, mdc: '2EF', status: 'hex_f', own: false, holder: null,
    suggested: { id: 768, mdc: '300' }, skipped: { from: 751, to: 767 },
    strip: [
        { id: 750, mdc: '2EE', state: 'free', selected: false },
        { id: 751, mdc: '2EF', state: 'hex_f', selected: true },
        { id: 752, mdc: '2F0', state: 'hex_f', selected: false },
        { gap: 14 },
        { id: 767, mdc: '2FF', state: 'hex_f', selected: false },
        { id: 768, mdc: '300', state: 'free', selected: false },
    ],
    next: [{ id: 768, mdc: '300' }, { id: 769, mdc: '301' }],
    ...other,
});

// ---------------------------------------------------------------- under the field
check('an ID with F is said at once, with its MDC Code; the register is asked after a pause, and says what to use', () => {
    const p = open();
    type(p, '751');
    assert.equal(p.node('#radioMdcPreview').html, 'MDC 2E<span class="rm-hex-f">F</span>');
    assert.equal(shown(p, '#radioIdStatus'), true);
    assert.equal(p.node('#radioIdStatusTitle').text, '751 tidak bisa dipakai: MDC 2EF mengandung F');
    assert.equal(shown(p, '#radioIdUse'), false, 'nothing to offer before the register has answered');
    assert.equal(p.requests.length, 0, 'not before the pause');

    p.runTimers();
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/radio\/id-check$/);
    assert.deepEqual({ ...asked.data }, { id: '751', except: '' });
    p.answer(asked, { data: answerOf() });
    assert.equal(p.node('#radioIdStatusText').text, 'ID 751 sampai 767 dilewati. ID berikutnya yang bisa dipakai: 768 (MDC 300).');
    assert.equal(shown(p, '#radioIdUse'), true);
    assert.equal(p.node('#radioIdUse').text, 'Pakai 768');
    assert.equal(p.node('#radioIdUse').attrs['data-use'], '768');
});

check('an ID that is taken, kept or free is said as such; an answer to an ID typed over is dropped', () => {
    const p = open();
    type(p, '1042');
    assert.equal(shown(p, '#radioIdStatus'), false, 'no F: nothing until the register has answered');
    p.runTimers();
    const asked = p.requests.at(-1);
    type(p, '1043');
    p.answer(asked, { data: answerOf({ id: 1042, mdc: '412', status: 'taken' }) });
    assert.equal(shown(p, '#radioIdStatus'), false, 'the answer of 1042 is not shown under 1043');

    p.runTimers();
    p.answer(p.requests.at(-1), { data: answerOf({
        id: 1043, mdc: '413', status: 'taken', skipped: null, suggested: { id: 1047, mdc: '417' },
        holder: { id: 9, label: 'ID 1043 · 867TZB5309', user: 'Pemakai Satu', department: 'Process Plant' },
    }) });
    assert.equal(p.node('#radioIdStatusTitle').text, '1043 sudah dipakai');
    assert.equal(p.node('#radioIdStatusText').text, 'Dipakai ID 1043 · 867TZB5309, Pemakai Satu (Process Plant). Radio ID unik di antara radio aktif. ID berikutnya yang bisa dipakai: 1047 (MDC 417).');

    const words = p.page('radioForm').idWords;
    assert.deepEqual(JSON.parse(JSON.stringify(words(answerOf({ id: 900, mdc: '384', status: 'reserved', suggested: null, holder: { label: 'ex-ID 900 · 867TZB0900', user: 'tanpa ID', department: null } })))),
        { title: '900 dicadangkan', text: 'ex-ID 900 · 867TZB0900, tanpa ID belum punya ID dan kemungkinan masih memakai 900. Cek radio tersebut dulu.' });
    assert.equal(words(answerOf({ id: 1053, mdc: '41D', status: 'ok', suggested: null })).title, '1053 bebas');
    assert.equal(words(answerOf({ id: 1200, mdc: '4B0', status: 'ok', own: true, suggested: null })).title, '1200 adalah ID radio ini');
    assert.match(words(answerOf({ own: true })).text, /^ID seperti ini seharusnya tidak dipakai: program ulang radionya ke ID lain/);

    // Free: nothing under the field.
    type(p, '1053');
    p.runTimers();
    p.answer(p.requests.at(-1), { data: answerOf({ id: 1053, mdc: '41D', status: 'ok', suggested: null, skipped: null }) });
    assert.equal(shown(p, '#radioIdStatus'), false);
    // Emptied: nothing either, and nothing asked.
    const before = p.requests.length;
    type(p, '');
    p.runTimers();
    assert.equal(p.requests.length, before);
    assert.equal(p.node('#radioMdcPreview').html, '');
});

// ---------------------------------------------------------------- the picker
check('the picker: whether the ID can be used, the IDs around it (free ones to click), and the next free IDs', () => {
    const p = open();
    type(p, '751');
    p.runTimers();
    p.answer(p.requests.at(-1), { data: answerOf() });
    const html = p.node('#radioNextIdMenu').html;
    assertWellFormed(html, 'the picker');
    assert.match(html, /<div class="rm-idp-status is-bad"><i class="ti ti-alert-triangle" aria-hidden="true"><\/i><div class="rm-idp-words"><div class="rm-idp-title">751 tidak bisa dipakai: MDC 2EF mengandung F<\/div>/);
    assert.match(html, /<button type="button" class="btn-rm sm primary" data-use="768">Pakai 768<\/button>/);
    assert.match(html, /<button type="button" class="rm-idp-cell is-free" data-use="750"><b>750<\/b><small>2EE<\/small><\/button>/);
    assert.match(html, /<span class="rm-idp-cell is-hex is-selected"><b>751<\/b><small>2EF<\/small><\/span>/);
    assert.match(html, /<span class="rm-idp-cell is-gap"><b>···<\/b><small>14 lagi<\/small><\/span>/);
    assert.match(html, /<button type="button" class="rm-idchip" data-use="769"><b>769<\/b> <span class="radio-mono">301<\/span><\/button>/);
    assert.match(html, /MDC Code adalah Radio ID dalam heksadesimal/);
});

check('opened with no ID typed, it asks for the lowest free IDs; an ID chosen goes into the field and closes it', () => {
    const p = open();
    p.node('#dmr_id').value = '';
    p.fire('#radioNextIdButton', 'show.bs.dropdown', {});
    assert.match(p.node('#radioNextIdMenu').html, /Memuat…/);
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/radio\/next-id$/);
    p.answer(asked, { data: [{ id: 11, mdc: 'B' }, { id: 12, mdc: 'C' }] });
    assert.match(p.node('#radioNextIdMenu').html, /data-use="11"><b>11<\/b> <span class="radio-mono">B<\/span>/);
    assert.doesNotMatch(p.node('#radioNextIdMenu').html, /rm-idp-status/);

    p.node('(object)').attrs['data-use'] = '12';
    p.fire('#radioNextIdMenu, #radioIdStatus', 'click [data-use]', { preventDefault() {} });
    assert.equal(p.node('#dmr_id').value, '12');
    assert.deepEqual(p.node('#dmr_id').triggered, ['input']);
    assert.equal(p.node('#radioNextIdButton').dropdown, 'hide');
    // Something that is no ID is not put in.
    p.page('radioForm').useId('abc');
    assert.equal(p.node('#dmr_id').value, '12');
});

check('every element the script reaches for by id is in the page', () => {
    const p = open();
    type(p, '751');
    p.runTimers();
    p.answer(p.requests.at(-1), { data: answerOf() });
    const used = p.ids();
    assert.ok(used.size >= 8, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
});

done();
