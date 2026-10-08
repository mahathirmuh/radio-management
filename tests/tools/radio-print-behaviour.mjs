// Runs the inline JavaScript of the print pop-up of the register list (radioPrint) without a
// browser and checks what it does: that every form gets a frame of its own, when printing is
// offered, what is left of a page that is no form, what printing, Ctrl+P and closing do, and that
// a signature that is still on the pad, or that was not printed, is not lost without being asked.
//
// usage: node radio-print-behaviour.mjs <file with the rendered page>
//
// The script is taken from the rendered page and run by page-sandbox.mjs, where "$" is a small
// stand-in for jQuery. The frames the script makes are stand-ins too: a test says what each holds.
// At the end every element the script reached for by id must exist in the rendered page (but the
// frame, which the script makes itself). PHPUnit calls this from RadioCheckoutFormTest.
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const { html, node, page, fire, ids, inside } = loadPage(
    process.argv[2],
    ['const radioPrint = {'],
    { href: 'http://localhost/radio-management/radio' }
);
const { check, done } = checker();
const radioPrint = page('radioPrint');
const document = page('document');
const win = page('window');

// The frames of the pop-up: the one that is in it now is the last that was made and not taken out again.
const frames = () => inside('radioPrintBody');
const calls = [];
const original = document.getElementById;
document.getElementById = id => (id === 'radioPrintFrame' ? (frames().at(-1) ?? null) : original(id));
const prepare = frame => {
    frame.contentWindow.focus = () => calls.push('focus');
    frame.contentWindow.print = () => calls.push('print');
    return frame;
};
/** A frame that holds the form; its pad is open or closed, and it has a signature (the address of its picture) or has not. */
const holdsTheForm = (frame, { padOpen = false, signature = null } = {}) => {
    frame.pad = { hidden: !padOpen };
    frame.signature = signature;
    frame.contentDocument = {
        querySelector: selector => {
            if (selector === 'main.sheet') return {};
            if (selector === '.sig-img' && frame.signature !== null) return { getAttribute: name => (name === 'src' ? frame.signature : null) };
            return null;
        },
        getElementById: id => (id === 'signOverlay' ? frame.pad : null),
    };
};
const holdsSomethingElse = frame => { frame.contentDocument = { querySelector: () => null, getElementById: () => null }; }; // a sign-in page, an error page
const cannotBeRead = frame => { Object.defineProperty(frame, 'contentDocument', { configurable: true, get() { throw new Error('Blocked a frame from another origin'); } }); };
const loads = frame => frame.listeners.load();
const buttonDisabled = () => node('#radioPrintButton').props.disabled;
const noticeShown = () => !node('#radioPrintNotice').classes.has('d-none');
const keys = (key, extra = {}) => {
    let prevented = false;
    fire('(document)', 'keydown', { key, preventDefault() { prevented = true; }, ...extra });

    return prevented;
};
/** Asks the pop-up to close the way Bootstrap does: the answer of the person to the question is what is passed. */
const closing = answers => {
    const asked = [];
    win.confirm = message => { asked.push(message); return answers; };
    let prevented = false;
    fire('#radioPrintModal', 'hide.bs.modal', { preventDefault() { prevented = true; } });

    return { asked: asked.length, prevented };
};
const address = at => `/radio-management/radio/${at}/checkout-form`;
const formIn = (at, options) => {
    radioPrint.open(address(at), 'x');
    const [frame] = frames();
    prepare(frame);
    holdsTheForm(frame, options);
    loads(frame);

    return frame;
};

check('the pop-up makes a frame for the form it is given and does not offer printing before it is there', () => {
    radioPrint.open(address(7), 'ID 11 · 867TZB5309');
    const [frame, ...others] = frames();
    assert.equal(others.length, 0);
    assert.deepEqual([frame.tag, frame.id, frame.src, frame.title], ['iframe', 'radioPrintFrame', address(7), 'IT Equipment Checkout Form']);
    assert.equal(node('#radioPrintLabel').text, 'ID 11 · 867TZB5309');
    assert.equal(node('#radioPrintModal').modal, 'show');
    assert.equal(buttonDisabled(), true);
    assert.equal(noticeShown(), false);
    assert.equal(node('#radioPrintNotice').text, '');
    assert.equal(radioPrint.url, address(7));

    // Another form is another frame, and the one before is gone: a frame whose address is changed fills the Back button's history.
    radioPrint.open(address(8));
    assert.equal(frames().length, 1);
    assert.equal(frames()[0].src, address(8));
    assert.notEqual(frames()[0], frame);
    // A radio without an ID or a serial number has nothing to say in the label, and that is no error.
    assert.equal(node('#radioPrintLabel').text, '');
});

