import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

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
