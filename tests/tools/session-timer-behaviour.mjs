// Runs the inline JavaScript of the session's clock (sessionTimer, in every signed-in page) without a browser: when it
// asks the server, the warnings two minutes before the idle timeout and five before the maximum length, "stay signed
// in", signing out, and the way to the login page once a request is refused because the session is over.
//
// usage: node session-timer-behaviour.mjs <file with the rendered page>
//
// The page's own script is taken from the rendered HTML and run by page-sandbox.mjs, where "$" is a small stand-in for
// jQuery and timers wait until runTimers(). PHPUnit calls this from SessionTimeoutTest.
import assert from 'node:assert/strict';
import { checker, loadPage } from './page-sandbox.mjs';

const { node, page, requests, dialogs, resolve, answer, refuse, runTimers } = loadPage(
    process.argv[2],
    ['const sessionTimer = {'],
    { href: 'http://localhost/dashboard' }
);
const { check, done } = checker();
const timer = page('sessionTimer');
const to = url => requests.filter(request => request.url === url);
const asked = () => to(timer.urls.status).length;
const lastAsked = () => to(timer.urls.status).at(-1);
const dialog = () => dialogs.at(-1).options;
const fresh = () => {
    Object.assign(timer, { ending: false, warnedAbsolute: false });
    page(`location.href = 'http://localhost/dashboard'`);
};
const failed = xhr => node('(document)').handlers.ajaxError.forEach(handler => handler({}, xhr));
const signedOut = () => node('#logout-form').triggered.filter(event => event === 'submit').length;

check('at first it waits (no request), then asks the server how long is left', () => {
    assert.equal(asked(), 0);
    runTimers();
    assert.equal(asked(), 1);
    assert.equal(lastAsked().type, 'GET');
});

check('time left (activity in another tab, say): no warning, it waits and asks again', () => {
    const shown = dialogs.length;
    answer(lastAsked(), { idle_left: 1500, absolute_left: 27000 });
    assert.equal(dialogs.length, shown);
    runTimers();
    assert.equal(asked(), 2);
});

check('two minutes left: the warning counts down, and offers to stay', () => {
    answer(lastAsked(), { idle_left: 120, absolute_left: 27000 });
    const options = dialog();
    assert.equal(options.title, 'Sesi akan berakhir');
    assert.match(options.html, /hampir 30 menit/);
    assert.match(options.html, /<b class="session-countdown">2:00<\/b>/);
    assert.deepEqual([options.confirmButtonText, options.cancelButtonText], ['Tetap masuk', 'Keluar']);
    assert.equal(options.timer, 122000);
    assert.deepEqual([options.allowOutsideClick, options.allowEscapeKey, options.showCancelButton], [false, false, true]);
});

check('"Tetap masuk" asks the server to keep the session (a POST), then waits again', () => {
    resolve({ isConfirmed: true });
    const kept = to(timer.urls.keepAlive).at(-1);
    assert.equal(kept.type, 'POST');
    const shown = dialogs.length;
    answer(kept, { idle_left: 1800, absolute_left: 26900 });
    assert.equal(dialogs.length, shown);
    const before = asked();
    runTimers();
    assert.equal(asked(), before + 1);
});

check('the countdown ran out: it asks the server once more (which has ended the session by then)', () => {
    answer(lastAsked(), { idle_left: 90, absolute_left: 26800 });
    assert.equal(dialog().timer, 92000);
    const before = asked();
    resolve({ isConfirmed: false, dismiss: 'timer' });
    assert.equal(asked(), before + 1);
});

check('"Keluar" signs out through the logout form', () => {
    answer(lastAsked(), { idle_left: 100, absolute_left: 26700 });
    resolve({ isConfirmed: false, dismiss: 'cancel' });
    assert.equal(signedOut(), 1);
    assert.equal(timer.ending, true);
});

check('five minutes before the maximum length: a warning that cannot be put off; "Login ulang sekarang" signs out', () => {
    fresh();
    timer.check();
    answer(lastAsked(), { idle_left: 1700, absolute_left: 300 });
    const options = dialog();
    assert.match(options.html, /paling lama 8 jam/);
    assert.match(options.html, />5:00</);
    assert.deepEqual([options.confirmButtonText, options.cancelButtonText], ['Login ulang sekarang', 'Nanti']);
    resolve({ isConfirmed: true });
    assert.equal(signedOut(), 2);
});

check('"Nanti" is the person being there (a POST); the maximum is not warned of twice; it waits until the session ends', () => {
    fresh();
    timer.check();
    answer(lastAsked(), { idle_left: 1700, absolute_left: 280 });
    const kept = to(timer.urls.keepAlive).length;
    resolve({ isConfirmed: false, dismiss: 'cancel' });
    assert.equal(signedOut(), 2);
    assert.equal(to(timer.urls.keepAlive).length, kept + 1);
    const shown = dialogs.length;
    answer(to(timer.urls.keepAlive).at(-1), { idle_left: 1800, absolute_left: 270 });
    assert.equal(dialogs.length, shown);
    const before = asked();
    runTimers();
    assert.equal(asked(), before + 1);
});

check('the warning of the maximum counts down to it, and closes when idleness would end the session first', () => {
    fresh();
    timer.check();
    answer(lastAsked(), { idle_left: 100, absolute_left: 290 });
    assert.match(dialog().html, /paling lama 8 jam.*>4:50</);
    assert.equal(dialog().timer, 102000);
    resolve({ isConfirmed: false, dismiss: 'timer' });
});

check('a check that fails for another reason (no network) is tried again later', () => {
    fresh();
    const before = asked();
    refuse(lastAsked(), { status: 0 });
    runTimers();
    assert.equal(asked(), before + 1);
});

check('ended by the server, which says why on the login page: straight there, without a dialog', () => {
    fresh();
    const shown = dialogs.length;
    failed({ status: 401, responseJSON: { message: 'Session expired.', session_ended: true, reason: 'idle' } });
    assert.equal(page('location.href'), timer.urls.login);
    assert.equal(dialogs.length, shown);
});

check('refused without a reason (signed out elsewhere, a stale form): a dialog first, then the login page', () => {
    fresh();
    failed({ status: 419, responseJSON: { message: 'CSRF token mismatch.' } });
    assert.deepEqual([dialog().title, dialog().confirmButtonText], ['Sesi berakhir', 'Login']);
    assert.equal(page('location.href'), 'http://localhost/dashboard');
    resolve({ isConfirmed: true });
    assert.equal(page('location.href'), timer.urls.login);
});

check('once only: a second refusal opens no second dialog', () => {
    const shown = dialogs.length;
    failed({ status: 401 });
    assert.equal(dialogs.length, shown);
});

check('other failures are not about the session', () => {
    fresh();
    const shown = dialogs.length;
    failed({ status: 500 });
    failed({ status: 403, responseJSON: { message: 'You are not allowed.' } });
    failed({ status: 0 });
    assert.equal(dialogs.length, shown);
    assert.equal(page('location.href'), 'http://localhost/dashboard');
    assert.equal(timer.ending, false);
});

check('the countdown reads as minutes and seconds', () => {
    assert.deepEqual([65, 0, -3, 600, 119.6].map(seconds => timer.clock(seconds)), ['1:05', '0:00', '0:00', '10:00', '2:00']);
});

done();
