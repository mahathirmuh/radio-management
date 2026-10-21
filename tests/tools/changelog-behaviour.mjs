// Runs the inline JavaScript of the dialog behind the version in the footer (rmChangelog, in every signed-in page) without a
// browser: the version opens the dialog and asks the server once what changed, saying so while it waits; the versions are
// drawn as the timeline of the design (ChangelogDialog): a legend, each series as a node with its minor release and its
// patches below it as branches, a band where a major version begins, every version in the colour of its strongest kind of
// change, the running one marked; every text of the answer escaped; a failure is said in the dialog and the next opening
// asks again.
//
// usage: node changelog-behaviour.mjs <file with the rendered page>
//
// The page's own script is taken from the rendered HTML and run by page-sandbox.mjs. PHPUnit calls this from ChangelogDialogTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const open = () => {
    // (The helpers of every page first: the band of a major version is written with rmFill.)
    const p = loadPage(process.argv[2], ['function debounce(', 'const rmChangelog = {'], { href: 'http://localhost/dashboard' });
    const url = p.page('rmChangelog').url;

    return { ...p, asked: () => p.requests.filter(request => request.url === url), body: () => p.node('#rmChangelogBody').html };
};
const { check, done } = checker();
const section = (kind, heading, html = '<ul>\n<li>A change.</li>\n</ul>') => ({ heading, kind, html });
const release = (version, sections, other = {}) => ({ version, date: '2026-10-21', date_label: '21 Okt 2026', current: false, sections, ...other });
// Newest first, as the server sends them: a minor release that is running, a series with two patches, a series without its
// .0, the first major version.
const history = () => [
    release('1.3.0', [section('added', 'Ditambahkan')], { current: true }),
    release('1.2.2', [section('fixed', 'Diperbaiki')]),
    release('1.2.1', [section('security', 'Keamanan'), section('fixed', 'Diperbaiki')]),
    release('1.2.0', [section('changed', 'Diubah')]),
    release('1.1.3', [section(null, 'Catatan')]),
    release('1.0.0', [section(null, null, '<p>The first.</p>')], { date: null, date_label: null }),
];
const shown = (releases = history()) => {
    const p = open();
    p.fire('#rmVersion', 'click');
    p.answer(p.asked()[0], { version: '1.3.0', releases });

    return p;
};
// The rows of the timeline: [version, classes] in the order they are drawn.
const rows = html => [...html.matchAll(/<div class="(rm-cl-release [^"]*)" style="--rm-cl-delay: (\d+)ms">[\s\S]*?<span class="rm-cl-version">v([^<]*)<\/span>/g)]
    .map(match => [match[3], match[1], Number(match[2])]);

check('the version in the footer opens the dialog and asks the server what changed, once', () => {
    const p = open();
    assert.equal(p.asked().length, 0);
    p.fire('#rmVersion', 'click');
    assert.equal(p.node('#rmChangelog').modal, 'show');
    assert.equal(p.asked().length, 1);
    assert.match(p.page('rmChangelog.url'), /\/changelog$/);
    assert.match(p.body(), /Memuat riwayat versi/);
    // Opened again while it waits: no second request.
    p.fire('#rmVersion', 'click');
    assert.equal(p.asked().length, 1);
});

check('a legend: a minor and a patch release, then the kinds of change the versions have, in their order, in English on an Indonesian page too', () => {
    const html = shown().body();
    assertWellFormed(html, 'the timeline');
    const legend = html.match(/<div class="rm-cl-legend">([\s\S]*?)<\/div>/)[1];
    assert.equal(textOf(legend), 'Minor releasePatch releaseAddedChangedFixedSecurity');
    assert.match(legend, /<span class="rm-cl-key rm-cl-kind-added"><span class="rm-cl-key-dot" aria-hidden="true"><\/span>Added<\/span>/);
    assert.doesNotMatch(legend, /Removed|Deprecated/, 'only the kinds there are');
});

