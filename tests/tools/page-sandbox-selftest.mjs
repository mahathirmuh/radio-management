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
import { checker, loadPage } from './page-sandbox.mjs';

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

check('the ids a script reached for are told, from selectors and from getElementById alike', () => {
    const { demo, ids } = fresh();
    demo.frame();
    demo.rewrite();
    const reached = ids();
    for (const id of ['box', 'boxes', 'tabs', 'host']) assert.ok(reached.has(id), id);
    assert.ok(!reached.has('nothing'));
});

done();
