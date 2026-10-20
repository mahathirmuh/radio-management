// A page's inline JavaScript, run without a browser. Used by the *-behaviour.mjs checkers here.
//
// The scripts are taken from the rendered HTML (the page's own one and the global helper scripts
// it uses, such as debounce and renderTable) and run in a sandbox where "$" is a small stand-in
// for jQuery that remembers what was written to each selector. Requests wait until the test
// answers them, and timers fire only when the test lets them.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

/** What a browser shows for a piece of HTML: the tags dropped, the entities decoded. */
export const textOf = html => html.replace(/<[^>]*>/g, '')
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0*39;/g, "'").replace(/&amp;/g, '&');

/** Whether a text has no half of a character that is written with two (such a text cannot be put into an address: encodeURIComponent throws). */
export const isWellFormed = text => !/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/.test(text);

/**
 * Fails when markup that a script has written is not well formed: a tag that is not an HTML tag (a typo), one that is closed
 * by another, left open or closed twice. The sandbox does not parse what a page writes, so without this a misspelled tag or
 * a missing end passes every check that looks at one attribute or one cell at a time (a browser would lay the page out wrongly).
 * The tags of a page are a short list: a tag outside it is a mistake until it is added here.
 */
export function assertWellFormed(html, what = 'the markup') {
    const known = new Set(['a', 'b', 'button', 'code', 'dd', 'div', 'dl', 'dt', 'em', 'i', 'li', 'ol', 's', 'small', 'span', 'strong', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul']);
    const unpaired = new Set(['br', 'hr', 'img', 'input']);
    const open = [];
    for (const match of String(html).matchAll(/<(\/?)([A-Za-z][A-Za-z0-9-]*)((?:"[^"]*"|'[^']*'|[^'">])*)>/g)) {
        const [, closing, tag] = match;
        assert.ok(known.has(tag) || unpaired.has(tag), `${what}: <${tag}> is no tag of the pages: ${match[0].slice(0, 80)}`);
        if (unpaired.has(tag)) continue;
        if (closing === '') {
            open.push(tag);
        } else {
            assert.equal(open.pop(), tag, `${what}: </${tag}> closes ${open.at(-1) ?? 'nothing'}: ${String(html).slice(Math.max(0, match.index - 60), match.index + 30)}`);
        }
    }
    assert.deepEqual(open, [], `${what}: tags left open`);
    // Nothing is left between "<" and the end that is not a tag either (a "<" that is text is written as &lt;).
    assert.doesNotMatch(String(html).replace(/<(\/?)([A-Za-z][A-Za-z0-9-]*)((?:"[^"]*"|'[^']*'|[^'">])*)>/g, ''), /</, `${what}: a "<" that is no tag`);
}

/**
 * @param {string} file the rendered page
 * @param {string[]} markers one piece of text per inline script to run, in that order
 * @param {{href: string, framePath?: string, storage?: Object<string, string>}} at the address of the page, the path a frame made by the
 *     page shows unless a test says otherwise, and what the browser has kept for the page (localStorage) before it runs
 */
