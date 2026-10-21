// Runs the inline JavaScript of the dialog behind the version in the footer (rmChangelog, in every signed-in page) without a
// browser: the version opens the dialog and asks the server once what changed, saying so while it waits; the versions are
// shown newest first with their date, the version this server runs marked, the number and the date as text; a failure is
// said in the dialog and the next opening asks again.
//
// usage: node changelog-behaviour.mjs <file with the rendered page>
//
// The page's own script is taken from the rendered HTML and run by page-sandbox.mjs. PHPUnit calls this from ChangelogTest.
import assert from 'node:assert/strict';
import { assertWellFormed, checker, loadPage, textOf } from './page-sandbox.mjs';

const open = () => {
    const p = loadPage(process.argv[2], ['const rmChangelog = {'], { href: 'http://localhost/dashboard' });
    const url = p.page('rmChangelog').url;

    return { ...p, asked: () => p.requests.filter(request => request.url === url), body: () => p.node('#rmChangelogBody').html };
};
const { check, done } = checker();
const release = (version, other = {}) => ({ version, date: '2026-10-21', date_label: '21 Okt 2026', current: false, html: '<p>Changed.</p>', ...other });

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

check('the versions newest first: number and date as text, the one the server runs marked, the changes as the server made them', () => {
    const p = open();
    p.fire('#rmVersion', 'click');
    p.answer(p.asked()[0], { version: '1.2.0', releases: [
        release('1.2.0', { current: true, html: '<h3>Diubah</h3>\n<ul>\n<li>A <code>b</code></li>\n</ul>' }),
        release('1.1.<b>0</b>', { date: null, date_label: null }),
    ] });
    const html = p.body();
    assertWellFormed(html, 'the versions');
    assert.deepEqual([...html.matchAll(/<span class="rm-release-version">([^<]*)<\/span>/g)].map(match => match[1]), ['v1.2.0', 'v1.1.&lt;b&gt;0&lt;/b&gt;']);
    assert.match(html, /<div class="rm-release is-current">\s*<div class="rm-release-head"><span class="rm-release-version">v1\.2\.0<\/span><span class="rm-release-date">21 Okt 2026<\/span><span class="rm-badge blue">Versi ini<\/span><\/div>/);
    assert.match(html, /<div class="rm-release-body"><h3>Diubah<\/h3>\n<ul>\n<li>A <code>b<\/code><\/li>\n<\/ul><\/div>/);
    // A version without a date has no date, and only the running one is marked.
    assert.match(html, /<div class="rm-release">\s*<div class="rm-release-head"><span class="rm-release-version">v1\.1\.&lt;b&gt;0&lt;\/b&gt;<\/span><\/div>/);
    assert.equal(html.match(/Versi ini/g).length, 1);
    assert.match(textOf(html), /v1\.2\.021 Okt 2026Versi ini/);
});

check('once shown, opening it again shows the same without asking again', () => {
    const p = open();
    p.fire('#rmVersion', 'click');
    p.answer(p.asked()[0], { version: '1.2.0', releases: [release('1.2.0', { current: true })] });
    const shown = p.node('#rmChangelogBody').writes;
    p.node('#rmChangelog').modal = null;
    p.fire('#rmVersion', 'click');
    assert.equal(p.node('#rmChangelog').modal, 'show');
    assert.equal(p.asked().length, 1);
    assert.equal(p.node('#rmChangelogBody').writes, shown);
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
    p.answer(p.asked()[1], { version: '1.2.0', releases: [release('1.2.0', { current: true })] });
    assert.match(p.body(), /<span class="rm-release-version">v1\.2\.0<\/span>/);
});

done();
