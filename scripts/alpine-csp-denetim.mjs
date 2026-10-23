/**
 * Alpine CSP denetimi: bütün Blade şablonlarındaki Alpine ifadelerini @alpinejs/csp'nin kendi ayrıştırıcısıyla sınar.
 *
 * Uygulama 'unsafe-eval' olmadan çalışır; Alpine ifadeleri bu yüzden eval ile değil, CSP sürümünün sınırlı
 * ayrıştırıcısıyla yorumlanır (tek deyim; ok fonksiyonu, ?., ??, typeof, new, şablon dizgesi, ... yok).
 * Ayrıştırılamayan ifade sayfada sessizce bozulur; bu betik onları derleme öncesinde yakalar. Ayrıca:
 *  - genel nesne kullanımı (JSON, Math, window, Number…) çalışma anında "Undefined variable" verir,
 *  - $el / $refs / $event üzerindeki DOM öğelerine atama CSP sürümünde yasaktır,
 *  - @js() / Js::from() dizi ve nesneler için JSON.parse(...) üretir; Alpine ifadelerinde yerine @alpine() kullanılır.
 *
 * Çalıştırma: npm run denetim:alpine   (hata varsa çıkış kodu 1)
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));

// --- Ayrıştırıcıyı paketin kendisinden al (sürüm değişirse denetim de onunla değişir) ---
const bundle = readFileSync(join(root, 'node_modules/@alpinejs/csp/dist/module.esm.js'), 'utf8');
const start = bundle.indexOf('// packages/csp/src/parser.js');
const end = bundle.indexOf('// packages/csp/src/evaluator.js');
if (start < 0 || end < 0) {
    console.error('@alpinejs/csp içinde ayrıştırıcı bölümü bulunamadı.');
    process.exit(2);
}
const { Tokenizer, Parser } = new Function(bundle.slice(start, end) + '\nreturn { Tokenizer, Parser };')();

function parse(expression) {
    return new Parser(new Tokenizer(expression).tokenize()).parse();
}

// Tarayıcıdaki genel adlar (Node'da olmayanlar elle eklendi). Kapsamda bulunmadıkları için CSP sürümünde çalışmazlar.
const GLOBALS = new Set([
    ...Object.getOwnPropertyNames(globalThis),
    'window', 'document', 'location', 'navigator', 'history', 'alert', 'confirm', 'prompt', 'localStorage',
    'sessionStorage', 'fetch', 'Intl', 'FormData', 'URL', 'URLSearchParams', 'Event', 'CustomEvent', 'self', 'top',
]);
const MAGICS = new Set(['$el', '$refs', '$event', '$root', '$dispatch', '$nextTick', '$watch', '$store', '$data', '$id']);

function* walk(node, parent = null, key = null) {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) {
        for (const child of node) yield* walk(child, parent, key);
        return;
    }
    if (typeof node.type !== 'string') return;
    yield { node, parent, key };
    for (const [k, v] of Object.entries(node)) {
        if (k !== 'type') yield* walk(v, node, k);
    }
}

function rootName(node) {
    while (node && node.type === 'MemberExpression') node = node.object;
    return node && node.type === 'Identifier' ? node.name : null;
}

function problems(ast) {
    const found = [];
    for (const { node, parent, key } of walk(ast)) {
        if (node.type === 'Identifier') {
            const isProperty = parent && parent.type === 'MemberExpression' && key === 'property' && !parent.computed;
            const isKey = parent && parent.type === 'Property' && key === 'key' && !parent.computed;
            if (!isProperty && !isKey && GLOBALS.has(node.name) && !MAGICS.has(node.name)) {
                found.push(`genel nesne: ${node.name}`);
            }
        }
        if ((node.type === 'AssignmentExpression' || node.type === 'UpdateExpression')) {
            const target = node.type === 'AssignmentExpression' ? node.left : node.argument;
            if (target.type === 'MemberExpression' && ['$el', '$refs', '$event', '$root'].includes(rootName(target))) {
                found.push('DOM öğesine atama');
            }
        }
    }
    return found;
}

// --- Blade şablonlarını tara ---
function files(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        return statSync(path).isDirectory() ? files(path) : path.endsWith('.blade.php') ? [path] : [];
    });
}

// Blade çıktısı (sunucu değeri) ifadede bir sabit gibi durur; ayrıştırma için 0 ile değiştirilir.
// @js/@json/Js::from/json_encode bir işaretle değiştirilir; Alpine ifadesinde görülürse bildirilir.
// Satır sayısı korunsun diye yer tutucular aynı satır sonlarını taşır.
const JS_FROM = '__js_from__';
const ALPINE = '__alpine__';

function maskBlade(source) {
    const keepLines = (text, filler) => filler + text.replace(/[^\n]/g, '');
    let out = source.replace(/\{\{--[\s\S]*?--\}\}/g, (m) => keepLines(m, ''));
    out = out.replace(/<\{\{[\s\S]*?\}\}/g, (m) => keepLines(m, '<div')); // <{{ $tag }} …>
    out = out.replace(/<\/\{\{[\s\S]*?\}\}/g, (m) => keepLines(m, '</div'));
    out = out.replace(/\{!!([\s\S]*?)!!\}|\{\{([\s\S]*?)\}\}/g, (m, raw, esc) => {
        return keepLines(m, /Js::from|json_encode/.test(raw ?? esc) ? JS_FROM : '0');
    });
    // Parantezli Blade yönergeleri (@selected(...), @if(...) …) atılır; @js/@json/@alpine bir değer basar.
    let result = '';
    let i = 0;
    const directive = /(?<![\w@])@(\w+)\s*\(/g;
    let match;
    while ((match = directive.exec(out))) {
        let depth = 0;
        let j = match.index + match[0].length - 1;
        for (; j < out.length; j++) {
            if (out[j] === '(') depth++;
            else if (out[j] === ')' && --depth === 0) break;
        }
        const value = ['js', 'json', 'alpine'].includes(match[1]);
        const filler = match[1] === 'alpine' ? ALPINE : value ? JS_FROM : '';
        result += out.slice(i, match.index) + keepLines(out.slice(match.index, j + 1), filler);
        i = j + 1;
        directive.lastIndex = i;
    }
    return result + out.slice(i);
}

function decodeEntities(value) {
    return value
        .replace(/&quot;/g, '"').replace(/&#0?39;|&apos;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

// x-transition (sınıf adları), x-ref, x-cloak, x-ignore, x-teleport ifade değildir.
const NOT_EXPRESSIONS = /^x-(transition|ref|cloak|ignore|teleport)\b/;

function expressionsOf(tag, name, value) {
    const component = /^x-[a-z]/.test(tag);
    let attr = name;
    if (component && attr.startsWith('::')) attr = attr.slice(1);
    else if (component && attr.startsWith(':')) return []; // Blade bileşen özelliği (PHP)

    const alpine = attr.startsWith('x-') || attr.startsWith('@') || attr.startsWith(':');
    if (!alpine || NOT_EXPRESSIONS.test(attr)) return [];
    if (value === null || value.trim() === '') return [];

    if (attr === 'x-for') {
        const m = value.match(/^\s*(?:\(([^)]*)\)|(\S+))\s+(?:in|of)\s+([\s\S]+)$/);
        return m ? [m[3]] : [null];
    }
    if (attr === 'x-model' || attr.startsWith('x-model.') || attr === 'x-modelable') {
        return [value, `${value} = __yer_tutucu`];
    }
    return [value];
}

const errors = [];
let checked = 0;

for (const path of files(join(root, 'resources/views'))) {
    const file = relative(root, path).replace(/\\/g, '/');
    const source = readFileSync(path, 'utf8');
    // Yer tutucular satır sonlarını korur; satır numarası maskelenmiş metinden de doğru çıkar.
    const lineOf = (offset, text) => text.slice(0, offset).split('\n').length;
    const masked = maskBlade(source);

    const tagPattern = /<([a-zA-Z][\w:.-]*)((?:\s+(?:[^\s"'>\/=]+)(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'>]+))?)*)\s*\/?>/g;
    const covered = [];
    let tag;
    while ((tag = tagPattern.exec(masked))) {
        covered.push([tag.index, tag.index + tag[0].length]);
        const attrPattern = /([^\s"'>\/=]+)(?:\s*=\s*("[^"]*"|'[^']*'|[^\s"'>]+))?/g;
        let attr;
        while ((attr = attrPattern.exec(tag[2]))) {
            const [, name, rawValue] = attr;
            const value = rawValue === undefined ? null : decodeEntities(rawValue.replace(/^["']|["']$/g, ''));
            const offset = tag.index + tag[1].length + 1 + attr.index;
            if (name === 'x-html') {
                errors.push(`${file}:${lineOf(offset, masked)}  x-html\n    CSP sürümünde x-html yasak; içeriği bileşen kodunda x-ref ile yerleştirin`);
                continue;
            }
            for (const expression of expressionsOf(tag[1], name, value)) {
                checked++;
                const where = `${file}:${lineOf(offset, masked)}  ${name}="${(value ?? '').replace(/\s+/g, ' ').slice(0, 90)}"`;
                if (expression === null) {
                    errors.push(`${where}\n    x-for biçimi tanınmadı`);
                    continue;
                }
                if (/^x-[a-z]/.test(tag[1]) && expression.includes(ALPINE)) {
                    errors.push(`${where}
    Blade bileşen etiketinin özniteliğinde @alpine() derlenmez; x-data'yı içteki bir HTML öğesine taşıyın`);
                    continue;
                }
                if (expression.includes(JS_FROM)) {
                    errors.push(`${where}
    @js()/@json()/Js::from/json_encode yerine @alpine() kullanın`);
                    continue;
                }
                try {
                    const issues = problems(parse(expression));
                    if (issues.length) errors.push(`${where}\n    ${[...new Set(issues)].join(', ')}`);
                } catch (error) {
                    errors.push(`${where}\n    ${error.message}`);
                }
            }
        }
    }

    // Etiket deseni bir etiketi kaçırırsa içindeki ifadeler sessizce denetimsiz kalırdı; onları ayrıca bildir.
    for (const m of masked.matchAll(/\s(?:x-[\w:.-]+|@[\w:.-]+|::?[\w.-]+)\s*=\s*["']/g)) {
        if (!covered.some(([from, to]) => m.index >= from && m.index < to)) {
            errors.push(`${file}:${lineOf(m.index, masked)}  etiket ayrıştırılamadı (${m[0].trim()}…)`);
        }
    }
}

if (errors.length) {
    console.error(errors.join('\n'));
    console.error(`\n${errors.length} sorun (${checked} ifade denetlendi).`);
    process.exit(1);
}
console.log(`Alpine CSP denetimi: ${checked} ifade sorunsuz.`);
