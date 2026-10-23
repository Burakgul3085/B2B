/**
 * Panel ve vitrin teması: "light" (açık), "dark" (koyu) veya "auto" (sistem). Tercih "tema" çerezinde 1 yıl saklanır;
 * sunucu sayfayı doğru sınıfla gönderir (açık/koyu arası yanıp sönme olmaz). "auto"da tarayıcının ayarı izlenir.
 */
const COOKIE = 'tema';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function read() {
    const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=(light|dark|auto)'));
    return match ? match[1] : 'light';
}

export function applyTheme(mode) {
    document.documentElement.classList.toggle('dark', mode === 'dark' || (mode === 'auto' && media.matches));
}

export function initTheme() {
    if (read() === 'auto') {
        applyTheme('auto');
    }
    media.addEventListener('change', () => read() === 'auto' && applyTheme('auto'));
}

export function themeSwitch() {
    return {
        mode: read(),
        set(mode) {
            this.mode = mode;
            const secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = `${COOKIE}=${mode}; Max-Age=${60 * 60 * 24 * 365}; Path=/; SameSite=Lax${secure}`;
            applyTheme(mode);
        },
        /** Şu an koyu görünüyor mu ("auto"da tarayıcı ayarına göre). */
        get isDark() {
            return this.mode === 'dark' || (this.mode === 'auto' && media.matches);
        },
        /** Vitrin üst bilgisindeki ay/güneş düğmesi: açık ↔ koyu. */
        toggle() {
            this.set(this.isDark ? 'light' : 'dark');
        },
    };
}