export function loadPage(file, markers, { href, framePath = '/', storage = {} }) {
    const html = fs.readFileSync(file, 'utf8');
    const blocks = [...html.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script\s*>/gi)].map(match => match[1]);
    const scripts = markers.map(marker => {
        const found = blocks.filter(block => block.includes(marker));
        assert.equal(found.length, 1, `expected one inline script with "${marker}", found ${found.length}`);
        return found[0];
    });

    // One record per selector string. The element a handler is called for ("this" in the handler that is
    // running, as jQuery calls it) is the record "(object)": a test sets its attributes before it fires the
    // handler, and fire() hands the record back, a new one being there for the next firing. Any other object a
    // script reaches for (document, the global object, nothing) is an element the test never filled: it gets an
    // empty record of its own, so a handler that reads from the wrong one finds nothing.
    //
    // A selector that matches several elements ($("#tabs a")) has one record of its own; what is done to it is done
    // to the elements a test says are among them (fire()'s "member" and "siblings"), so that a script that marks
    // the clicked element and unmarks all the others can be told from one that does it in the wrong order.
    const nodes = new Map();
    const members = new Map();
    let element = null;
    const newRecord = () => ({ html: '', text: '', wrote: null, writes: 0, value: '', attrs: {}, props: {}, data: {}, classes: new Set(), handlers: {}, hidden: false, modal: null, removed: 0, triggered: [], movedTo: null });
    const node = selector => {
        if (!nodes.has(selector)) {
            nodes.set(selector, newRecord());
        }
        return nodes.get(selector);
    };
    const records = start => [...nodes].filter(([selector]) => selector.startsWith(start)).map(([, record]) => record);
    // The classes of "a b", as jQuery reads them.
    const names = name => String(name ?? '').split(/\s+/).filter(Boolean);
    // What is written into an element replaces what was inside it: the elements that were inside are gone, and
    // those that take their place have no attributes, classes or handlers of theirs.
    const forget = gone => gone.forEach(record => Object.assign(record, { html: '', text: '', wrote: null, attrs: {}, props: {}, data: {}, classes: new Set(), handlers: {} }));

    // A header cell (th[data-key]) is there when the table has one: in the <thead> the script
    // wrote, else in the rendered page. The global table helper binds its sorting to the cells
    // it finds and reads their text.
    const headerCell = selector => {
        const match = selector.match(/^(.*) th\[data-key="([\w-]+)"\]$/);
        if (!match) return null;
        const head = nodes.get(`${match[1]} thead`);
        const cell = new RegExp(`<th\\b[^>]*\\bdata-key="${match[2]}"[^>]*>([\\s\\S]*?)</th>`).exec(head && head.wrote ? head.html : html);
        return cell ? cell[1] : null;
    };
    const exists = selector => headerCell(selector) !== null;

    const requests = [];
    const ready = [];
    const timers = new Map();
    let lastTimer = 0;
    const appended = [];
    const asked = new Set(); // ids asked for through document.getElementById

    function $(target) {
        if (typeof target === 'function') {
            ready.push(target);
            return undefined;
        }
        const known = typeof target === 'string' || (target !== null && target !== undefined && (target === element || target === documentStub));
        const selector = typeof target === 'string' ? target : (target === documentStub ? '(document)' : (known ? '(object)' : '(not an element of the test)'));
        const n = known ? node(selector) : newRecord();
        // The record of the selector, and the elements the test says it matches.
        const all = () => [n, ...(members.get(selector) ?? [])];
        const api = {
            get length() { return exists(selector) ? 1 : 0; },
            html(value) {
                if (value === undefined) return n.html;
                Object.assign(n, { html: String(value), text: '', wrote: 'html', writes: n.writes + 1 });
                forget(records(`${selector} `));
                const head = selector.match(/^(.*) thead$/);
                if (head) forget(records(`${head[1]} th[`)); // a new <thead> is new header cells
                return api;
            },
            text(value) {
                if (value !== undefined) {
                    Object.assign(n, { html: '', text: String(value), wrote: 'text', writes: n.writes + 1 });
                    forget(records(`${selector} `));
                    return api;
                }
                if (n.wrote === 'text') return n.text;
                // Its own markup as text, and what was written as text into the elements inside it.
                return textOf(n.wrote === 'html' ? n.html : headerCell(selector) ?? '')
                    + records(`${selector} `).map(record => (record.wrote === 'text' ? record.text : '')).join('');
            },
            val(value) { if (value === undefined) return n.value; n.value = value; return api; },
            attr(name, value) { if (value === undefined) return n.attrs[name]; all().forEach(record => { record.attrs[name] = value; }); return api; },
            removeAttr(name) { all().forEach(record => { delete record.attrs[name]; }); return api; },
            prop(name, value) { if (value === undefined) return n.props[name]; all().forEach(record => { record.props[name] = value; }); return api; },
            data(name) { return n.data[name]; },
            // (A checkbox is ticked when its "checked" property says so; nothing else is asked of an element here.)
            is(what) { return what === ':checked' ? !!n.props.checked : false; },
            addClass(name) { all().forEach(record => names(name).forEach(one => record.classes.add(one))); return api; },
            removeClass(name) { all().forEach(record => names(name).forEach(one => record.classes.delete(one))); return api; },
            toggleClass(name, on) { all().forEach(record => names(name).forEach(one => (on ? record.classes.add(one) : record.classes.delete(one)))); return api; },
            on(event, filter, handler) {
                const key = handler ? `${event} ${filter}` : event;
                (n.handlers[key] ||= []).push(handler || filter);
                return api;
            },
            off(event) { if (event) delete n.handlers[event]; else n.handlers = {}; return api; },
            show() { n.hidden = false; return api; },
            hide() { n.hidden = true; return api; },
            empty() { return api.html(''); },
            // Taking out "#id" takes out the elements the script made with that id.
            remove() {
                n.removed++;
                const id = selector.match(/^#([\w-]+)$/);
                if (id) appended.forEach(entry => { entry.gone ||= entry.element.id === id[1]; });
                return api;
            },
            find(inner) { return $(`${selector} ${inner}`); },
            // (Where an element was moved to: the selector of its new parent.)
            appendTo(parent) { n.movedTo = String(parent); return api; },
            // A form as jQuery sends it: what the test put in its value ("a=1&b=2").
            serialize() { return String(n.value ?? ''); },
            // Each element the selector stands for (its own record, and the members a test set up), as "this" of the callback.
            each(callback) {
                all().forEach((record, index) => {
                    const [previous, saved] = [element, nodes.get('(object)')];
                    element = {};
                    nodes.set('(object)', record);
                    try {
                        callback.call(element, index, element);
                    } finally {
                        element = previous;
                        nodes.set('(object)', saved ?? newRecord());
                    }
                });
                return api;
            },
            modal(action) { n.modal = action; return api; },
            ready(callback) { ready.push(callback); return api; },
            tooltip() { return api; },
            css() { return api; },
            // Widgets and animations a page may use: what they would show is not what these checkers are about.
            select2() { return api; },
            // (What is triggered is written down: a script that moves the focus does it so.)
            trigger(event) { all().forEach(record => record.triggered.push(event)); return api; },
            slideToggle() { return api; },
            slideUp() { return api; },
            slideDown() { return api; },
        };
        return api;
    }
    // As jQuery writes a query: encodeURIComponent for every key and value (it throws on half of a character that is outside the basic
    // plane), a blank as "+". (URLSearchParams would turn that half into U+FFFD and say nothing.)
    $.param = values => Object.entries(values).map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value ?? '')}`).join('&').replace(/%20/g, '+');
    // What a script sees as "document" ($(document) is the record "(document)").
    const documentStub = {
        visibilityState: 'visible',
        // (What the page listens for on the document is kept, not called: no event comes by itself.)
        listeners: {},
        addEventListener(event, callback) { this.listeners[event] = callback; },
        createElement: tag => ({ tag, listeners: {}, contentWindow: { location: { pathname: framePath } }, addEventListener(event, callback) { this.listeners[event] = callback; } }),
        getElementById: id => { asked.add(id); return { appendChild: element => appended.push({ into: id, element, gone: false }) }; },
    };
    $.getJSON = (url, data) => {
        $.param(data ?? {}); // jQuery writes the query before it sends anything: a value it cannot write is a failure of the script, not of the request
        const request = { url, data, done: null, fail: null, always: null, chained: [] };
        const chain = {
            done(callback) { request.done = callback; return chain; },
            fail(callback) { request.fail = callback; return chain; },
            always(callback) { request.always = callback; return chain; },
            // A request that is called off fails as jQuery's does: with the status text "abort".
            abort() { request.aborted = true; request.fail?.({ status: 0, statusText: 'abort' }, 'abort'); request.always?.(); return chain; },
        };
        requests.push(request);
        return chain;
    };

    // A write request ($.ajax): waits for the test to answer it, like a read does. "then" is how a library such as SweetAlert chains
    // on it (jQuery 3: a refusal handled by the second function resolves the promise).
    $.ajax = options => {
        $.param(options.data ?? {});
        const request = { url: options.url, type: options.type ?? 'GET', data: options.data, dataType: options.dataType, done: () => {}, fail: () => {}, always: () => {}, chained: [] };
        const chain = {
            done(callback) { request.done = callback; return chain; },
            fail(callback) { request.fail = callback; return chain; },
            always(callback) { request.always = callback; return chain; },
            then(success, failure) { request.chained.push({ success, failure }); return chain; },
        };
        requests.push(request);
        return chain;
    };

    // SweetAlert: every dialog the page opens is written down, with the options it was opened with. The test says what the person
    // answered (resolve), and what the dialog's own functions (preConfirm) do is called by the test too.
    const dialogs = [];
    const Swal = {
        isLoading: () => false,
        fire: (...args) => {
            const dialog = { args, options: typeof args[0] === 'object' ? args[0] : { title: args[0], html: args[1], icon: args[2] }, callbacks: [] };
            dialogs.push(dialog);
            const promise = { then(callback) { dialog.callbacks.push(callback); return promise; } };

            return promise;
        },
    };

    // localStorage: what the browser keeps for the person between pages (the width of the sidebar). stored() shows it.
    const kept = new Map(Object.entries(storage));
    const localStorage = {
        getItem: key => (kept.has(key) ? kept.get(key) : null),
        setItem: (key, value) => { kept.set(key, String(value)); },
        removeItem: key => { kept.delete(key); },
    };

    // history.replaceState: the addresses a page gave itself without loading (addresses() shows them).
    const addresses = [];
    const history = { replaceState: (state, title, url) => { addresses.push(String(url)); } };

    const sandbox = vm.createContext({
        $,
        Swal,
        localStorage,
        history,
        console,
        URL,
        URLSearchParams,
        location: { href, ...Object.fromEntries(['search', 'pathname', 'hash', 'origin'].map(key => [key, new URL(href)[key]])) },
        // Timers do not run by themselves: runTimers() lets the waiting ones fire, like time passing.
        setTimeout: callback => { timers.set(++lastTimer, callback); return lastTimer; },
        clearTimeout: id => { timers.delete(id); },
        // A repeated timer is kept and never fires: what it repeats is checked by calling it.
        setInterval: () => ++lastTimer,
        clearInterval: () => {},
        document: documentStub,
    });
    vm.runInContext('globalThis.window = globalThis;', sandbox); // scripts say window.location, window.print ...
    scripts.forEach((script, index) => vm.runInContext(script, sandbox, { filename: `page-script-${index}.js` }));
    ready.splice(0).forEach(callback => callback());

    return {
        html,
        $,
        node,
        exists,
        requests,
        dialogs,
        /** What the page has kept in localStorage. */
        stored: () => Object.fromEntries(kept),
        /** The addresses the page gave itself with history.replaceState, the latest last. */
        addresses: () => [...addresses],
        /** The person's answer to the dialog that was opened last (what Swal.fire(...).then() is called with). */
        resolve: (result, dialog = dialogs.at(-1)) => dialog.callbacks.forEach(callback => callback(result)),
        appended,
        /** A variable of the page's script. */
        page: name => vm.runInContext(name, sandbox),
        runTimers() {
            const waiting = [...timers.values()];
            timers.clear();
            waiting.forEach(callback => callback());
        },
        /**
         * Calls the handlers of an event. "this" is the element the test has filled in at node('(object)'), a new
         * one for each firing, and the record of it is what comes back.
         *
         * @param {{member?: string, siblings?: {attrs?: object, classes?: string[]}[]}} [others] member: a selector
         *     that matches the element (and the siblings: other elements it matches, which the test sets up)
         * @returns {{element: object, siblings: object[]}} the records, as they are after the handlers have run
         */
        fire(selector, event, argument, { member = null, siblings = [] } = {}) {
            const handlers = node(selector).handlers[event];
            assert.ok(handlers && handlers.length, `no "${event}" handler on ${selector}`);
            const own = node('(object)');
            const others = siblings.map(({ attrs = {}, classes = [] }) => Object.assign(newRecord(), { attrs: { ...attrs }, classes: new Set(classes) }));
            if (member) members.set(member, [own, ...others]);
            element = {};
            try {
                handlers.forEach(handler => handler.call(element, argument));
            } finally {
                element = null;
                members.delete(member);
                nodes.set('(object)', newRecord());
            }

            return { element: own, siblings: others };
        },
        /** What the latest request asked for. */
        sent: () => ({ ...requests.at(-1).data }),
        // (A script need not listen for every outcome: one that set no "always", or no "fail", is not called for it.)
        answer(request, body) { request.done?.(body); request.chained.forEach(({ success }) => success?.(body)); request.always?.(); },
        refuse(request, xhr) { request.fail?.(xhr); request.chained.forEach(({ failure }) => failure?.(xhr)); request.always?.(); },
        /** The elements the script put into the element with this id and has not taken out again. */
        inside: id => appended.filter(entry => entry.into === id && !entry.gone).map(entry => entry.element),
        /** Everything the script has written into the page as markup. */
        written: () => [...nodes.values()].map(record => record.html).join('\n'),
        /** Every id the script reached for: "#id" in a selector, or document.getElementById. */
        ids() {
            const ids = new Set(asked);
            for (const selector of nodes.keys()) {
                for (const match of selector.matchAll(/#([A-Za-z][\w-]*)/g)) ids.add(match[1]);
            }
            return ids;
        },
    };
}

/** check(name, test) runs one test and stops everything at the first that fails; done() says how many passed. */
export function checker() {
    let checks = 0;
    return {
        check(name, test) {
            try {
                test();
                checks++;
            } catch (error) {
                console.error(`FAILED: ${name}\n${error.message}`);
                process.exit(1);
            }
        },
        done() { console.log(`${checks} behaviour checks passed`); },
    };
}
