import Alpine from '@alpinejs/csp';
import * as components from './components.js';
import { enableUploadProgress, heroSlider } from './storefront.js';
import { checkout } from './store-checkout.js';
import { folderImport } from './folder-import.js';
import { cookieConsent } from './store-consent.js';
import { initAnalytics } from './store-analytics.js';
import { initBuyerProtection } from './store-buyer-protection.js';
import { initTheme, themeSwitch } from './theme.js';

// Alpine'in CSP sürümü: ifadeler eval olmadan yorumlanır (politikada 'unsafe-eval' yok). Mantık içeren
// davranışlar bileşen olarak tanımlanır; şablon ifadeleri npm run denetim:alpine ile sınanır.
window.Alpine = Alpine;
Object.entries(components).forEach(([name, component]) => Alpine.data(name, component));
Alpine.data('heroSlider', heroSlider);
Alpine.data('checkout', checkout);
Alpine.data('folderImport', folderImport);
Alpine.data('cookieConsent', cookieConsent);
Alpine.data('themeSwitch', themeSwitch);
initTheme();
Alpine.start();
enableUploadProgress();
initAnalytics();
initBuyerProtection();

// İçerik güvenlik politikası satır içi olay işleyicilerini (onsubmit="…") engeller; onay ve otomatik gönderim
// bu yüzden öznitelikle tanımlanır: data-confirm="Soru?" (form veya buton), data-autosubmit (seçim değişince gönder).
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = event.submitter?.dataset.confirm ?? form.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]') && event.target.form) {
        event.target.form.requestSubmit();
    }
});

// Grafikler yalnızca grafik içeren sayfalarda yüklenir (Chart.js ayrı parça olarak).
if (document.querySelector('canvas[data-chart]')) {
    import('./charts.js').then(({ renderCharts }) => renderCharts());
}

// Yazdır düğmesi: data-print (ekstre). Sayfa düzeni print: sınıflarıyla yazdırmaya uyarlanır.
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) {
        window.print();
    }
});

// Ürün kartındaki ikinci görsel (üzerine gelince görünen) ancak fare karta gelince indirilir; listede her kart için
// fazladan bir görsel indirilmez. Dokunmatik ekranda hover olmadığı için hiç indirilmez.
document.addEventListener('pointerover', (event) => {
    const card = event.target instanceof Element ? event.target.closest('article') : null;
    card?.querySelectorAll('img[data-hover-src]').forEach((img) => {
        img.src = img.dataset.hoverSrc;
        img.removeAttribute('data-hover-src');
    });
}, { passive: true });

// Koyu temada yazdırırken açık temaya geçilir (beyaz kâğıtta açık renkli yazı kalmasın); baskıdan sonra geri dönülür.
let printedDark = false;
window.addEventListener('beforeprint', () => {
    printedDark = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
});
window.addEventListener('afterprint', () => {
    if (printedDark) {
        document.documentElement.classList.add('dark');
    }
});
