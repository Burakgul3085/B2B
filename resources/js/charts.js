/*
 * Dashboard grafikleri (Chart.js). Grafikler sayfadaki <canvas data-chart='{...}'> öğelerinden kurulur;
 * veriler sunucuda gerçek kayıtlardan üretilir.
 *
 * Görsel kurallar: ince çizgiler (2px), yuvarlatılmış çubuk uçları, tek eksen, sakin ızgara,
 * 2+ seride lejant, çizgide çapraz imleç + ipucu, çubukta tek öğe ipucu. Renkler doğrulanmış
 * kategorik paletten sabit sırayla atanır (dataviz validate_palette: PASS; düşük kontrastlı iki renk
 * için her grafiğin altında tablo görünümü vardır).
 */
import {
    ArcElement, BarController, BarElement, CategoryScale, Chart, DoughnutController, Filler, Legend,
    LinearScale, LineController, LineElement, PointElement, Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend,
    LinearScale, LineController, LineElement, PointElement, Tooltip);

const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];
const COMPARE = '#94a3b8';
const INK = '#0f172a';
const MUTED = '#64748b';
const GRID = '#eef2f7';

Chart.defaults.font.family = "'Inter Variable', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = MUTED;

const money = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 });
const moneyFull = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
const fmt = (v, unit) => (unit === 'money' ? moneyFull.format(v) : new Intl.NumberFormat('tr-TR').format(v));
const axisFmt = (v, unit) => (unit === 'money' ? money.format(v) : new Intl.NumberFormat('tr-TR').format(v));

const tooltip = (unit) => ({
    backgroundColor: '#ffffff',
    titleColor: INK,
    bodyColor: INK,
    borderColor: '#e2e8f0',
    borderWidth: 1,
    padding: 10,
    boxPadding: 4,
    callbacks: { label: (ctx) => ` ${ctx.dataset.label ? ctx.dataset.label + ': ' : ''}${fmt(ctx.parsed.y ?? ctx.parsed.x ?? ctx.parsed, unit)}` },
});

const builders = {
    // Dönem ve önceki dönem satış eğrisi
    line(cfg) {
        return {
            type: 'line',
            data: {
                labels: cfg.labels,
                datasets: [
                    { label: cfg.names[0], data: cfg.series[0], borderColor: SERIES[0], backgroundColor: 'rgba(42,120,214,0.08)', fill: true,
                        borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2, tension: 0.25 },
                    { label: cfg.names[1], data: cfg.series[1], borderColor: COMPARE, borderDash: [5, 4], borderWidth: 2,
                        pointRadius: 0, pointHoverRadius: 4, tension: 0.25 },
                ],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 2, color: INK } }, tooltip: tooltip(cfg.unit) },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => axisFmt(v, cfg.unit) } },
                },
            },
        };
    },
    // Tek seri yatay çubuk (kategori / bayi)
    hbar(cfg) {
        return {
            type: 'bar',
            data: { labels: cfg.labels, datasets: [{ data: cfg.values, backgroundColor: SERIES[0], borderRadius: 4, borderSkipped: 'start', barPercentage: 0.7, categoryPercentage: 0.9, maxBarThickness: 28 }] },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { ...tooltip(cfg.unit), mode: 'nearest', intersect: true } },
                scales: {
                    x: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { maxTicksLimit: 4, callback: (v) => axisFmt(v, cfg.unit) } },
                    y: { grid: { display: false }, ticks: { color: INK, callback(v) { const l = this.getLabelForValue(v); return l.length > 22 ? l.slice(0, 21) + '…' : l; } } },
                },
            },
        };
    },
    // Dikey çubuk (bayi: aylık sipariş)
    bar(cfg) {
        return {
            type: 'bar',
            data: { labels: cfg.labels, datasets: [{ label: cfg.name, data: cfg.values, backgroundColor: SERIES[0], borderRadius: 4, borderSkipped: 'start', barPercentage: 0.7, maxBarThickness: 36 }] },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltip(cfg.unit) },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, grid: { color: GRID }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: (v) => axisFmt(v, cfg.unit) } },
                },
            },
        };
    },
    // Halka (sipariş durum grupları) — 4 sabit renk, lejantta değerleriyle
    doughnut(cfg) {
        return {
            type: 'doughnut',
            data: { labels: cfg.labels.map((l, i) => `${l} (${cfg.values[i]})`), datasets: [{ data: cfg.values, backgroundColor: SERIES, borderColor: '#ffffff', borderWidth: 2, hoverOffset: 4 }] },
            options: {
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, color: INK, padding: 12 } },
                    tooltip: { ...tooltip('int'), callbacks: { label: (ctx) => ` ${ctx.label}` } },
                },
            },
        };
    },
};

export function renderCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.rendered) return;
        const cfg = JSON.parse(canvas.dataset.chart);
        new Chart(canvas, builders[cfg.type](cfg));
        canvas.dataset.rendered = '1';
    });
}
