/**
 * Google Analytics 4 (mağaza vitrini). Ölçüm kimliği <meta name="ga-measurement-id"> ile gelir; betik yalnızca ziyaretçi
 * analitik çerezlere izin verdiyse yüklenir. İzin kaldırılırsa ölçüm durur ve _ga çerezleri silinir.
 */
import { consentAllows } from './store-consent.js';

let loaded = false;

// Gizli bağlantı anahtarları (misafir sipariş sayfası, parola sıfırlama, e-bülten onayı / ayrılma) Google'a gönderilmez:
// adres yoldaki anahtar atılarak ölçülür. Aksi hâlde Analytics'i gören biri müşterinin siparişini açabilirdi.
const SECRET_PATHS = [/^(\/siparis\/[^/]+)\/.+$/, /^(\/parola-sifirla)\/.+$/, /^(\/bulten\/(?:onay|ayril))\/.+$/];

export function safeUrl(href) {
    let url;
    try {
        url = new URL(href, location.href);
    } catch {
        return '';
    }
    const rule = SECRET_PATHS.find((re) => re.test(url.pathname));
    if (rule) {
        url.pathname = url.pathname.replace(rule, '$1');
        url.search = '';
    }
    ['token', 'email', 'code'].forEach((key) => url.searchParams.delete(key));
    url.hash = '';

    return url.href;
}

function config(id) {
    const options = { page_location: safeUrl(location.href) };
    if (document.referrer) {
        options.page_referrer = safeUrl(document.referrer);
    }
    window.gtag('config', id, options);
}

function start(id) {
    window[`ga-disable-${id}`] = false;
    if (loaded) {
        config(id);
        return;
    }
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    // gtag, dizi değil arguments nesnesi bekler.
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    config(id);

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
    document.head.appendChild(script);
}

function stop(id) {
    window[`ga-disable-${id}`] = true;
    const expired = 'Max-Age=0; Path=/';
    document.cookie.split('; ').map((c) => c.split('=')[0]).filter((name) => name === '_ga' || name.startsWith('_ga_')).forEach((name) => {
        document.cookie = `${name}=; ${expired}`;
        document.cookie = `${name}=; ${expired}; Domain=.${location.hostname}`;
    });
}

export function initAnalytics() {
    const id = document.querySelector('meta[name="ga-measurement-id"]')?.content;
    if (!id) {
        return;
    }
    if (consentAllows('analytics')) {
        start(id);
    } else {
        stop(id);
    }
    window.addEventListener('store-consent', (event) => (event.detail.analytics ? start(id) : stop(id)));
}