check('each series is its minor release with its patches below it, newest first; a series without its .0 hangs on its oldest', () => {
    const html = shown().body();
    assert.deepEqual(rows(html).map(([version, classes]) => [version, classes]), [
        ['1.3.0', 'rm-cl-release rm-cl-kind-added is-parent is-current has-next'],
        ['1.2.0', 'rm-cl-release rm-cl-kind-changed is-parent has-next'],
        ['1.2.2', 'rm-cl-release rm-cl-kind-fixed is-patch has-next'],
        // The strongest kind of change colours the version: Keamanan over Diperbaiki.
        ['1.2.1', 'rm-cl-release rm-cl-kind-security is-patch has-next'],
        // A heading of another kind: the colour of "changed".
        ['1.1.3', 'rm-cl-release rm-cl-kind-changed is-parent has-next'],
        ['1.0.0', 'rm-cl-release rm-cl-kind-changed is-parent is-major'],
    ]);
    // The rows rise one after the other.
    assert.deepEqual(rows(html).map(row => row[2]), [0, 45, 90, 135, 180, 225]);
});

check('the nodes: the series on a minor release, nothing on a patch (which hangs on a branch), the major number on a major release', () => {
    const html = shown().body();
    const rail = version => html.split(`<span class="rm-cl-version">v${version}</span>`)[0].split('<div class="rm-cl-rail" aria-hidden="true">').pop();
    assert.match(rail('1.3.0'), /^<span class="rm-cl-line is-bottom"><\/span><span class="rm-cl-ping"><\/span><span class="rm-cl-node">1\.3<\/span><\/div>/);
    assert.match(rail('1.2.0'), /^<span class="rm-cl-line is-top"><\/span><span class="rm-cl-line is-bottom"><\/span><span class="rm-cl-node">1\.2<\/span>/);
    assert.match(rail('1.2.2'), /^<span class="rm-cl-line is-top"><\/span><span class="rm-cl-line is-bottom"><\/span><span class="rm-cl-elbow"><\/span><span class="rm-cl-node"><\/span>/);
    assert.match(rail('1.0.0'), /^<span class="rm-cl-line is-top"><\/span><span class="rm-cl-node">1\.0<\/span>/, 'the last has no line below it');
    // Only the running version pings.
    assert.equal(html.match(/rm-cl-ping/g).length, 1);
});

