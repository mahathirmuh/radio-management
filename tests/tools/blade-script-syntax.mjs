// Parses every inline <script> block of every Blade view and reports JavaScript syntax errors.
// Blade syntax is taken out first: expressions ({{ }}, {!! !!}, @json(), @js()) become the
// placeholder 0, directives (@if (...), @endif, ...) are removed, and nothing else on their line
// is touched, so JavaScript that shares a line with a directive is still parsed.
//
// Limits, chosen so that a mistake shows up as a red test and never as a missed error:
// - the branches of an @if / @else end up side by side, so a `const` declared in each branch is
//   reported as declared twice: declare it once before the branch;
// - <script type="module"> blocks and non-JavaScript types (templates, JSON) are not checked.
// usage: node blade-script-syntax.mjs <project-root>
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root = process.argv[2];
const dirs = ['modules', 'resources/views'].map(dir => path.join(root, dir));
const files = [];
const walk = dir => {
    if (!fs.existsSync(dir)) return;
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full);
        else if (entry.name.endsWith('.blade.php')) files.push(full);
    }
};
dirs.forEach(walk);

// Directives that take an argument list, and the bare ones.
const WITH_ARGUMENTS = ['elseif', 'if', 'foreach', 'forelse', 'for', 'while', 'isset', 'empty', 'unless', 'switch',
    'case', 'auth', 'guest', 'can', 'cannot', 'canany', 'permission', 'error', 'env', 'include', 'includeIf', 'php'];
const BARE = ['else', 'endif', 'endforeach', 'endforelse', 'empty', 'endfor', 'endwhile', 'endisset', 'endempty', 'endunless',
    'endswitch', 'default', 'break', 'continue', 'endauth', 'auth', 'endguest', 'guest', 'endcan', 'endcannot',
    'endcanany', 'endpermission', 'enderror', 'endenv', 'production', 'endproduction', 'once', 'endonce'];

// Index just past the ")" that closes the "(" at `open`, skipping over quoted strings; -1 when
// the list never closes (then the rest of the script must not be swallowed silently).
function closingParenthesis(source, open) {
    let depth = 0;
    for (let i = open; i < source.length; i++) {
        const char = source[i];
        if (char === '"' || char === "'") {
            for (i++; i < source.length && source[i] !== char; i++) {
                if (source[i] === '\\') i++;
            }
        } else if (char === '(') {
            depth++;
        } else if (char === ')' && --depth === 0) {
            return i + 1;
        }
    }
    return -1;
}

// Replaces `@name (...)` including its balanced argument list. "@for" does not match "@foreach".
function replaceCalls(source, name, replacement) {
    const needle = '@' + name;
    let out = '';
    let from = 0;
    while (true) {
        const at = source.indexOf(needle, from);
        if (at === -1) return out + source.slice(from);
        let open = at + needle.length;
        while (source[open] === ' ' || source[open] === '\t') open++;
        if (source[open] !== '(') {
            out += source.slice(from, at + needle.length);
            from = at + needle.length;
            continue;
        }
        out += source.slice(from, at) + replacement;
        from = closingParenthesis(source, open);
        if (from === -1) {
            throw new SyntaxError(`the argument list of ${needle}( never closes (an unmatched quote or parenthesis in it?)`);
        }
    }
}

function withoutBlade(js) {
    js = js.replace(/\{\{--[\s\S]*?--\}\}/g, '');
    js = js.replace(/@php\b(?!\s*\()[\s\S]*?@endphp/g, ''); // a PHP block, not JavaScript
    js = replaceCalls(js, 'json', '0');
    js = replaceCalls(js, 'js', '0');
    js = js.replace(/\{!![\s\S]*?!!\}/g, '0').replace(/\{\{[\s\S]*?\}\}/g, '0');
    for (const name of WITH_ARGUMENTS) js = replaceCalls(js, name, '');
    return js.replace(new RegExp('@(' + BARE.join('|') + ')\\b', 'g'), '');
}

// The attribute itself, not data-type / data-src.
const attribute = (attributes, name) => new RegExp('(?:^|\\s)' + name + '\\s*=\\s*["\']?([^"\'\\s>]*)', 'i').exec(attributes);

// What a browser runs as a classic script: no type, an empty one, or a JavaScript MIME type
// (including the legacy ones). Modules, JSON and templates are left alone.
const isClassicScript = attributes => {
    const type = attribute(attributes, 'type');
    return !type || type[1] === ''
        || /^(text|application)\/(x-)?(java|ecma)script$/i.test(type[1])
        || /^text\/(jscript|livescript|javascript1\.[0-5])$/i.test(type[1]);
};

let blocks = 0;
const errors = [];
for (const file of files) {
    const text = fs.readFileSync(file, 'utf8');
    const pattern = /<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi;
    let match;
    while ((match = pattern.exec(text))) {
        const [, attributes, body] = match;
        if (attribute(attributes, 'src') || !isClassicScript(attributes) || !body.trim()) continue;
        blocks++;
        try {
            new vm.Script(withoutBlade(body), { filename: path.relative(root, file) });
        } catch (error) {
            const line = text.slice(0, match.index).split('\n').length;
            const hint = /has already been declared/.test(error.message)
                ? ' (if each branch of an @if declares it, declare it once before the branch)'
                : '';
            errors.push(`${path.relative(root, file)} (script starting at line ${line}): ${error.message}${hint}`);
        }
    }
}

console.log(`${files.length} views, ${blocks} inline script blocks parsed`);
console.log(errors.length ? errors.join('\n') : 'no syntax errors');
process.exit(errors.length ? 1 : 0);
