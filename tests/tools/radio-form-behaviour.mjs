// Runs the inline JavaScript of the printed form (IT Equipment Checkout Form) that deals with its
// fields, without a browser, and checks what it does: the fields become editable as plain text,
// Enter does not break a one-line field, pasted text comes in as plain text (on one line where the
// field is one line), a click on a word of a pair to choose from ("HT" / "RIG") strikes it out or
// takes the line away again, a form that has grown longer than a page says so and is made smaller
// for the printing, and Ctrl+P prints the form. (The pad for signing has its own script and its
// own checker.)
//
// usage: node radio-form-behaviour.mjs <file with the rendered form>
//
// The form is a page of its own without jQuery, so it gets a small stand-in for the document: the
// fields and words the markup has, each remembering what the script did to it. It is run for a
// browser that has contenteditable="plaintext-only" and for one that has not. PHPUnit calls this
// from RadioCheckoutFormTest.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { checker } from './page-sandbox.mjs';

const html = fs.readFileSync(process.argv[2], 'utf8');
const blocks = [...html.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script\s*>/gi)].map(match => match[1]);
const scripts = blocks.filter(block => block.includes("'contenteditable'"));
assert.equal(scripts.length, 1, `expected one inline script that makes the fields editable, found ${scripts.length} of ${blocks.length}`);

/** An element as the script reaches it: its classes (from the markup), its attributes, and the handlers it was given. */
const element = classes => {
    const classSet = new Set(classes);
    const attrs = {};
    const listeners = {};

    return {
        attrs,
        listeners,
        classes: classSet,
        classList: {
            contains: name => classSet.has(name),
            toggle: name => (classSet.delete(name) ? false : classSet.add(name).has(name)),
        },
        setAttribute: (name, value) => { attrs[name] = String(value); },
        addEventListener: (event, handler) => { listeners[event] = handler; },
    };
};

/** The page with the markup's fields (.f, one line when it also has .line) and words (.alt), and the script run on it. */
const run = (supportsPlaintextOnly, { height = 900, resizeObserver = true } = {}) => {
    const fields = [...html.matchAll(/<span class="(f(?:\s[^"]*)?)"/g)].map(match => element(match[1].split(/\s+/)));
    const words = [...html.matchAll(/<span class="(alt(?:\s[^"]*)?)"/g)].map(match => element(match[1].split(/\s+/)));
    const inserted = [];
    // The sheet is as tall as the test says; the warning starts as "shown" and is the script's to hide. A browser does not scale a sheet
    // exactly with "zoom" (lines of text are rounded): a sheet zoomed by the share it is too tall comes out a little taller than that.
    const sheet = {
        height,
        drift: 28,
        style: { zoom: '' }, // what a browser answers for a style that is not set
        getBoundingClientRect() {
            const zoom = Number(this.style.zoom || 1);

            return { height: this.height * zoom + this.drift * (1 - zoom) };
        },
    };
    const warning = { hidden: false };
    const scale = { textContent: '' };
    const pad = { hidden: true };
    const note = { hidden: true };
    const documentListeners = {};
    const windowListeners = {};
    const printed = [];
    const observers = [];
    const document = {
        createElement: () => {
            const probe = element([]);
            // What a browser answers for the mode it does not know: it keeps the default.
            Object.defineProperty(probe, 'contentEditable', {
                get: () => (supportsPlaintextOnly && probe.attrs.contenteditable === 'plaintext-only' ? 'plaintext-only' : 'inherit'),
            });

            return probe;
        },
        querySelectorAll: selector => ({ '.f': fields, '.alt': words })[selector] ?? [],
        querySelector: selector => (selector === 'main.sheet' ? sheet : null),
        getElementById: id => ({ fitWarning: warning, fitScale: scale, signOverlay: pad, signNote: note })[id] ?? null,
        addEventListener: (event, handler) => { documentListeners[event] = handler; },
        execCommand: (command, ui, text) => { inserted.push([command, ui, text]); return true; },
    };
    const window = { addEventListener: (event, handler) => { windowListeners[event] = handler; }, print: () => printed.push('print') };
    const context = { document, window };
    if (resizeObserver) {
        context.ResizeObserver = class { constructor(callback) { observers.push({ callback, observed: [] }); this.at = observers.length - 1; } observe(target) { observers[this.at].observed.push(target); } };
    }
    vm.runInContext(scripts[0], vm.createContext(context), { filename: 'checkout-form-script.js' });

    return { fields, words, inserted, sheet, warning, scale, pad, note, documentListeners, windowListeners, printed, observers };
};
const press = (field, key) => {
    let prevented = false;
    field.listeners.keydown?.({ key, preventDefault() { prevented = true; } });

    return prevented;
};
const paste = (field, text) => {
    let prevented = false;
    field.listeners.paste({ preventDefault() { prevented = true; }, clipboardData: { getData: type => (type === 'text' ? text : '') } });

    return prevented;
};