check('printing is offered when the frame holds the form, and a frame that was replaced loads for no one', () => {
    radioPrint.open(address(7), 'x');
    const [frame] = frames();
    holdsTheForm(frame);
    loads(frame);
    assert.equal(buttonDisabled(), false);
    assert.equal(noticeShown(), false);
    assert.deepEqual(frames(), [frame], 'the form stays');

    // A second form is asked for while the first is still arriving; the first then loads (as something that is no form,
    // say): it must not take the second one out of the pop-up, nor say anything.
    radioPrint.open(address(8), 'y');
    const [second] = frames();
    assert.notEqual(second, frame);
    holdsSomethingElse(frame);
    loads(frame);
    assert.deepEqual(frames(), [second]);
    assert.deepEqual([buttonDisabled(), noticeShown()], [true, false]);
    holdsTheForm(second);
    loads(second);
    assert.equal(buttonDisabled(), false);
});

check('a page that is no form is not left in the pop-up: the pop-up says what to do instead', () => {
    for (const makeUnreadable of [holdsSomethingElse, cannotBeRead]) {
        radioPrint.open(address(7), 'x');
        const [frame] = frames();
        makeUnreadable(frame);
        loads(frame);
        // The sign-in page, a 403 or a 404 would show their own links and run their own redirects inside the pop-up.
        assert.deepEqual(frames(), [], 'the frame is taken out');
        assert.equal(buttonDisabled(), true);
        assert.equal(noticeShown(), true);
        assert.match(node('#radioPrintNotice').text, /^Form tidak bisa ditampilkan\. Sesi Anda mungkin sudah berakhir, radio ini sudah dihapus, atau Anda tidak punya izin\. Muat ulang halaman/);
    }
    // Opening again clears the notice.
    radioPrint.open(address(7), 'x');
    assert.equal(noticeShown(), false);
    assert.equal(frames().length, 1);
});

check('printing prints the frame, and Ctrl+P does the same while the pop-up is open', () => {
    formIn(7);

    calls.length = 0;
    radioPrint.print();
    assert.deepEqual(calls, ['focus', 'print']);

    // Ctrl+P (or Cmd+P) is the form's, and not the register's behind the pop-up; any other key is left alone.
    calls.length = 0;
    assert.deepEqual([keys('p', { ctrlKey: true }), keys('P', { metaKey: true }), keys('p'), keys('x', { ctrlKey: true })], [true, true, false, false]);
    assert.deepEqual(calls, ['focus', 'print', 'focus', 'print']);
    // Ctrl+Shift+P and Ctrl+Alt+P are other keys of the browser's (a system dialog, say), as in the form itself.
    assert.deepEqual([keys('p', { ctrlKey: true, shiftKey: true }), keys('p', { ctrlKey: true, altKey: true })], [false, false]);
    assert.equal(calls.length, 4);

    // Where there is no form to print (the frame is still loading, or holds something else), the keys print nothing at all, not the register either.
    radioPrint.open(address(7), 'x');
    prepare(frames()[0]);
    calls.length = 0;
    assert.equal(keys('p', { ctrlKey: true }), true);
    assert.deepEqual(calls, []);
    // And with no frame there is nothing for the button to print either.
    radioPrint.closed();
    assert.doesNotThrow(() => radioPrint.print());
});

check('a signature that is still on the pad is not left off the paper: nothing is printed until it is taken or cancelled', () => {
    const frame = formIn(7, { padOpen: true });
    calls.length = 0;
    radioPrint.print();
    assert.deepEqual(calls, []);
    assert.equal(noticeShown(), true);
    assert.match(node('#radioPrintNotice').text, /^Tanda tangan belum dipakai\. Tekan "Pakai tanda tangan" atau "Batal"/);
    // Ctrl+P is no different, and the register behind is not printed instead.
    assert.equal(keys('p', { ctrlKey: true }), true);
    assert.deepEqual(calls, []);

    // The pad is closed: the form prints, and the notice goes.
    frame.pad.hidden = true;
    radioPrint.print();
    assert.deepEqual(calls, ['focus', 'print']);
    assert.equal(noticeShown(), false);
});

