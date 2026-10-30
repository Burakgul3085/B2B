/**
 * iyzico Korumalı Alışveriş rozeti (mağaza vitrini). iyzico panelinin verdiği kod satır içi <script> içerdiği için
 * (içerik güvenlik politikası engeller) aynı ayar burada kurulur: token <meta name="iyzico-buyer-protection"> ile
 * gelir (mağaza yönetimi → Ödeme ve Teslimat), betik static.iyzipay.com'dan yüklenir.
 */
export function initBuyerProtection() {
    const meta = document.querySelector('meta[name="iyzico-buyer-protection"]');
    if (!meta || !meta.content) {
        return;
    }
    window.iyz = { token: meta.content, position: meta.dataset.position || 'bottomLeft', ideaSoft: false, pwi: true };
    const script = document.createElement('script');
    script.src = 'https://static.iyzipay.com/buyer-protection/buyer-protection.js';
    script.async = true;
    document.body.appendChild(script);
}
