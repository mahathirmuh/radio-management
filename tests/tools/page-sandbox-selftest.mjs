// Checks the stand-in for jQuery that page-sandbox.mjs gives the page checkers: the checkers are only as good as the way the
// stand-in behaves like jQuery where they lean on it, so those ways are checked here against a small page of its own.
//
// usage: node page-sandbox-selftest.mjs
//
// PHPUnit calls this from PageSandboxTest.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { assertWellFormed, checker, isWellFormed, loadPage } from './page-sandbox.mjs';

const page = `<!doctype html><html><body>
<table id="t"><thead><tr><th data-key="a">A</th><th data-key="b">B</th></tr></thead></table>
<script>
const demo = {
    rewrite() {
        $('#box').addClass('keep').attr('data-own', '1');
        $('#boxes a').addClass('stay');
        $('#box a').addClass('old').attr('href', '/old').prop('disabled', true).on('click', () => {});
        $('#box').html('<a>new</a>');
    },
    rewriteText() {
        $('#box a').addClass('old').attr('href', '/old');
        $('#box').text('plain');
    },
    rewriteHead() {
        $('#t th[data-key="a"]').addClass('sorted');
        $('#t thead').html('<tr><th data-key="a">A</th></tr>');
    },
    mark() {
        $('#tabs a').removeClass('active');
        $(this).addClass('active');
    },
    fetch() {
        $.getJSON('/data', { q: 1 }).done(body => { demo.got = body; }).fail(() => { demo.failed = true; }).always(() => { demo.finished = true; });
    },
    later() {
        setTimeout(() => { demo.fired = true; }, 500);
    },
    save() {
        $.ajax({ url: '/save', type: 'POST', dataType: 'json', data: { _method: 'PATCH', n: 1 } })
            .done(body => { demo.saved = body; }).fail(xhr => { demo.refused = xhr; }).always(() => { demo.finished = true; });
    },
    ask() {
        // As a dialog's own function does: the answer or the refusal, both as a value.
        $.ajax({ url: '/ask', type: 'POST' }).then(response => { demo.value = { ok: true, response }; }, xhr => { demo.value = { ok: false, xhr }; });
        Swal.fire({ title: 'Sure?', html: '<b>x</b>', icon: 'question' }).then(result => { demo.answer = result.isConfirmed; });
        Swal.fire('Done', 'Text', 'success');
    },
    frame() {
        const frame = document.createElement('iframe');
        frame.id = 'theFrame';
        document.getElementById('host').appendChild(frame);
    },
    drop() {
        $('#theFrame').remove();
    },
    got: null, failed: false, finished: false, fired: false,
};
$('#tabs').on('click', 'a', demo.mark);
$('#box').on('click', function () { $(this).attr('data-clicked', $(this).attr('data-in')); });
</script></body></html>`;

const file = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'sandbox-selftest-')), 'page.html');
fs.writeFileSync(file, page);
process.on('exit', () => fs.rmSync(path.dirname(file), { recursive: true, force: true }));
const fresh = () => {
    const loaded = loadPage(file, ['const demo = {'], { href: 'http://localhost/demo' });

    return { ...loaded, demo: loaded.page('demo') };
};
const { check, done } = checker();

check('writing markup into an element replaces what was inside it, and leaves the element itself as it was', () => {
    const { demo, node } = fresh();
    demo.rewrite();
    const box = node('#box');
    assert.equal(box.html, '<a>new</a>');
    assert.equal(box.wrote, 'html');
    assert.deepEqual([...box.classes], ['keep']);
    assert.deepEqual(box.attrs, { 'data-own': '1' });
    // The links inside are new ones: nothing of the old ones is left.
    const link = node('#box a');
    assert.deepEqual([[...link.classes], link.attrs, link.props, link.handlers, link.html, link.wrote], [[], {}, {}, {}, '', null]);
    // An element that only has a similar name is not inside it.
    assert.deepEqual([...node('#boxes a').classes], ['stay']);
});

check('writing text into an element does the same', () => {
    const { demo, node, $ } = fresh();
    demo.rewriteText();
    assert.deepEqual([[...node('#box a').classes], node('#box a').attrs], [[], {}]);
    assert.equal($('#box').text(), 'plain');
    assert.equal(node('#box').html, '');
});

