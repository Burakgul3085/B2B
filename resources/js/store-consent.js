/**
 * Mağaza çerez tercihi (KVKK / Gizlilik ve Çerez Politikası).
 * Tercih "magaza_cerez" çerezinde 1 yıl saklanır: "essential" (yalnızca zorunlu) veya "all" (tümü).
 * Şu anda yalnızca zorunlu çerezler kullanılıyor; ileride analiz/reklam betiği eklenirse
 * yalnızca window.storeConsent() === 'all' iken yüklenmelidir.
 */
const COOKIE = 'magaza_cerez';

export function readConsent() {
    const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=(essential|all)'));
    return match ? match[1] : null;
}

export function cookieConsent() {
    return {
        open: false,
        init() {
            this.open = readConsent() === null;
            // "Çerez tercihleri" bağlantıları bandı yeniden açar.
            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-cookie-settings]')) {
                    event.preventDefault();
                    this.open = true;
                }
            });
        },
        choose(value) {
            const secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `${COOKIE}=${value}; Max-Age=${60 * 60 * 24 * 365}; Path=/; SameSite=Lax${secure}`;
            this.open = false;
        },
    };
}

window.storeConsent = readConsent;
