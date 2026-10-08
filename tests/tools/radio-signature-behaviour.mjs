// Runs the inline JavaScript of the pad for signing the form on the screen without a browser, and
// checks what it does: how the pad is sized, what a pen, a finger and a mouse draw (and what they do
// not), what a stroke that the system cancels or whose end never comes does, how much ink makes a
// signature, what comes out as the picture (where the ink lands and how thick it is), and how the
// picture and the date get into the form and out of it again.
//
// usage: node radio-signature-behaviour.mjs <file with the rendered form>
//
// A small stand-in for the document is made here: the elements the script reaches for, and a
// canvas that remembers what is drawn on it. (What a real pen and a real screen make of it can only
// be seen in a browser: that has been done with a simulated pen in headless Edge, not with a real
// Wacom.) PHPUnit calls this from RadioCheckoutFormTest.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { checker } from './page-sandbox.mjs';

const html = fs.readFileSync(process.argv[2], 'utf8');
const blocks = [...html.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script\s*>/gi)].map(match => match[1]);
const scripts = blocks.filter(block => block.includes("getElementById('signCell')"));
assert.equal(scripts.length, 1, `expected one inline script for the pad, found ${scripts.length} of ${blocks.length}`);

/** An element: what the script sets on it is kept, and every handler it was given can be called (listeners.click(...)). */
const make = (tag, extra = {}) => {
    const classes = new Set();
    const attrs = {};
    const handlers = {};
    const listeners = new Proxy({}, { get: (_, event) => (handlers[event] ? (...args) => handlers[event].map(handler => handler(...args)) : undefined) });
    const element = {
        tag, attrs, classes, listeners, handlers, style: {}, children: [], focused: 0, removed: false, hidden: false, disabled: false,
        classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
        setAttribute: (name, value) => { attrs[name] = String(value); },
        getAttribute: name => attrs[name] ?? null,
        addEventListener: (event, handler) => { (handlers[event] ||= []).push(handler); },
        focus() { this.focused++; },
        remove() { this.removed = true; },
        ...extra,
    };

    return element;
};

/** A canvas context that remembers what is drawn on it: each stroke with the width it was drawn with, and each point. */
const recorder = () => {
    const log = [];
    const context = {
        log,
        lineWidth: 1,
        setTransform: (...matrix) => log.push({ op: 'setTransform', matrix }),
        clearRect: (...area) => log.push({ op: 'clearRect', area }),
        beginPath: () => log.push({ op: 'beginPath' }),
        moveTo: (x, y) => log.push({ op: 'moveTo', x, y }),
        quadraticCurveTo: (cx, cy, x, y) => log.push({ op: 'curve', cx, cy, x, y }),
        stroke: () => log.push({ op: 'stroke', width: context.lineWidth, ink: context.strokeStyle }),
        arc: (x, y, radius) => log.push({ op: 'arc', x, y, radius }),
        fill: () => log.push({ op: 'fill', ink: context.fillStyle }),
    };

    return context;
};
const strokes = context => context.log.filter(entry => entry.op === 'stroke');
const near = (actual, expected, tolerance, what) => assert.ok(Math.abs(actual - expected) <= tolerance, `${what}: ${actual} is not ${expected} (±${tolerance})`);

/** Every position that was drawn: the ends of a path, the points a curve bends towards, the centres of dots. */
const positions = context => context.log.flatMap(entry => {
    if (entry.op === 'moveTo') return [[entry.x, entry.y]];
    if (entry.op === 'curve') return [[entry.cx, entry.cy], [entry.x, entry.y]];
    if (entry.op === 'arc') return [[entry.x, entry.y]];

    return [];
});