const { check, done } = checker();
const modern = run(true);
const older = run(false);
const oneLine = modern.fields.filter(field => field.classes.has('line'));
const ruled = modern.fields.filter(field => !field.classes.has('line'));
const page = 273 * 96 / 25.4; // A4 less 12 mm above and below, in CSS pixels

check('the form has its fields and the words that can be struck out', () => {
    // 27 fields of one line, the box "Other" and the note; the pairs to choose from: desktop or laptop, HT or rig, mouse or keyboard.
    assert.deepEqual([modern.fields.length, oneLine.length, ruled.length, modern.words.length], [29, 27, 2, 6]);
});

check('every field is edited as plain text, with a mode the browser has', () => {
    for (const field of modern.fields) {
        assert.deepEqual([field.attrs.contenteditable, field.attrs.spellcheck], ['plaintext-only', 'false']);
    }
    for (const field of older.fields) {
        assert.deepEqual([field.attrs.contenteditable, field.attrs.spellcheck], ['true', 'false']);
    }
});

check('Enter does not break a one-line field and does in the others', () => {
    for (const field of oneLine) {
        assert.equal(press(field, 'Enter'), true);
        assert.equal(press(field, 'a'), false);
    }
    for (const field of ruled) {
        assert.equal(field.listeners.keydown, undefined, 'a field of several lines has no handler for keys');
    }
});

check('pasted text comes in as plain text, on one line where the field is one line', () => {
    for (const { fields, inserted } of [modern, older]) {
        inserted.length = 0;
        const [line] = fields.filter(field => field.classes.has('line'));
        const [ruledField] = fields.filter(field => !field.classes.has('line'));
        assert.equal(paste(line, 'Budi\r\n  Santoso\nSitus A'), true, 'what the browser would paste is not used');
        assert.equal(paste(ruledField, 'Baris satu\nBaris dua'), true);
        assert.deepEqual(inserted, [['insertText', false, 'Budi Santoso Situs A'], ['insertText', false, 'Baris satu\nBaris dua']]);
    }
});

check('a click on a word of a pair to choose from strikes it out, and another click takes the line away', () => {
    // The pairs are (desktop, laptop), (HT, rig), (mouse, keyboard): HT is the third word.
    const [, , word, other] = modern.words;
    const before = word.classes.has('struck');
    word.listeners.click();
    assert.equal(word.classes.has('struck'), !before);
    word.listeners.click();
    assert.equal(word.classes.has('struck'), before);
    // The word next to it is its own: one struck does not strike the other.
    const otherBefore = other.classes.has('struck');
    word.listeners.click();
    assert.equal(other.classes.has('struck'), otherBefore);
    // All six can be clicked.
    assert.ok(modern.words.every(each => typeof each.listeners.click === 'function'));
});

check('a form that has grown past one A4 page says so, with the size it will be printed at, and takes the warning back when it is short enough', () => {
    const { sheet, warning, scale, documentListeners, windowListeners } = modern;
    /** The size the form is printed at when it is as tall as this. */
    const printedAt = height => {
        sheet.height = height;
        windowListeners.beforeprint();
        const zoom = Number(sheet.style.zoom);
        windowListeners.afterprint();

        return Math.floor(zoom * 100);
    };
    // It is looked at when the script runs (the warning is the script's to hide), and again when something is typed, the form has loaded
    // or the window has changed: the height is read each time.
    assert.equal(warning.hidden, true);
    sheet.height = page + 40;
    documentListeners.input();
    assert.equal(warning.hidden, false);
    assert.equal(sheet.style.zoom, '', 'looking at the form leaves it as big as it was');
    assert.equal(scale.textContent, String(printedAt(page + 40)), 'the size it says is the size it is printed at');
    // A page and one pixel is still a page; two pixels is not.
    sheet.height = page + 1;
    documentListeners.input();
    assert.equal(warning.hidden, true);
    sheet.height = page + 2;
    documentListeners.input();
    assert.equal(warning.hidden, false);
    sheet.height = page;
    documentListeners.input();
    assert.equal(warning.hidden, true);
    sheet.height = page + 40;
    windowListeners.load();
    assert.equal(warning.hidden, false);
    sheet.height = page - 5;
    windowListeners.resize();
    assert.equal(warning.hidden, true);
    // A form that arrives too long says so at once, with its own size.
    const long = run(true, { height: 1200 });
    assert.equal(long.warning.hidden, false);
    long.windowListeners.beforeprint();
    assert.equal(long.scale.textContent, String(Math.floor(Number(long.sheet.style.zoom) * 100)));
    // The warning is not part of the paper, and it is announced.
    assert.ok(/class="hint warn no-print" id="fitWarning" role="alert" hidden/.test(html));
});