check('closing the pop-up asks only when a signature would be lost: one on the pad, or one that was not printed', () => {
    // Nothing to lose: it closes without a word.
    formIn(7);
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    // A signature that was not printed: asked, and "no" keeps the pop-up open.
    const frame = formIn(7, { signature: 'data:image/png;base64,AAAA' });
    assert.deepEqual(closing(false), { asked: 1, prevented: true });
    assert.deepEqual(closing(true), { asked: 1, prevented: false });
    // Printed: it is on paper, nothing is lost by closing.
    radioPrint.print();
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    // Taken away after printing (the button of the box): there is nothing left to lose.
    frame.signature = null;
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    // Signed again after printing: the new signature was not printed.
    frame.signature = 'data:image/png;base64,BBBB';
    assert.deepEqual(closing(false), { asked: 1, prevented: true });
    // The next form is another form: what was printed of the one before does not count for it, even when the picture is the same.
    radioPrint.print();
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    formIn(8, { signature: 'data:image/png;base64,BBBB' });
    assert.deepEqual(closing(false), { asked: 1, prevented: true });
    // The pad is open (ink that was not taken): asked.
    const open = formIn(7, { padOpen: true });
    assert.deepEqual(closing(false), { asked: 1, prevented: true });
    open.pad.hidden = true;
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    // A frame that cannot be looked into (it went to another address) shows nothing that could be lost.
    cannotBeRead(formIn(7, { signature: 'data:image/png;base64,CCCC' }));
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    // A frame that holds no form, or no frame at all, has nothing to lose.
    radioPrint.open(address(7), 'x');
    holdsSomethingElse(frames()[0]);
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
    radioPrint.closed();
    assert.deepEqual(closing(false), { asked: 0, prevented: false });
});

check('closing the pop-up takes the frame out, and what loads afterwards changes nothing', () => {
    // The focus goes back to what had it: the button of the row.
    const row = { focused: 0, focus() { this.focused++; } };
    document.activeElement = row;
    document.contains = element => element === row;
    radioPrint.open(address(7), 'x');
    const [frame] = frames();
    holdsTheForm(frame);
    loads(frame);
    assert.equal(buttonDisabled(), false);

    fire('#radioPrintModal', 'hidden.bs.modal');
    assert.equal(radioPrint.url, null);
    assert.deepEqual(frames(), []);
    assert.equal(row.focused, 1);
    // The frame that was in it, loading late, is no form of a pop-up that is closed: no notice, no button.
    holdsSomethingElse(frame);
    loads(frame);
    assert.equal(buttonDisabled(), false);
    assert.equal(noticeShown(), false);
    // Ctrl+P is the browser's again.
    assert.equal(keys('p', { ctrlKey: true }), false);

    // A row that is no longer in the page (the table was drawn again) is not given the focus.
    document.contains = () => false;
    radioPrint.open(address(7), 'x');
    fire('#radioPrintModal', 'hidden.bs.modal');
    assert.equal(row.focused, 1);
    delete document.activeElement;
    delete document.contains;
});

check('what the pop-up needs is in the page, and it cannot be closed by accident', () => {
    for (const id of [...ids()].filter(id => id !== 'radioPrintFrame')) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(html), `the page has no element with id="${id}"`);
    }
    assert.ok(/id="radioPrintBody"/.test(html), 'the frames go into the body of the pop-up');
    assert.ok(!/<iframe/.test(html), 'the frame is made by the script, one for every form');
    assert.ok(/onclick="radioPrint\.print\(\)"/.test(html), 'the button prints');
    // What is typed (and signed) in the form is lost when the pop-up closes: a click beside it or Esc must not close it.
    assert.ok(/id="radioPrintModal"[^>]*data-bs-backdrop="static"[^>]*data-bs-keyboard="false"/.test(html.replace(/\s+/g, ' ')), 'a static pop-up');
    assert.ok(/id="radioPrintNotice" role="alert"/.test(html), 'the notice is announced');
});

done();
