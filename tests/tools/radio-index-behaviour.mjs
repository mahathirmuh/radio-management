// Runs the inline JavaScript of the register list (Register Radio) without a browser and checks that
// the buttons of a row are tied to what they open: the row writes the address and the label into the
// button, and a click on the button hands them to the print pop-up, as the buttons of the row have
// always handed the id to the form and the archive.
//
// usage: node radio-index-behaviour.mjs <file with the rendered page>
//
// The page's own scripts (the list, the form, the archive pop-up, the print pop-up and their helpers)
// are taken from the rendered HTML and run by page-sandbox.mjs, where "$" is a small stand-in for
// jQuery. PHPUnit calls this from RadioCheckoutFormTest.
import assert from 'node:assert/strict';
import { checker, loadPage, textOf } from './page-sandbox.mjs';

const { node, page, fire } = loadPage(
    process.argv[2],
    ['function debounce(', 'function renderTableWithFeatures(', 'const RadioUi = {', 'const radioFilter = {', 'const radioForm = {', 'const radioArchive = {', 'const radioPrint = {', 'const radioTable = {'],
    { href: 'http://localhost/radio-management/radio' }
);
const { check, done } = checker();
const [radioTable, radioPrint, radioForm, radioArchive] = ['radioTable', 'radioPrint', 'radioForm', 'radioArchive'].map(name => page(name));

// A row of the list, as far as the buttons need it.
const row = (extra = {}) => ({
    id: 5, dmr_id: 11, serial_number: '867TEST0001', archived: false,
    detail_url: 'http://localhost/radio-management/radio/5/detail', print_url: 'http://localhost/radio-management/radio/5/checkout-form', ...extra,
});
const buttons = html => [...html.matchAll(/<(a|button)\b([^>]*)>/g)].map(([, tag, attributes]) => ({
    tag,
    attrs: Object.fromEntries([...attributes.matchAll(/([\w-]+)="([^"]*)"/g)].map(([, name, value]) => [name, textOf(value)])),
}));
const actions = extra => radioTable.columns.find(column => column.key === 'actions').render(row(extra));

/** What jQuery's .data() makes of an attribute: "5" is the number 5, "true" is true, "null" is null, {...} and [...] are parsed; other text stays text. */
const coerce = value => {
    if (value === 'true') return true;
    if (value === 'false') return false;
    if (value === 'null') return null;
    if (value === String(+value)) return +value;
    if (/^(?:\{[\w\W]*\}|\[[\w\W]*\])$/.test(value)) {
        try {
            return JSON.parse(value);
        } catch (error) {
            return value;
        }
    }

    return value;
};

/** What a click on the button does: the button's data-* attributes are what .data() reads, and the three pop-ups say what they were asked. */
const clicks = button => {
    const calls = [];
    const wasForm = radioForm.open;
    const wasArchive = radioArchive.open;
    const wasRestore = radioArchive.restore;
    const wasPrint = radioPrint.open;
    radioForm.open = (...args) => calls.push(['form', ...args]);
    radioArchive.open = (...args) => calls.push(['archive', ...args]);
    radioArchive.restore = (...args) => calls.push(['restore', ...args]);
    radioPrint.open = (...args) => calls.push(['print', ...args]);
    try {
        const data = Object.fromEntries(Object.entries(button.attrs).filter(([name]) => name.startsWith('data-')).map(([name, value]) => [name.slice(5), coerce(value)]));
        node('(object)').data = data;
        fire('#radioTable', 'click button[data-action]');
    } finally {
        Object.assign(radioForm, { open: wasForm });
        Object.assign(radioArchive, { open: wasArchive, restore: wasRestore });
        Object.assign(radioPrint, { open: wasPrint });
    }

    return calls;
};

check('a row has the buttons detail, print, edit and archive, in that order, and the print button has a name', () => {
    const found = buttons(actions());
    assert.deepEqual(found.map(button => button.attrs['data-bs-title']), ['Detail & Riwayat', 'Cetak form (IT Equipment Checkout Form)', 'Edit', 'Arsipkan']);
    const print = found[1];
    assert.equal(print.tag, 'button');
    assert.equal(print.attrs['aria-label'], 'Cetak form');
    assert.equal(print.attrs['data-action'], 'print');
});

check('the print button carries the address of the form of its row and the label of its radio', () => {
    const [, print] = buttons(actions());
    assert.equal(print.attrs['data-url'], 'http://localhost/radio-management/radio/5/checkout-form');
    assert.equal(print.attrs['data-label'], 'ID 11 · 867TEST0001');
    // A radio without an ID (archived, say) is labelled by its serial number alone, and what is in the label cannot break the markup.
    const [, archived] = buttons(actions({ dmr_id: null, serial_number: '<b>"x"</b> & 1' }));
    assert.equal(archived.attrs['data-label'], '<b>"x"</b> & 1');
    assert.match(actions({ dmr_id: null, serial_number: '"><img src=x onerror=alert(1)>' }), /data-label="&quot;&gt;&lt;img src=x onerror=alert\(1\)&gt;"/);
});

check('a click on the print button hands that address and label to the pop-up, and nothing else opens', () => {
    const [, print] = buttons(actions());
    assert.deepEqual(clicks(print), [['print', 'http://localhost/radio-management/radio/5/checkout-form', 'ID 11 · 867TEST0001']]);
});

check('the other buttons of the row open what they always opened', () => {
    const [, , edit, archive] = buttons(actions());
    // (the id is a number by the time it gets there, as jQuery reads it from the attribute)
    assert.deepEqual(clicks(edit), [['form', 'edit', 5]]);
    assert.deepEqual(clicks(archive), [['archive', 5, 'ID 11 · 867TEST0001']]);
    const [, , , restore] = buttons(actions({ archived: true }));
    assert.deepEqual(clicks(restore), [['restore', 5, 'ID 11 · 867TEST0001']]);
});

done();
