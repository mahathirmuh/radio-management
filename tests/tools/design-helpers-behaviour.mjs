// The shared helpers of the design layer (main.components.js.js): the foot of a table (renderPagination), the sorting
// arrows of its headers (initSorting), the toast and the busy button (rmFeedback), the question in the design's dialog
// (rmDialog), and the dates of the pickers (rmPickers). Run by ShellTest with a rendered page.
// usage: node tests/tools/design-helpers-behaviour.mjs <rendered page.html>
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const [file] = process.argv.slice(2);
const { check, done } = checker();
const open = () => loadPage(file, ['function renderTableWithFeatures(', 'const rmFeedback = {', 'const rmPickers = {'], { href: 'http://localhost/dashboard' });
const meta = (current, last, total = last * 25) => ({ current_page: current, last_page: last, total, from: (current - 1) * 25 + 1, to: Math.min(current * 25, total) });

check('the foot says what is shown in the words of the page, and offers the first, the last and the pages next to this one', () => {
    const page = open();
    page.page('renderPagination')({ meta: meta(5, 12), limit: 25, container: '#foot', labels: { showing: 'Menampilkan', of: 'dari', rows: 'Baris per halaman', noun: 'radio' } });
    const html = page.node('#foot').html;
    assert.match(html, /<span class="rm-foot-text">Menampilkan 101–125 dari 300 radio<\/span>/);
    assert.match(html, /Baris per halaman/);
    const pages = [...html.matchAll(/data-page="(\d+)"(?: aria-current="page")?>(\d+)<\/button>/g)].map(match => Number(match[2]));
    assert.deepEqual(pages, [1, 4, 5, 6, 12]);
    assert.equal((html.match(/rm-pager-dots/g) || []).length, 2);
    assert.match(html, /class="rm-pager-page btn-pagination is-on" data-page="5" aria-current="page">5<\/button>/);
    assert.match(html, /class="dropdown-item rm-size-item pagination-limit is-on" data-limit="25">25<\/a>/);
});

check('the first page has no way back and the last no way on; one page has no dots', () => {
    const page = open();
    page.page('renderPagination')({ meta: meta(1, 1, 7), container: '#foot' });
    const html = page.node('#foot').html;
    assert.match(html, /btn-previous" data-page="0" aria-label="Previous page" disabled>/);
    assert.match(html, /btn-next" data-page="2" aria-label="Next page" disabled>/);
    assert.doesNotMatch(html, /rm-pager-dots/);
    assert.match(html, /Showing 1–7 of 7</);
});

check('a page and a size chosen at the foot are handed to the page', () => {
    const page = open();
    const asked = [];
    page.page('renderPagination')({ meta: meta(2, 3), container: '#foot', onPageChange: n => asked.push(['page', n]), onLimitChange: n => asked.push(['limit', n]) });
    page.node('(object)').data.page = 3;
    page.fire('#foot .btn-pagination, .btn-previous, .btn-next', 'click');
    page.node('(object)').data.limit = 100;
    page.fire('#foot .pagination-limit', 'click', { preventDefault() {} });
    assert.deepEqual(asked, [['page', 3], ['limit', 100]]);
});

check('no rows, no foot', () => {
    const page = open();
    page.page('renderPagination')({ meta: { total: 0 }, container: '#foot' });
    assert.equal(page.node('#foot').hidden, true);
});

check('a toast says its text as text, at the foot, and goes away by itself', () => {
    const page = open();
    page.page('rmFeedback').toast('<b>Radio</b> diarsipkan');
    const dialog = page.dialogs.at(-1).options;
    assert.equal(dialog.toast, true);
    assert.equal(dialog.position, 'bottom');
    assert.equal(dialog.titleText, '<b>Radio</b> diarsipkan');
    assert.equal(dialog.html, undefined);
    assert.equal(dialog.title, undefined);
    assert.ok(dialog.timer > 0);
    assert.equal(dialog.showConfirmButton, false);
    page.page('rmFeedback').toast('Gagal', 'error');
    assert.match(page.dialogs.at(-1).options.customClass.popup, /rm-toast-error/);
});

check('a button is busy, then not', () => {
    const page = open();
    page.page('rmFeedback').busy('#save', true);
    assert.equal(page.node('#save').classes.has('is-busy'), true);
    assert.equal(page.node('#save').props.disabled, true);
    page.page('rmFeedback').busy('#save', false);
    assert.equal(page.node('#save').classes.has('is-busy'), false);
    assert.equal(page.node('#save').props.disabled, false);
});

check('a question in the design dialog: the tone, the icon, text as text, the kind of button', () => {
    const page = open();
    page.page('rmDialog').confirm({ tone: 'red', icon: 'trash', title: 'Hapus?', text: '<b>x</b>', confirmText: 'Hapus', confirmKind: 'destructive' });
    const dialog = page.dialogs.at(-1).options;
    assert.equal(dialog.text, '<b>x</b>');
    assert.equal(dialog.html, undefined);
    assert.equal(dialog.iconHtml, '<i class="ti ti-trash"></i>');
    assert.equal(dialog.customClass.icon, 'rm-swal-icon tone-red');
    assert.equal(dialog.customClass.confirmButton, 'btn-rm destructive');
    assert.equal(dialog.buttonsStyling, false);
    assert.equal(dialog.focusCancel, true);
    page.page('rmDialog').confirm({ title: 'Lanjut?', html: '<b>ya</b>' });
    assert.equal(page.dialogs.at(-1).options.html, '<b>ya</b>');
    assert.equal(page.dialogs.at(-1).options.text, undefined);
});

check('dates of the pickers: YYYY-MM-DD, read back, and shown as "01 Okt 2026"', () => {
    const page = open();
    const pickers = page.page('rmPickers');
    assert.equal(pickers.label('2026-10-01'), '01 Okt 2026');
    assert.equal(pickers.label('2026-05-31'), '31 Mei 2026');
    assert.equal(pickers.label(''), '');
    assert.equal(pickers.label('kemarin'), '');
    assert.equal(pickers.parse('01-10-2026'), null);
    assert.equal(pickers.iso(pickers.parse('2026-02-03')), '2026-02-03');
    assert.equal(pickers.weekdays[0], 'Sn');
});

done();