check('a frame that is shown after the form has loaded is measured when it gets a size', () => {
    // The pop-up is not on the screen yet when the form arrives: the form has no height then, and gets it later.
    const shy = run(true, { height: 0 });
    assert.equal(shy.warning.hidden, true);
    assert.equal(shy.observers.length, 1);
    assert.deepEqual(shy.observers[0].observed, [shy.sheet]);
    shy.sheet.height = 1200;
    shy.observers[0].callback();
    assert.equal(shy.warning.hidden, false);
    // A browser without ResizeObserver still has the window's resize.
    const old = run(true, { height: 0, resizeObserver: false });
    old.sheet.height = 1200;
    old.windowListeners.resize();
    assert.equal(old.warning.hidden, false);
});

check('a form that is too long is made smaller for the printing so that it is one page long, and as big as it was after', () => {
    const { sheet, windowListeners, documentListeners } = modern;
    for (const height of [page + 40, 1200, 1500, 2000, 4000]) {
        sheet.height = height;
        windowListeners.beforeprint();
        const zoom = Number(sheet.style.zoom);
        const laidOut = sheet.getBoundingClientRect().height;
        assert.ok(zoom > 0 && zoom < 1, `height ${height}: zoom ${zoom}`);
        // As tall as the page, not taller (a second page would have no margin above and below), and not smaller than it has to be.
        assert.ok(laidOut <= page + 0.5, `height ${height}: ${laidOut} is more than a page`);
        assert.ok(laidOut >= page * 0.99, `height ${height}: ${laidOut} is smaller than it has to be`);
        windowListeners.afterprint();
        assert.equal(sheet.style.zoom, '');
    }
    // One that fits is not touched, and one a pixel over a page is not either; nor is one that has no height (a frame that is not shown).
    for (const height of [0, 900, page, page + 1]) {
        sheet.height = height;
        windowListeners.beforeprint();
        assert.equal(sheet.style.zoom, '', `height ${height}`);
        windowListeners.afterprint();
    }
    // While the browser has the sheet zoomed for the printing, a resize or an input in between looks at the sheet as it is (not as it is zoomed)
    // and leaves the zoom alone; and after printing the form is looked at again.
    sheet.height = 1500;
    windowListeners.beforeprint();
    const zoom = sheet.style.zoom;
    windowListeners.resize();
    documentListeners.input();
    windowListeners.load();
    assert.equal(sheet.style.zoom, zoom);
    assert.equal(modern.warning.hidden, false, 'the sheet is judged as it is, not as it is zoomed');
    windowListeners.afterprint();
    sheet.height = 900;
    documentListeners.input();
    assert.equal(modern.warning.hidden, true);
});

check('Ctrl+P prints the form and not what is around it, but not while a signature is still on the pad', () => {
    const { windowListeners, printed, pad, note } = modern;
    const press = (key, extra = {}) => {
        let prevented = false;
        windowListeners.keydown({ key, preventDefault() { prevented = true; }, ...extra });

        return prevented;
    };
    assert.deepEqual([press('p', { ctrlKey: true }), press('P', { metaKey: true })], [true, true]);
    assert.deepEqual(printed, ['print', 'print']);
    // Other keys, and other combinations of Ctrl (find, print to a file ...), are the browser's.
    assert.deepEqual([press('p'), press('f', { ctrlKey: true }), press('p', { ctrlKey: true, shiftKey: true }), press('p', { ctrlKey: true, altKey: true })], [false, false, false, false]);
    assert.equal(printed.length, 2);

    // What is being drawn on the pad is not on the form yet: it would be left off the paper. The pad says so, and nothing is printed.
    pad.hidden = false;
    assert.equal(press('p', { ctrlKey: true }), true);
    assert.deepEqual([printed.length, note.hidden], [2, false]);
    pad.hidden = true;
    assert.equal(press('p', { ctrlKey: true }), true);
    assert.equal(printed.length, 3);
});

done();