/** The form page as far as the pad reaches: run the pad's script on it, with the box, the window and the pad of the sizes given. */
const page = ({ box = { width: 152, height: 66 }, window: view = { innerWidth: 1000, innerHeight: 700, devicePixelRatio: 1 } } = {}) => {
    const el = {};
    for (const id of ['signCell', 'signOverlay', 'signCanvas', 'signOpen', 'signRemove', 'signUndo', 'signClear', 'signCancel', 'signApply', 'signNote']) {
        el[id] = make(id === 'signCanvas' ? 'canvas' : 'div');
    }
    el.signRemove.hidden = true;
    el.signOverlay.hidden = true;
    el.signNote.hidden = true;
    el.signApply.disabled = el.signUndo.disabled = el.signClear.disabled = true;
    el.signCell.getBoundingClientRect = () => ({ left: 0, top: 0, ...box });
    el.signCell.attrs['data-today'] = '08-10-2026';
    el.signCell.children = [el.signOpen, el.signRemove];
    el.signCell.querySelector = selector => (selector === '.sig-img' ? (el.signCell.children.find(child => child.tag === 'img' && !child.removed) ?? null) : null);
    el.signCell.insertBefore = (child, reference) => {
        el.signCell.children.splice(Math.max(0, el.signCell.children.indexOf(reference)), 0, child);
        el.signCell.insertedBefore.push(child);
    };
    el.signCell.insertedBefore = [];
    el.signCell.firstChild = el.signOpen;
    el.signOverlay.panel = make('div');
    el.signOverlay.querySelector = selector => (selector === '.sig-panel' ? el.signOverlay.panel : null);
    // The pad is where the test puts it: at (100, 50) on the page, and as big as the script says it is. Like a real canvas, a new
    // width or height is logged as a wipe of what was there (and of its transform).
    const canvas = el.signCanvas;
    canvas.context = recorder();
    canvas.getContext = () => canvas.context;
    canvas.captured = [];
    canvas.setPointerCapture = id => canvas.captured.push(id);
    canvas.hasPointerCapture = id => canvas.captured.includes(id);
    for (const side of ['width', 'height']) {
        let value = 0;
        Object.defineProperty(canvas, side, { get: () => value, set: next => { value = next; canvas.context.log.push({ op: 'resize', side }); } });
    }
    canvas.getBoundingClientRect = () => ({ left: 100, top: 50, width: parseFloat(canvas.style.width), height: parseFloat(canvas.style.height) });

    const sheet = make('main', { inert: false });
    const date = make('span', { textContent: '' });
    const pictures = [];
    const keydown = {};
    const resizing = {};
    const root = { style: { overflow: '' } };
    const document = {
        documentElement: root,
        getElementById: id => el[id] ?? null,
        querySelector: selector => (selector === 'main.sheet' ? sheet : selector === '[data-field="user_date"]' ? date : null),
        createElement: tag => {
            const made = make(tag);
            if (tag === 'canvas') {
                made.context = recorder();
                made.getContext = () => made.context;
                made.toDataURL = type => { pictures.push({ canvas: made, type }); return `data:${type};base64,PICTURE${pictures.length}`; };
            }

            return made;
        },
        addEventListener: (event, handler) => { keydown[event] = handler; },
        activeElement: null,
    };
    const window = { ...view, addEventListener: (event, handler) => { resizing[event] = handler; } };
    vm.runInContext(scripts[0], vm.createContext({ document, window, Math, Infinity, parseFloat }), { filename: 'checkout-signature-script.js' });

    // The time of the events, in milliseconds: a test lets it pass.
    const clock = { now: 1000 };
    // Pointer events as a pen, a finger or a mouse gives them; the page of the pad is at (100, 50), so (x, y) are on the pad.
    const pointer = (type, { id = 1, kind = 'mouse', button = 0, x, y, pressure = kind === 'pen' ? 0.5 : 0, coalesced = null } = {}) => {
        let prevented = false;
        const event = {
            pointerId: id, pointerType: kind, button, pressure, clientX: 100 + x, clientY: 50 + y, timeStamp: clock.now,
            preventDefault() { prevented = true; },
            ...(coalesced ? { getCoalescedEvents: () => coalesced.map(([cx, cy, cp]) => ({ pointerId: id, pointerType: kind, pressure: cp, clientX: 100 + cx, clientY: 50 + cy })) } : {}),
        };
        canvas.listeners[type]?.(event);

        return prevented;
    };
    /** A stroke along the points, as a pen of the kind given draws it. */
    const draw = (points, options = {}) => {
        const [[x, y], ...rest] = points;
        pointer('pointerdown', { ...options, x, y });
        rest.forEach(([mx, my, pressure]) => pointer('pointermove', { ...options, x: mx, y: my, ...(pressure === undefined ? {} : { pressure }) }));
        const [lx, ly] = points.at(-1);
        pointer('pointerup', { ...options, x: lx, y: ly });
    };
    const open = () => el.signOpen.listeners.click();

    return { el, canvas, sheet, date, document, window, pictures, keydown, resizing, root, clock, pointer, draw, open };
};

/** A line that is long enough to be a signature: zig-zags across the pad. */
const along = (width, at = 120) => Array.from({ length: 12 }, (_, i) => [width * (0.1 + i * 0.07), at + (i % 2) * 40]);

const { check, done } = checker();