check('the head of a version: its number, its date, "Major release" on a major release, "Versi ini" on the running one', () => {
    const html = shown().body();
    assert.match(html, /<div class="rm-cl-head"><span class="rm-cl-version">v1\.3\.0<\/span><span class="rm-cl-date">21 Okt 2026<\/span><span class="rm-badge blue">Versi ini<\/span><\/div>/);
    assert.match(html, /<div class="rm-cl-head"><span class="rm-cl-version">v1\.0\.0<\/span><span class="rm-badge rm-cl-major">Major release<\/span><\/div>/, 'no date: none shown');
    assert.equal(html.match(/Versi ini/g).length, 1);
    // A band where a major version begins: here only one, "Versi 1", above the first version, without a line through it.
    assert.deepEqual([...html.matchAll(/<div class="rm-cl-era( [^"]*)?">/g)].map(match => match[1] ?? ''), ['']);
    assert.match(html, /<div class="rm-cl-era-label"><span>Versi 1<\/span><span class="rm-cl-hair"><\/span><\/div>/);
});

check('a second major version opens with its band, the line running through it in the colour of the version above', () => {
    const html = shown([release('2.0.0', [section('removed', 'Dihapus')], { current: true }), release('1.4.0', [section('added', 'Ditambahkan')])]).body();
    assert.deepEqual([...html.matchAll(/<div class="rm-cl-era( [^"]*)?">/g)].map(match => match[1] ?? ''), ['', ' has-line rm-cl-kind-removed']);
    assert.match(html, /<div class="rm-cl-era has-line rm-cl-kind-removed"><div class="rm-cl-rail"><span class="rm-cl-era-line"><\/span><\/div><div class="rm-cl-era-label"><span>Versi 1<\/span>/);
    assert.match(html, /<span class="rm-cl-node">2\.0<\/span>/);
});

check('the notes: a pill per kind of change, named as in the legend, then the markup of the server; every text of the answer escaped', () => {
    const html = shown([release('1.2.<b>0</b>', [section('fixed', 'Diperbaiki'), section('mystery', 'Lain <img src=x onerror=alert(1)>')],
        { current: true, date_label: '<i>21</i> Okt', language: 'en' })]).body();
    assertWellFormed(html, 'the timeline');
    assert.doesNotMatch(html, /<img|<b>0|<i>21/);
    assert.match(html, /<span class="rm-cl-version">v1\.2\.&lt;b&gt;0&lt;\/b&gt;<\/span><span class="rm-cl-date">&lt;i&gt;21&lt;\/i&gt; Okt<\/span>/);
    // A kind the dialog knows by its name in the legend, whatever the heading in the file; the notes marked with the
    // language the server says they are written in.
    assert.match(html, /<div class="rm-cl-notes" lang="en"><div class="rm-cl-section rm-cl-kind-fixed"><span class="rm-cl-pill"><span class="rm-cl-pill-dot"><\/span>Fixed<\/span><\/div><div class="rm-cl-text rm-cl-kind-fixed"><ul>\n<li>A change\.<\/li>\n<\/ul><\/div>/);
    // A kind the dialog does not know: grey, with the heading as the file writes it.
    assert.match(html, /<div class="rm-cl-section rm-cl-kind-other"><span class="rm-cl-pill"><span class="rm-cl-pill-dot"><\/span>Lain &lt;img src=x onerror=alert\(1\)&gt;<\/span><\/div><div class="rm-cl-text rm-cl-kind-other">/);
    // Notes without a heading: no pill. No language from the server: that of CHANGELOG.md, Indonesian.
    const first = shown().body().split('<span class="rm-cl-version">v1.0.0</span>')[1];
    assert.match(first, /<div class="rm-cl-notes" lang="id"><div class="rm-cl-text rm-cl-kind-other"><p>The first\.<\/p><\/div><\/div>/);
    assert.match(shown([release('1.0.0', [section('added', 'Added')], { language: '"><b>x' })]).body(), /<div class="rm-cl-notes" lang="&quot;&gt;&lt;b&gt;x">/);
});

check('once shown, opening it again shows the same without asking again', () => {
    const p = shown();
    const writes = p.node('#rmChangelogBody').writes;
    p.node('#rmChangelog').modal = null;
    p.fire('#rmVersion', 'click');
    assert.equal(p.node('#rmChangelog').modal, 'show');
    assert.equal(p.asked().length, 1);
    assert.equal(p.node('#rmChangelogBody').writes, writes);
});

check('no versions in the answer: it says there are no notes yet', () => {
    const p = open();
    p.fire('#rmVersion', 'click');
    p.answer(p.asked()[0], { version: '1.2.0', releases: [] });
    assert.equal(p.body(), '<div class="rm-changelog-note">Belum ada catatan perubahan.</div>');
});

check('a failure is said in the dialog, and the next opening asks again', () => {
    const p = open();
    p.fire('#rmVersion', 'click');
    p.refuse(p.asked()[0], { status: 500 });
    assert.match(p.body(), /<div class="rm-changelog-note">Riwayat versi tidak bisa dimuat\./);
    p.fire('#rmVersion', 'click');
    assert.equal(p.asked().length, 2);
    p.answer(p.asked()[1], { version: '1.2.0', releases: [release('1.2.0', [section('changed', 'Diubah')], { current: true })] });
    assert.match(p.body(), /<span class="rm-cl-version">v1\.2\.0<\/span>/);
});

done();
