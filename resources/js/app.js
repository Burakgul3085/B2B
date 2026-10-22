import Alpine from '@alpinejs/csp';
import * as components from './components.js';
import { enableUploadProgress, heroSlider } from './storefront.js';
import { checkout } from './store-checkout.js';
import { cookieConsent } from './store-consent.js';
import { initTheme, themeSwitch } from './theme.js';

// Alpine'in CSP sürümü: ifadeler eval olmadan yorumlanır (politikada 'unsafe-eval' yok). Mantık içeren
// davranışlar bileşen olarak tanımlanır; şablon ifadeleri npm run denetim:alpine ile sınanır.
window.Alpine = Alpine;
Object.entries(components).forEach(([name, component]) => Alpine.data(name, component));
Alpine.data('heroSlider', heroSlider);
Alpine.data('checkout', checkout);
Alpine.data('cookieConsent', cookieConsent);
Alpine.data('themeSwitch', themeSwitch);
initTheme();
Alpine.start();
enableUploadProgress();

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
