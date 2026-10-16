import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Grafikler yalnızca grafik içeren sayfalarda yüklenir (Chart.js ayrı parça olarak).
if (document.querySelector('canvas[data-chart]')) {
    import('./charts.js').then(({ renderCharts }) => renderCharts());
}