check('a new head of a table is new header cells', () => {
    const { demo, node, exists } = fresh();
    assert.equal(exists('#t th[data-key="a"]'), true, 'the page has the cell');
    assert.equal(exists('#t th[data-key="z"]'), false);
    demo.rewriteHead();
    assert.deepEqual([...node('#t th[data-key="a"]').classes], [], 'the cell that was marked is gone');
    // The head the script wrote has one cell: the other is no longer there.
    assert.equal(exists('#t th[data-key="a"]'), true);
    assert.equal(exists('#t th[data-key="b"]'), false);
});

check('what a handler does to a selector of many elements is done to those the test says it matches', () => {
    const { fire } = fresh();
    const { element, siblings } = fire('#tabs', 'click a', undefined, {
        member: '#tabs a',
        siblings: [{ classes: ['active'] }, { classes: ['active', 'other'], attrs: { 'data-sheet': '2' } }],
    });
    assert.deepEqual([...element.classes], ['active'], 'the clicked one is marked, after the others are cleared');
    assert.deepEqual(siblings.map(sibling => [...sibling.classes]), [[], ['other']]);
    assert.deepEqual(siblings[1].attrs, { 'data-sheet': '2' });
});

check('the element a handler is called for is filled in by the test, comes back, and is a new one the next time', () => {
    const { fire, node } = fresh();
    node('(object)').attrs['data-in'] = '5';
    node('(object)').data.sheet = 3;
    const first = fire('#box', 'click');
    assert.deepEqual(first.element.attrs, { 'data-in': '5', 'data-clicked': '5' }, 'the handler read from, and wrote to, the element it was called for');
    assert.equal(first.element.data.sheet, 3);
    const second = fire('#box', 'click');
    assert.deepEqual([second.element.attrs, second.element.data], [{}, {}], 'nothing of the first element is on the next one');
    assert.throws(() => fire('#box', 'dblclick'), /no "dblclick" handler on #box/);
});

check('a request waits until the test answers it, and an answer reaches the callbacks in the way jQuery calls them', () => {
    const { demo, requests, sent, answer } = fresh();
    demo.fetch();
    assert.equal(requests.length, 1);
    assert.deepEqual([requests[0].url, sent()], ['/data', { q: 1 }]);
    assert.deepEqual([demo.got, demo.finished], [null, false]);
    answer(requests[0], { rows: [1] });
    assert.deepEqual([demo.got, demo.failed, demo.finished], [{ rows: [1] }, false, true]);

    const other = fresh();
    other.demo.fetch();
    other.refuse(other.requests[0], { status: 500 });
    assert.deepEqual([other.demo.got, other.demo.failed, other.demo.finished], [null, true, true]);
});

check('a timer fires when the test lets time pass, and not before', () => {
    const { demo, runTimers } = fresh();
    demo.later();
    assert.equal(demo.fired, false);
    runTimers();
    assert.equal(demo.fired, true);
});

check('an element the script made and put into the page is there until it is taken out by its id', () => {
    const { demo, inside } = fresh();
    demo.frame();
    assert.deepEqual(inside('host').map(element => element.id), ['theFrame']);
    assert.deepEqual(inside('elsewhere'), []);
    demo.drop();
    assert.deepEqual(inside('host'), []);
});

check('a request is written as jQuery writes it: every part encoded, a blank as "+", and half of a character is a failure', () => {
    const { demo, $, requests } = fresh();
    assert.equal($.param({ a: 'x y', b: 'A&B "C"', c: '', d: null, 'e f': 'ü' }), 'a=x+y&b=A%26B+%22C%22&c=&d=&e+f=%C3%BC');
    // (jQuery leaves "!'()*~" as they are, as encodeURIComponent does; URLSearchParams would encode some of them.)
    assert.equal($.param({ id: "b!x(1)'*~" }), "id=b!x(1)'*~");
    // Half of a character that has two (outside the basic plane) cannot be written: it throws before anything is sent, like jQuery.
    assert.throws(() => $.param({ q: '😀'.slice(0, 1) }), URIError);
    assert.throws(() => $.getJSON('/data', { q: 'a' + '😀'.slice(0, 1) }), URIError);
    assert.equal(requests.length, 0);
    $.getJSON('/data', { q: 'a😀' });
    assert.equal(requests.length, 1);
    demo.fetch();
    assert.equal(requests.length, 2);
});

check('a text with half of a character is told from one without', () => {
    const half = '\u{1F600}'.slice(0, 1);
    for (const whole of ['', 'abc', '\u{1F600}', 'a\u{1F600}b', 'é']) assert.equal(isWellFormed(whole), true, JSON.stringify(whole));
    for (const broken of [half, half + 'a', 'a' + half, '\u{1F600}'.slice(1), 'a\u{1F600}'.slice(0, 2)]) assert.equal(isWellFormed(broken), false, JSON.stringify(broken));
});

check('what a script triggers on an element is written down', () => {
    const page = fresh();
    const { $, node } = page;
    assert.deepEqual(node('#box').triggered, []);
    $('#box').trigger('focus');
    $('#box').trigger('focus').trigger('change');
    assert.deepEqual(node('#box').triggered, ['focus', 'focus', 'change']);
    assert.deepEqual(node('#other').triggered, []);
});

check('markup that a script wrote is held to be well formed: known tags, each closed by its own end', () => {
    for (const good of ['', 'text only', '<tr><td class="a b">x</td><td><span title="a > b">y</span></td></tr>', '<ul><li><a href="#" class="x">1</a></li></ul>', 'a<br>b<input type="text">',
        '<div role="status"><div class="alert d-none" id="x">&lt;b&gt; is text</div></div>']) {
        assertWellFormed(good, good);
    }
    for (const [bad, why] of [
        ['<tr><td>x</tr>', 'closed by another'], ['<div><span>x</div></span>', 'crossed'], ['<div>x', 'left open'], ['x</div>', 'closed twice'],
        ['<Qr class="x">y</Qr>', 'not a tag of the pages'], ['<tr><tdd>x</tdd></tr>', 'a typo'], ['a < b', 'a "<" that is no tag'], ['<div class="a>x</div>', 'a quote left open'],
    ]) {
        assert.throws(() => assertWellFormed(bad, bad), assert.AssertionError, why);
    }
});

check('a write request waits for the test to answer it, as a read does, and a library can chain on it', () => {
    const { demo, requests, answer, refuse } = fresh();
    demo.save();
    assert.equal(requests.length, 1);
    assert.deepEqual([requests[0].url, requests[0].type, requests[0].dataType, { ...requests[0].data }], ['/save', 'POST', 'json', { _method: 'PATCH', n: 1 }]);
    assert.equal(demo.saved, undefined, 'nothing is answered by itself');
    answer(requests[0], { ok: 1 });
    assert.deepEqual([{ ...demo.saved }, demo.finished], [{ ok: 1 }, true]);

    const again = fresh();
    again.demo.save();
    again.refuse(again.requests[0], { status: 422 });
    assert.deepEqual([{ ...again.demo.refused }, again.demo.finished], [{ status: 422 }, true]);
    // A request that is made like a read ("GET" unless it says otherwise) is told as such; an answer can be given before anybody listens.
    assert.equal(typeof refuse, 'function');

    // What a library chains on it gets the answer, or the refusal, whichever it is.
    const chained = fresh();
    chained.demo.ask();
    assert.equal(chained.requests[0].type, 'POST');
    chained.answer(chained.requests[0], { message: 'm' });
    assert.deepEqual({ ...chained.demo.value, response: { ...chained.demo.value.response } }, { ok: true, response: { message: 'm' } });
    const refused = fresh();
    refused.demo.ask();
    refused.refuse(refused.requests[0], { status: 409 });
    assert.deepEqual([refused.demo.value.ok, refused.demo.value.xhr.status], [false, 409]);
});

check('a dialog is written down with its options, and the test says what the person answered', () => {
    const { demo, dialogs, resolve } = fresh();
    demo.ask();
    assert.equal(dialogs.length, 2);
    assert.deepEqual([dialogs[0].options.title, dialogs[0].options.html, dialogs[0].options.icon], ['Sure?', '<b>x</b>', 'question']);
    // The short form: title, text, icon.
    assert.deepEqual([...dialogs[1].args], ['Done', 'Text', 'success']);
    assert.deepEqual([dialogs[1].options.title, dialogs[1].options.html, dialogs[1].options.icon], ['Done', 'Text', 'success']);
    assert.equal(demo.answer, undefined, 'the person has not answered yet');
    resolve({ isConfirmed: true }, dialogs[0]);
    assert.equal(demo.answer, true);
});

check('the ids a script reached for are told, from selectors and from getElementById alike', () => {
    const { demo, ids } = fresh();
    demo.frame();
    demo.rewrite();
    const reached = ids();
    for (const id of ['box', 'boxes', 'tabs', 'host']) assert.ok(reached.has(id), id);
    assert.ok(!reached.has('nothing'));
});

done();
