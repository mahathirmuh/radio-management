// The sidebar of every page (main.sidebar.sidebar): the icon rail and the memory of it, the groups that fold, and the
// name of an icon in the rail. Run by ShellTest with a rendered page.
// usage: node tests/tools/sidebar-behaviour.mjs <rendered page.html>
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const [file] = process.argv.slice(2);
const { check, done } = checker();
const open = (storage = {}) => loadPage(file, ['const sidebarUi = {'], { href: 'http://localhost/radio-management/master/radio-type', storage });
const point = (page, attrs) => Object.assign(page.node('(object)').attrs, attrs);

check('a first visit shows the full sidebar', () => {
    const page = open();
    assert.equal(page.page('sidebarUi.rail'), false);
    assert.ok(!page.node('html').classes.has('rm-rail'));
    assert.equal(page.node('#sidebarControl').attrs['aria-pressed'], 'false');
});

check('the rail comes back as it was left', () => {
    const page = open({ sidebarState: 'collapsed' });
    assert.equal(page.page('sidebarUi.rail'), true);
    assert.ok(page.node('html').classes.has('rm-rail'));
    assert.equal(page.node('#sidebarControl').attrs['aria-pressed'], 'true');
});

check('the button in the top bar switches between the two, and the choice is kept', () => {
    const page = open();
    page.fire('#sidebarControl', 'click');
    assert.ok(page.node('html').classes.has('rm-rail'));
    assert.equal(page.node('#sidebarControl').attrs['aria-pressed'], 'true');
    assert.deepEqual(page.stored(), { sidebarState: 'collapsed' });
    page.fire('#sidebarControl', 'click');
    assert.ok(!page.node('html').classes.has('rm-rail'));
    assert.deepEqual(page.stored(), { sidebarState: 'expanded' });
});

check('a group opens and closes, and leaves the page where it is', () => {
    const page = open();
    point(page, { 'data-rm-group': '7', 'data-rm-first': 'http://localhost/radio-management/master/company' });
    page.fire('(document)', 'click [data-rm-group]');
    assert.equal(page.node('[data-rm-group="7"]').attrs['aria-expanded'], 'true');
    assert.ok(page.node('#rm-navgroup-7').classes.has('is-open'));
    point(page, { 'data-rm-group': '7', 'data-rm-first': 'http://localhost/radio-management/master/company' });
    page.fire('(document)', 'click [data-rm-group]');
    assert.equal(page.node('[data-rm-group="7"]').attrs['aria-expanded'], 'false');
    assert.ok(!page.node('#rm-navgroup-7').classes.has('is-open'));
    assert.equal(page.page('location.href'), 'http://localhost/radio-management/master/radio-type');
});

check('in the rail the icon of a group goes to its first page', () => {
    const page = open({ sidebarState: 'collapsed' });
    point(page, { 'data-rm-group': '7', 'data-rm-first': 'http://localhost/radio-management/master/company' });
    page.fire('(document)', 'click [data-rm-group]');
    assert.equal(page.page('location.href'), 'http://localhost/radio-management/master/company');
    assert.ok(!page.node('#rm-navgroup-7').classes.has('is-open'));
});

check('a group without a page of its own for the person stays where it is in the rail', () => {
    const page = open({ sidebarState: 'collapsed' });
    point(page, { 'data-rm-group': '9', 'data-rm-first': '' });
    page.fire('(document)', 'click [data-rm-group]');
    assert.equal(page.page('location.href'), 'http://localhost/radio-management/master/radio-type');
});

check('the rail names the icon under the pointer, next to it, until the pointer leaves', () => {
    const page = open({ sidebarState: 'collapsed' });
    page.page('sidebarUi').rectOf = () => ({ right: 68, top: 150, height: 36 });
    page.fire('(document)', 'mouseleave focusout [data-rm-tip]');
    assert.equal(page.node('#rmNavTip').attrs.hidden, 'hidden');
    point(page, { 'data-rm-tip': 'Riwayat Radio' });
    page.fire('(document)', 'mouseenter focusin [data-rm-tip]');
    assert.equal(page.node('#rmNavTip').text, 'Riwayat Radio');
    assert.equal(page.node('#rmNavTip').attrs.hidden, undefined);
    page.fire('(document)', 'mouseleave focusout [data-rm-tip]');
    assert.equal(page.node('#rmNavTip').attrs.hidden, 'hidden');
});

check('the name goes as text, never as markup', () => {
    const page = open({ sidebarState: 'collapsed' });
    page.page('sidebarUi').rectOf = () => ({ right: 68, top: 150, height: 36 });
    point(page, { 'data-rm-tip': '<b>Riwayat</b>' });
    page.fire('(document)', 'mouseenter focusin [data-rm-tip]');
    assert.equal(page.node('#rmNavTip').text, '<b>Riwayat</b>');
    assert.equal(page.node('#rmNavTip').html, '');
});

check('the full sidebar shows the names itself: no name pops up there', () => {
    const page = open();
    page.page('sidebarUi').rectOf = () => ({ right: 236, top: 150, height: 36 });
    point(page, { 'data-rm-tip': 'Riwayat Radio' });
    page.fire('(document)', 'mouseenter focusin [data-rm-tip]');
    assert.equal(page.node('#rmNavTip').text, '');
    assert.equal(page.node('#rmNavTip').attrs.hidden, 'hidden');
});

check('leaving the rail or scrolling the sidebar takes the name away', () => {
    const page = open({ sidebarState: 'collapsed' });
    page.page('sidebarUi').rectOf = () => ({ right: 68, top: 150, height: 36 });
    point(page, { 'data-rm-tip': 'Riwayat Radio' });
    page.fire('(document)', 'mouseenter focusin [data-rm-tip]');
    page.fire('.rm-sidebar', 'scroll');
    assert.equal(page.node('#rmNavTip').attrs.hidden, 'hidden');
    point(page, { 'data-rm-tip': 'Riwayat Radio' });
    page.fire('(document)', 'mouseenter focusin [data-rm-tip]');
    page.fire('#sidebarControl', 'click');
    assert.equal(page.node('#rmNavTip').attrs.hidden, 'hidden');
});

done();
