// Runs the script of the dialog "Serah terima" (radioHandover) of a radio's detail without a browser and checks what it
// does: confirming waits for a receiver, a BA and ink on the pad; the receiver is looked up in the employee directory and
// picked; what is sent; and once it is recorded, the checkout form opens with the signature in it (radioPrint).
//
// usage: node radio-handover-behaviour.mjs <file with the rendered detail of a radio, for Super Admin>
//
// Like the other *-behaviour.mjs checkers: the scripts are taken from the rendered HTML and run by page-sandbox.mjs.
// PHPUnit calls this from RadioHandoverTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage } from './page-sandbox.mjs';

const markers = ['function debounce(', 'const rmFeedback = {', 'const RadioUi = {', 'const radioPrint = {', 'const radioHandover = {'];
const open = () => {
    const p = loadPage(process.argv[2], markers, { href: 'http://localhost/radio-management/radio/1/detail' });
    // (The detail's own script names the radio; the dialog only needs the name.)
    p.page("globalThis.radioLabel = 'ID 1044 · 871TQC2208'");
    return p;
};
const { check, done } = checker();
const disabled = p => p.node('#handoverConfirm').props.disabled;
const fill = (p, { receiver = 'Penerima Baru', ba = 'BA2300412', ink = 0.2 } = {}) => {
    p.node('#handoverReceiver').value = receiver;
    p.node('#handoverBa').value = ba;
    p.page('radioHandover').pad.length = ink;
    p.page('radioHandover').ready();
};

check('the dialog opens empty; confirming waits for a receiver, a BA and a signature', () => {
    const p = open();
    p.page('radioHandover').open();
    assert.equal(p.node('#radioHandoverModal').modal, 'show');
    assert.equal(p.node('#handoverReceiver, #handoverBa, #handoverPo').value, '');
    assert.equal(p.node('#handoverError').classes.has('d-none'), true);
    assert.equal(disabled(p), true);

    fill(p, { ink: 0 });
    assert.equal(disabled(p), true, 'no ink');
    fill(p, { ink: 0.05 });
    assert.equal(disabled(p), true, 'a speck is no signature');
    fill(p, { receiver: '  ' });
    assert.equal(disabled(p), true, 'no receiver');
    fill(p, { ba: '' });
    assert.equal(disabled(p), true, 'no BA');
    fill(p);
    assert.equal(disabled(p), false);
});

check('the receiver is looked up after a pause from three letters, shown as text, and picked with their number', () => {
    const p = open();
    p.node('#handoverReceiver').value = 'Bu';
    p.fire('#handoverReceiver', 'input', {});
    p.runTimers();
    assert.equal(p.requests.length, 0, 'not for two letters');

    p.node('#handoverReceiver').value = 'Budi';
    p.fire('#handoverReceiver', 'input', {});
    p.runTimers();
    const asked = p.requests.at(-1);
    assert.match(asked.url, /\/radio-management\/radio\/employees$/);
    assert.deepEqual({ ...asked.data }, { q: 'Budi' });
    p.answer(asked, { available: true, failed: false, data: [
        { employee_id: '20190342', name: 'Budi <b>Santoso</b>', email: 'budi@example.test' },
        { employee_id: '20190343', name: 'Budi Lain', email: '' },
    ] });
    const html = p.node('#handoverMatches').html;
    assertWellFormed(html, 'the people found');
    assert.match(html, /<button type="button" class="rm-match" data-pick="0"><span class="rm-match-name">Budi &lt;b&gt;Santoso&lt;\/b&gt;<\/span><span class="rm-match-line">budi@example\.test<\/span><span class="rm-match-id">20190342<\/span><\/button>/);

    p.node('(object)').attrs['data-pick'] = '1';
    p.fire('#handoverMatches', 'click [data-pick]', {});
    assert.equal(p.node('#handoverReceiver').value, 'Budi Lain');
    assert.equal(p.page('radioHandover').employee, '20190343');
    assert.equal(p.node('#handoverMatches').html, '');
});

check('what is sent: the receiver and their number, BA, PO, accessories ticked, condition, place, department, company, signature', () => {
    const p = open();
    p.page('radioHandover').employee = '20190342';
    fill(p);
    p.node('#handoverPo').value = ' PO 4500187 ';
    p.node('input[name="handover_accessories"]:checked').value = 'Baterai';
    p.node('#handoverCondition').value = 'layak';
    p.node('#handoverPlacement').value = 'di_user';
    p.node('#handoverDepartment').value = '3';
    p.node('#handoverCompany').value = '';
    p.page("radioHandover.picture = () => 'data:image/png;base64,AAAA'");
    p.fire('#handoverConfirm', 'click', {});
    const sent = p.requests.at(-1);
    assert.match(sent.url, /\/radio-management\/radio\/\d+\/handover$/);
    assert.equal(sent.type, 'POST');
    assert.deepEqual(JSON.parse(JSON.stringify(sent.data)), {
        _method: 'PUT', receiver: 'Penerima Baru', employee_id: '20190342', ba: 'BA2300412', po: 'PO 4500187', accessories: ['Baterai'],
        condition: 'layak', placement: 'di_user', department_id: '3', company_id: '', signature: 'data:image/png;base64,AAAA',
    });
    assert.equal(p.node('#handoverConfirm').props.disabled, true, 'busy while it is sent');

    // Recorded: the dialog closes, a toast says so, and the form opens with the signature in it; closed, the page reloads.
    p.answer(sent, { message: 'Radio ID 1044 diserahkan ke Penerima Baru.', checkout_form: '/radio-management/radio/1/checkout-form' });
    assert.equal(p.node('#radioHandoverModal').modal, 'hide');
    assert.equal(p.dialogs.at(-1).options.titleText, 'Radio ID 1044 diserahkan ke Penerima Baru.');
    assert.equal(p.page('radioPrint').url, '/radio-management/radio/1/checkout-form');
    assert.equal(p.page('radioPrint').signing, 'data:image/png;base64,AAAA');
    assert.equal(typeof p.page('radioPrint').after, 'function');
    assert.equal(p.node('#radioPrintModal').modal, 'show');
});

check('a refusal is said in the dialog, which stays open', () => {
    const p = open();
    fill(p);
    p.fire('#handoverConfirm', 'click', {});
    p.refuse(p.requests.at(-1), { status: 422, responseJSON: { message: 'Input tidak valid', errors: ['Tanda tangan masih kosong.'] } });
    assert.equal(p.node('#handoverError').classes.has('d-none'), false);
    assert.equal(p.node('#handoverErrorText').text, 'Tanda tangan masih kosong.');
    assert.notEqual(p.node('#radioHandoverModal').modal, 'hide');
    assert.equal(p.node('#handoverConfirm').props.disabled, false);
    // Not ready: nothing is sent.
    fill(p, { ink: 0 });
    const before = p.requests.length;
    p.fire('#handoverConfirm', 'click', {});
    assert.equal(p.requests.length, before);
});

check('every element the script reaches for by id is in the page', () => {
    const p = open();
    p.page('radioHandover').open();
    fill(p);
    p.page('radioHandover').payload();
    const used = p.ids();
    assert.ok(used.size >= 12, `only ${used.size} ids seen`);
    for (const id of used) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(p.html), `the page has no element with id="${id}"`);
    }
});

done();