check('the box opens the pad: sized by the window and shaped like the box, nothing behind it can be used', () => {
    const { el, canvas, sheet, root, open } = page();
    open();
    assert.equal(el.signOverlay.hidden, false);
    assert.equal(sheet.inert, true);
    assert.equal(root.style.overflow, 'hidden');
    assert.equal(el.signOverlay.panel.focused, 1);
    // 152 x 66 box: a ratio of 2.30. The window (1000 x 700) allows 944 across and 490 x 2.30 = 1128: so 900 wide, the most.
    assert.deepEqual([canvas.style.width, canvas.style.height], ['900px', '391px']);
    // The drawing area has more pixels than the screen shows (twice at least), and is drawn in the pixels of the screen: the
    // transform is set after the size (a real canvas forgets its transform when its size is set).
    assert.deepEqual([canvas.width, canvas.height], [1800, 782]);
    const log = canvas.context.log;
    const transform = log.findIndex(entry => entry.op === 'setTransform');
    assert.deepEqual(log[transform].matrix, [2, 0, 0, 2, 0, 0]);
    assert.ok(transform > log.findLastIndex(entry => entry.op === 'resize'), 'the transform comes after the size');
    // Nothing is drawn yet: nothing to take, to undo or to wipe.
    assert.deepEqual([el.signApply.disabled, el.signUndo.disabled, el.signClear.disabled], [true, true, true]);

    // A low window makes the pad smaller, a sharp screen draws it with more pixels, and the pad is never too small to sign on.
    const low = page({ window: { innerWidth: 1000, innerHeight: 500, devicePixelRatio: 3 } });
    low.open();
    assert.deepEqual([low.canvas.style.width, low.canvas.width], ['668px', 2004]);
    const tiny = page({ window: { innerWidth: 300, innerHeight: 300, devicePixelRatio: 1 } });
    tiny.open();
    assert.equal(tiny.canvas.style.width, '260px');
    // A box of another shape than the usual one: the pad is the same shape, within limits (not wider than 3 to 1, not squarer than 1.4 to 1).
    const wide = page({ box: { width: 300, height: 50 } });
    wide.open();
    assert.equal(wide.canvas.style.height, `${Math.round(900 / 3)}px`);
    const square = page({ box: { width: 100, height: 100 } });
    square.open();
    assert.deepEqual([square.canvas.style.width, square.canvas.style.height], ['686px', '490px']);
});

check('a pen draws wider the harder it presses, a mouse and a finger draw one width whatever they report, and a pen that reports nothing draws as a mouse does', () => {
    const pad = page();
    pad.open();
    const width = 4.5; // 0.5 % of a pad 900 wide
    pad.draw([[100, 100], [200, 130], [300, 100], [400, 130]], { kind: 'mouse' });
    pad.draw([[100, 200], [200, 230], [300, 200], [400, 230]], { kind: 'pen', pressure: 0.9 });
    pad.draw([[100, 300], [200, 330], [300, 300], [400, 330]], { kind: 'pen', pressure: 0.2 });
    const widths = strokes(pad.canvas.context).map(entry => Math.round(entry.width * 1000) / 1000);
    assert.ok(widths.includes(4.5), 'the mouse draws at the width of the pen');
    assert.ok(widths.includes(Math.round(width * (0.5 + 0.9) * 1000) / 1000), 'a hard press is wider');
    assert.ok(widths.includes(Math.round(width * (0.5 + 0.2) * 1000) / 1000), 'a light touch is narrower');
    assert.ok(strokes(pad.canvas.context).every(entry => entry.ink === '#101010'));

    // A pen whose driver reports no pressure (0) draws as a mouse does, and a finger that reports a pressure (1 or 0.9) is not wider.
    const plain = page();
    plain.open();
    plain.draw([[100, 100], [200, 130], [300, 100], [400, 130]], { kind: 'pen', pressure: 0 });
    assert.deepEqual([...new Set(strokes(plain.canvas.context).map(entry => entry.width))], [4.5]);
    const finger = page();
    finger.open();
    finger.draw([[100, 100], [200, 130], [300, 100], [400, 130]], { kind: 'touch', pressure: 0.9 });
    assert.deepEqual([...new Set(strokes(finger.canvas.context).map(entry => entry.width))], [4.5]);
});

check('a hand beside a pen draws nothing while the pen is near, and a finger draws once it has been away; the pad does not remember', () => {
    const pad = page();
    pad.open();
    // The pen only hovers, and the palm touches down: the palm draws nothing. (3 seconds after the last sign of the pen it is a finger.)
    pad.pointer('pointermove', { kind: 'pen', id: 5, x: 300, y: 100 });
    pad.clock.now += 1500;
    pad.draw([[500, 100], [600, 150], [700, 100]], { kind: 'touch', id: 7 });
    assert.equal(strokes(pad.canvas.context).length, 0);
    pad.clock.now += 3000;
    pad.draw([[500, 100], [600, 150], [700, 100]], { kind: 'touch', id: 7 });
    assert.ok(strokes(pad.canvas.context).length > 0, 'a finger draws when no pen has been near for a while');

    // A pen that touches counts as near as well (a tap that does not move included), and a pad that is opened again forgets the pen.
    const again = page();
    again.open();
    again.pointer('pointerdown', { kind: 'pen', id: 4, x: 100, y: 100 });
    again.pointer('pointerup', { kind: 'pen', id: 4, x: 100, y: 100 });
    again.el.signClear.listeners.click();
    const before = again.canvas.context.log.length;
    again.clock.now += 500;
    again.draw([[500, 100], [600, 150], [700, 100]], { kind: 'touch', id: 7 });
    assert.equal(again.canvas.context.log.length, before, 'half a second after a tap of the pen');
    again.el.signCancel.listeners.click();
    again.open();
    // (what is drawn from here on: the pad drew before)
    const reopened = again.canvas.context.log.length;
    again.draw([[500, 100], [600, 150], [700, 100]], { kind: 'touch', id: 7 });
    assert.ok(strokes({ log: again.canvas.context.log.slice(reopened) }).length > 0, 'a pad opened again has seen no pen');
});

check('the barrel button, the eraser end and the right button draw nothing', () => {
    const pad = page();
    pad.open();
    for (const kind of ['pen', 'mouse']) {
        for (const button of [1, 2, 5]) {
            const prevented = pad.pointer('pointerdown', { kind, button, x: 100, y: 100 });
            assert.equal(prevented, false, `${kind} button ${button}`);
        }
    }
    assert.equal(pad.canvas.context.log.filter(entry => entry.op === 'arc').length, 0);
    assert.deepEqual(pad.canvas.captured, []);

    // The left button / the tip draws, and takes hold of the pointer so that a stroke goes on beyond the pad.
    assert.equal(pad.pointer('pointerdown', { id: 1, x: 100, y: 100 }), true);
    assert.deepEqual(pad.canvas.captured, [1]);
    pad.pointer('pointermove', { id: 1, x: 200, y: 130 });
    pad.pointer('pointerup', { id: 1, x: 200, y: 130 });
    // The barrel button's menu is not wanted on the pad.
    let prevented = false;
    pad.canvas.listeners.contextmenu({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    // A pen that hovers (moves without having touched down) draws nothing.
    const log = pad.canvas.context.log.length;
    pad.pointer('pointermove', { kind: 'pen', id: 3, x: 400, y: 100 });
    assert.equal(pad.canvas.context.log.length, log);
});

check('one pointer draws at a time: another cannot start a stroke, bend the one that is drawn, or end it', () => {
    // No pen has been near: the second pointer is turned away by the rule of one pointer, not by that of a hand beside a pen.
    const pad = page();
    pad.open();
    const count = () => pad.canvas.context.log.length;
    pad.pointer('pointerdown', { id: 1, x: 100, y: 100 });
    pad.pointer('pointermove', { id: 1, x: 200, y: 130 });
    const afterFirst = count();
    pad.pointer('pointerdown', { id: 2, kind: 'mouse', x: 600, y: 300 });
    pad.pointer('pointermove', { id: 2, kind: 'mouse', x: 650, y: 330 });
    pad.pointer('pointerup', { id: 2, kind: 'mouse', x: 650, y: 330 });
    assert.equal(count(), afterFirst, 'nothing of the second pointer is drawn');
    assert.deepEqual(pad.canvas.captured, [1]);
    // The stroke of the first goes on: the up of another did not end it.
    pad.pointer('pointermove', { id: 1, x: 300, y: 100 });
    assert.ok(count() > afterFirst);
    // Its own up ends it: what comes after is no part of it.
    pad.pointer('pointerup', { id: 1, x: 300, y: 100 });
    const ended = count();
    pad.pointer('pointermove', { id: 1, x: 400, y: 130 });
    assert.equal(count(), ended);
});

check('a stroke the system cancels is no ink, one whose up never comes does not keep the pad from drawing, and an end is painted once', () => {
    // A cancelled pointer (a palm, a gesture of the driver): its stroke goes, and a new one can be drawn.
    const cancelled = page();
    cancelled.open();
    cancelled.pointer('pointerdown', { id: 1, x: 100, y: 100 });
    cancelled.pointer('pointermove', { id: 1, x: 300, y: 150 });
    cancelled.pointer('pointermove', { id: 1, x: 500, y: 100 });
    assert.equal(cancelled.el.signClear.disabled, false);
    cancelled.pointer('pointercancel', { id: 1, x: 500, y: 100 });
    assert.deepEqual([cancelled.el.signApply.disabled, cancelled.el.signClear.disabled], [true, true], 'the stroke is gone');
    // Its ink is wiped from the pad as well: the pad is drawn again with what is left (the first stroke, and not the cancelled one).
    const left = page();
    left.open();
    left.draw([[100, 100], [200, 150], [300, 100], [400, 150]]);
    left.pointer('pointerdown', { id: 2, x: 100, y: 300 });
    left.pointer('pointermove', { id: 2, x: 300, y: 340 });
    left.pointer('pointermove', { id: 2, x: 500, y: 300 });
    const marked = left.canvas.context.log.length;
    left.pointer('pointercancel', { id: 2, x: 500, y: 300 });
    const afterCancel = left.canvas.context.log.slice(marked);
    const wiped = afterCancel.findIndex(entry => entry.op === 'clearRect');
    assert.ok(wiped >= 0, 'the pad is wiped');
    const again = positions({ log: afterCancel.slice(wiped) });
    assert.ok(again.length > 0 && again.every(([, y]) => y < 200), 'what is drawn again is the first stroke');
    cancelled.draw(along(900));
    assert.equal(cancelled.el.signApply.disabled, false);
    // A cancel of another pointer than the one that draws is no business of the stroke's.
    const other = page();
    other.open();
    other.pointer('pointerdown', { id: 1, x: 100, y: 100 });
    other.pointer('pointermove', { id: 1, x: 400, y: 150 });
    other.pointer('pointercancel', { id: 9, x: 100, y: 100 });
    assert.equal(other.el.signClear.disabled, false);

    // The up of a stroke never comes and the pointer has let go (no capture): the next stroke is drawn, and the first stays as far as it got.
    const stale = page();
    stale.open();
    stale.pointer('pointerdown', { id: 1, x: 100, y: 100 });
    for (const x of [200, 300, 400, 500]) stale.pointer('pointermove', { id: 1, x, y: 100 + (x % 200) / 4 });
    stale.canvas.captured = [];
    stale.draw(along(900, 250), { id: 2 });
    stale.el.signUndo.listeners.click();
    assert.equal(stale.el.signApply.disabled, false, 'the first stroke is still there after one undo');
    stale.el.signUndo.listeners.click();
    assert.deepEqual([stale.el.signApply.disabled, stale.el.signClear.disabled], [true, true], 'and the second undo takes it');

    // The pointer may lose its hold before it lifts: that ends the stroke, and the lift that follows paints nothing more.
    const lost = page();
    lost.open();
    lost.pointer('pointerdown', { id: 1, x: 100, y: 100 });
    lost.pointer('pointermove', { id: 1, x: 300, y: 150 });
    lost.pointer('pointermove', { id: 1, x: 500, y: 100 });
    lost.pointer('lostpointercapture', { id: 1, x: 500, y: 100 });
    const painted = lost.canvas.context.log.length;
    lost.pointer('pointerup', { id: 1, x: 500, y: 100 });
    lost.pointer('lostpointercapture', { id: 1, x: 500, y: 100 });
    assert.equal(lost.canvas.context.log.length, painted, 'an end is painted once');
    // A second stroke can follow.
    lost.draw(along(900));
    assert.equal(lost.el.signApply.disabled, false);
});

check('a tap or a speck is no signature, a stroke of some length is, and the positions in between are used in order', () => {
    const pad = page();
    pad.open();
    // A tap: a dot is drawn so that the person sees it, but it is no signature.
    pad.draw([[200, 200]]);
    assert.equal(pad.canvas.context.log.filter(entry => entry.op === 'arc').length >= 1, true);
    // The dot is as wide as the pen is (its radius is half the width of a line), and wider for a harder press.
    const dot = pad.canvas.context.log.find(entry => entry.op === 'arc');
    near(dot.radius, 4.5 / 2, 1e-9, 'the dot of a tap with a mouse');
    const hard = page();
    hard.open();
    hard.pointer('pointerdown', { kind: 'pen', pressure: 0.9, x: 200, y: 200 });
    hard.pointer('pointerup', { kind: 'pen', x: 200, y: 200 });
    near(hard.canvas.context.log.find(entry => entry.op === 'arc').radius, 4.5 * 1.4 / 2, 1e-9, 'the dot of a hard tap with a pen');
    assert.deepEqual([pad.el.signApply.disabled, pad.el.signClear.disabled], [true, false]);

    // 6 % of the pad's width (54 px) is the least that is taken for a signature: a little under is a speck, a little over is not.
    const length = at => {
        const edge = page();
        edge.open();
        edge.draw([[100, 100], [100 + at, 100]]);

        return edge.el.signApply.disabled;
    };
    assert.deepEqual([length(49.5), length(58.5)], [true, false]);

    // A pen reports more positions than events: all of them are taken, in order.
    const fine = page();
    fine.open();
    fine.pointer('pointerdown', { kind: 'pen', pressure: 0.5, x: 100, y: 100 });
    fine.pointer('pointermove', { kind: 'pen', pressure: 0.5, x: 160, y: 120, coalesced: [[110, 104, 0.4], [130, 110, 0.5], [160, 120, 0.6]] });
    fine.pointer('pointerup', { kind: 'pen', x: 160, y: 120 });
    // down + 3 positions = 4 points = 3 segments, bending towards the points before them, and the last one drawn again when the pen is lifted.
    const bends = fine.canvas.context.log.filter(entry => entry.op === 'curve').map(entry => entry.cx);
    assert.equal(bends.length, 4);
    [100, 110, 130, 130].forEach((expected, at) => near(bends[at], expected, 1e-9, `bend ${at}`));
    // Each curve starts in the middle between the two points before it (the first one at the first point), so that the line is smooth.
    const starts = fine.canvas.context.log.filter(entry => entry.op === 'moveTo').map(entry => [entry.x, entry.y]);
    [[100, 100], [105, 102], [120, 107], [120, 107]].forEach(([x, y], at) => {
        near(starts[at][0], x, 1e-9, `start ${at} (x)`);
        near(starts[at][1], y, 1e-9, `start ${at} (y)`);
    });
    // And each is as wide as the pen pressed where the curve began (the pressure of the point it starts from, not of the one it goes to).
    const widths = strokes(fine.canvas.context).map(entry => entry.width);
    [4.5, 4.05, 4.5, 4.5].forEach((expected, at) => near(widths[at], expected, 1e-9, `width ${at}`));
    // Positions beyond the pad are held to its edge (the ink is never outside what is shown).
    const edge = page();
    edge.open();
    edge.draw([[100, 100], [-300, 120], [2000, 100], [1500, 900]]);
    const curves = edge.canvas.context.log.filter(entry => entry.op === 'curve');
    assert.ok(curves.every(curve => curve.x >= 0 && curve.x <= 900 && curve.y >= 0 && curve.y <= 391 && curve.cx >= 0 && curve.cx <= 900 && curve.cy >= 0), 'inside the pad');
});

check('undo takes the last stroke, wipe takes all, and the pad is drawn again without them', () => {
    const pad = page();
    pad.open();
    pad.draw(along(900, 100));
    pad.draw(along(900, 250));
    assert.equal(pad.el.signApply.disabled, false);
    const log = () => pad.canvas.context.log;
    const afterWipe = from => {
        const slice = log().slice(from);
        const at = slice.findIndex(entry => entry.op === 'clearRect');

        return { area: slice[at].area, drawn: positions({ log: slice.slice(at) }) };
    };

    let from = log().length;
    pad.el.signUndo.listeners.click();
    let again = afterWipe(from);
    // The whole pad is wiped, and what is drawn again is the first stroke (its height is 120 to 160) and not the one that was undone (250 to 290).
    near(again.area[2], 900, 1e-9, 'the wiped width');
    near(again.area[3], 900 / (152 / 66), 1e-6, 'the wiped height');
    assert.deepEqual([again.area[0], again.area[1]], [0, 0]);
    assert.ok(again.drawn.length > 0 && again.drawn.every(([, y]) => y >= 100 && y <= 160), 'the first stroke is drawn again');
    assert.equal(pad.el.signApply.disabled, false, 'one stroke is left');
    from = log().length;
    pad.el.signUndo.listeners.click();
    again = afterWipe(from);
    assert.equal(again.drawn.length, 0, 'nothing is drawn again');
    assert.deepEqual([pad.el.signApply.disabled, pad.el.signUndo.disabled, pad.el.signClear.disabled], [true, true, true]);
    pad.draw(along(900, 100));
    pad.el.signClear.listeners.click();
    assert.deepEqual([pad.el.signApply.disabled, pad.el.signClear.disabled], [true, true]);
    // A button that turns itself off does not keep the key: it goes to the pad.
    const focused = page();
    focused.open();
    focused.draw(along(900));
    focused.document.activeElement = focused.el.signUndo;
    const before = focused.el.signOverlay.panel.focused;
    focused.el.signUndo.listeners.click();
    assert.equal(focused.el.signOverlay.panel.focused, before + 1);
});

check('the signature becomes a picture of the ink only, fitted to it, as thick as a pen on paper whatever size it was drawn in', () => {
    const pad = page();
    pad.open();
    // A signature drawn over 60 % of the pad's width and 13 % of its height, away from the corner of the pad.
    pad.draw([[180, 100], [300, 200], [420, 120], [540, 220], [720, 110]]);
    pad.el.signApply.listeners.click();

    assert.equal(pad.pictures.length, 1);
    const { canvas: out, type } = pad.pictures[0];
    assert.equal(type, 'image/png');
    // The longer side has 1200 pixels, the shape is that of the ink (plus a margin), and nothing but ink is drawn on it.
    assert.equal(Math.max(out.width, out.height), 1200);
    assert.ok(out.width > out.height);
    assert.ok(out.context.log.every(entry => !['clearRect'].includes(entry.op)));
    assert.deepEqual([...new Set(strokes(out.context).map(entry => entry.ink))], ['#101010']);
    // Where the ink lands: all of it on the picture, with the same margin on every side (4 % of the longer side of the ink).
    const margin = 0.04 * 0.6 * (1200 / (0.6 * 1.08)); // in picture pixels: 4 % of 0.6 pad widths, at the scale of the picture
    const at = positions(out.context);
    const xs = at.map(([x]) => x);
    const ys = at.map(([, y]) => y);
    assert.ok(xs.every(x => x >= 0 && x <= out.width) && ys.every(y => y >= 0 && y <= out.height), 'inside the picture');
    near(Math.min(...xs), margin, 0.6, 'left margin');
    near(out.width - Math.max(...xs), margin, 0.6, 'right margin');
    near(Math.min(...ys), margin, 0.6, 'top margin');
    near(out.height - Math.max(...ys), margin, 0.6, 'bottom margin');
    // The ink: 0.4 mm of 38 mm of the box (152 wide, less 1 mm on each side), the picture being fitted into the box.
    const inner = { width: 152 - 2 * 96 / 25.4, height: 66 - 2 * 96 / 25.4 };
    const shown = Math.min(inner.width / out.width, inner.height / out.height);
    const expected = inner.width * (0.4 / 38) / shown;
    const widths = strokes(out.context).map(entry => entry.width);
    assert.ok(widths.length > 0 && widths.every(width => Math.abs(width - expected) < 1e-9), `${widths[0]} against ${expected}`);

    // A small signature gets the same ink: the picture is fitted into the box, and the pen is as wide in it.
    const small = page();
    small.open();
    small.draw([[100, 100], [140, 140], [180, 100], [220, 140], [260, 100]]);
    small.el.signApply.listeners.click();
    const smallOut = small.pictures[0].canvas;
    const smallShown = Math.min(inner.width / smallOut.width, inner.height / smallOut.height);
    for (const entry of strokes(smallOut.context)) {
        assert.ok(Math.abs(entry.width * smallShown - inner.width * (0.4 / 38)) < 1e-9, 'the same thickness in the box');
    }

    // A tall signature is held in by the height of the box and not its width: the ink is as thick all the same (and this is where the
    // 1 mm that the picture keeps inside the box counts).
    const tall = page();
    tall.open();
    tall.draw([[200, 100], [260, 300], [320, 100], [380, 300], [440, 100]]);
    tall.el.signApply.listeners.click();
    const tallOut = tall.pictures[0].canvas;
    assert.ok(inner.height / tallOut.height < inner.width / tallOut.width, 'the height of the box is what holds it');
    const tallShown = Math.min(inner.width / tallOut.width, inner.height / tallOut.height);
    for (const entry of strokes(tallOut.context)) {
        assert.ok(Math.abs(entry.width * tallShown - inner.width * (0.4 / 38)) < 1e-9, 'the same thickness for a tall one');
    }
});

check('the picture goes into the box before the buttons, the date is signed in, the pad closes and gives the focus back', () => {
    const pad = page();
    pad.open();
    pad.draw(along(900));
    pad.el.signApply.listeners.click();

    const [image] = pad.el.signCell.insertedBefore;
    assert.deepEqual([image.tag, image.className, image.alt, image.src], ['img', 'sig-img', 'Tanda tangan Responsible User', 'data:image/png;base64,PICTURE1']);
    // Below the buttons it would cover them, and a click on the signed box would no longer open the pad again.
    assert.deepEqual(pad.el.signCell.children, [image, pad.el.signOpen, pad.el.signRemove]);
    assert.equal(pad.el.signCell.classes.has('signed'), true);
    assert.equal(pad.el.signRemove.hidden, false);
    assert.equal(pad.el.signOpen.attrs['aria-label'], 'Ganti tanda tangan Responsible User');
    assert.equal(pad.date.textContent, '08-10-2026');
    // The pad is closed: the form can be used again, and the key goes back to where it was.
    assert.deepEqual([pad.el.signOverlay.hidden, pad.sheet.inert, pad.root.style.overflow, pad.el.signOpen.focused], [true, false, '', 1]);

    // Signing again puts the new picture in the place of the old one.
    pad.open();
    assert.equal(pad.el.signApply.disabled, true, 'the pad starts empty');
    pad.draw(along(900, 200));
    pad.el.signApply.listeners.click();
    assert.equal(pad.el.signCell.insertedBefore.length, 1, 'one picture in the box');
    assert.equal(image.src, 'data:image/png;base64,PICTURE2');

    // Nothing that is not a signature is taken (the button is disabled, and a click that gets through takes nothing).
    const none = page();
    none.open();
    none.draw([[100, 100], [110, 105]]);
    none.el.signApply.listeners.click();
    assert.deepEqual([none.pictures.length, none.el.signCell.insertedBefore.length, none.el.signOverlay.hidden], [0, 0, false]);
});

check('a date that was written in is not replaced; the date that signing wrote is taken back with the signature, no other, at any number of signings', () => {
    const written = page();
    written.date.textContent = '07-10-2026';
    written.open();
    written.draw(along(900));
    written.el.signApply.listeners.click();
    assert.equal(written.date.textContent, '07-10-2026');
    written.el.signRemove.listeners.click();
    assert.equal(written.date.textContent, '07-10-2026');

    const signed = page();
    signed.open();
    signed.draw(along(900));
    signed.el.signApply.listeners.click();
    const [image] = signed.el.signCell.insertedBefore;
    assert.equal(signed.date.textContent, '08-10-2026');
    signed.el.signRemove.listeners.click();
    assert.deepEqual([image.removed, signed.el.signCell.classes.has('signed'), signed.el.signRemove.hidden, signed.date.textContent], [true, false, true, '']);
    assert.equal(signed.el.signOpen.attrs['aria-label'], 'Tanda tangan Responsible User di layar');
    // The button that was pressed is gone: the key goes to the box.
    assert.equal(signed.el.signOpen.focused, 2, 'after the pad closed, and after the remove button went');

    // A date that was changed by hand after signing stays when the signature goes.
    const changed = page();
    changed.open();
    changed.draw(along(900));
    changed.el.signApply.listeners.click();
    changed.date.textContent = '09-10-2026';
    changed.el.signRemove.listeners.click();
    assert.equal(changed.date.textContent, '09-10-2026');

    // Signing, removing, typing the same day by hand, signing and removing again: what was typed by hand is not signing's to take back.
    const twice = page();
    twice.open();
    twice.draw(along(900));
    twice.el.signApply.listeners.click();
    twice.el.signRemove.listeners.click();
    assert.equal(twice.date.textContent, '');
    twice.date.textContent = '08-10-2026';
    twice.open();
    twice.draw(along(900));
    twice.el.signApply.listeners.click();
    twice.el.signRemove.listeners.click();
    assert.equal(twice.date.textContent, '08-10-2026');
});

check('Cancel and the Esc key close the pad and take nothing, and Ctrl+P is answered with where to go on', () => {
    const pad = page();
    pad.open();
    pad.draw(along(900));
    pad.el.signNote.hidden = false;
    pad.el.signCancel.listeners.click();
    assert.deepEqual([pad.el.signOverlay.hidden, pad.sheet.inert, pad.el.signCell.insertedBefore.length, pad.date.textContent, pad.pictures.length], [true, false, 0, '', 0]);
    assert.equal(pad.el.signNote.hidden, true, 'the note about printing goes with the pad');

    pad.open();
    pad.draw(along(900));
    let prevented = false;
    pad.keydown.keydown({ key: 'Escape', preventDefault() { prevented = true; } });
    assert.deepEqual([prevented, pad.el.signOverlay.hidden, pad.el.signCell.insertedBefore.length], [true, true, 0]);
    // Esc with the pad closed is no business of the pad's.
    prevented = false;
    pad.keydown.keydown({ key: 'Escape', preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    // The pad that is opened again is empty, and has no note.
    pad.el.signNote.hidden = false;
    pad.open();
    assert.deepEqual([pad.el.signApply.disabled, pad.el.signNote.hidden], [true, true]);
});

check('the pad is sized again when the window changes, and what was drawn is drawn again, at the new size', () => {
    const pad = page();
    pad.open();
    pad.draw(along(900));
    const before = pad.canvas.context.log.length;
    pad.window.innerWidth = 600;
    pad.resizing.resize();
    assert.equal(pad.canvas.style.width, '544px');
    // Size, then transform, then the wipe, then the eleven segments of the stroke at the new size.
    const slice = pad.canvas.context.log.slice(before);
    const order = slice.map(entry => entry.op).filter(op => ['resize', 'setTransform', 'clearRect', 'stroke'].includes(op));
    assert.deepEqual(order, ['resize', 'resize', 'setTransform', 'clearRect', ...Array(11).fill('stroke')]);
    const [, last] = positions({ log: slice }).reduce((best, point) => (point[0] > best[0] ? point : best), [0, 0]);
    assert.ok(Math.max(...positions({ log: slice }).map(([x]) => x)) <= 544, 'drawn at the new size');
    assert.ok(last >= 0);
    // A closed pad is left alone.
    pad.el.signCancel.listeners.click();
    const closed = pad.canvas.context.log.length;
    pad.window.innerWidth = 800;
    pad.resizing.resize();
    assert.equal(pad.canvas.context.log.length, closed);
});

check('the pad is in the page: markup and script agree, and the paper does not carry it', () => {
    for (const id of ['signCell', 'signOverlay', 'signCanvas', 'signOpen', 'signRemove', 'signUndo', 'signClear', 'signCancel', 'signApply', 'signNote']) {
        assert.ok(new RegExp(`\\bid="${id}"`).test(html), `the page has no element with id="${id}"`);
    }
    // What the script looks for besides ids: the panel it gives the focus, the field it writes the date in, and the sheet it locks.
    assert.ok(/class="sig-panel"/.test(html), 'the panel');
    assert.ok(/data-field="user_date"/.test(html), 'the date field');
    assert.ok(/<main class="sheet"/.test(html), 'the sheet');
    // The pad and the buttons of the box are for the screen; only the picture is part of the paper.
    assert.ok(/class="sig-overlay no-print" id="signOverlay"/.test(html));
    assert.ok(/class="sig-open no-print" id="signOpen"/.test(html));
    assert.ok(/class="sig-remove no-print" id="signRemove"/.test(html));
    assert.ok(/class="sig-note" id="signNote" role="alert" hidden/.test(html), 'the note is announced and closed');
    // A pen on the pad must draw and not scroll.
    assert.ok(/\.sig-surface canvas \{[^}]*touch-action: none/.test(html));
});

done();
