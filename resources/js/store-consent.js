/**
 * Mağaza çerez tercihi (KVKK / Gizlilik ve Çerez Politikası).
 * Tercih "magaza_cerez" çerezinde 1 yıl saklanır: "v2.f1.a0.m0" biçiminde (işlevsel, analitik, pazarlama izinleri).
 * Eski değerler: "essential" = hepsi kapalı, "all" = hepsi açık. Zorunlu çerezler her zaman açıktır.
 * İleride analitik/pazarlama betiği eklenirse yalnızca window.storeConsentAllows('analytics' | 'marketing') true iken yüklenmelidir.
 */
const COOKIE = 'magaza_cerez';
const CATEGORIES = ['functional', 'analytics', 'marketing'];

function raw() {
    const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]+)'));
    return match ? decodeURIComponent(match[1]) : null;
}

/** Kaydedilmiş tercih: { functional, analytics, marketing } ya da hiç seçilmediyse null. */
export function readPreferences() {
    const value = raw();
    if (value === 'all') {
        return { functional: true, analytics: true, marketing: true };
    }
    if (value === 'essential') {
        return { functional: false, analytics: false, marketing: false };
    }
    const m = value && value.match(/^v2\.f([01])\.a([01])\.m([01])$/);
    return m ? { functional: m[1] === '1', analytics: m[2] === '1', marketing: m[3] === '1' } : null;
}

/** Kategoriye izin var mı? Zorunlu her zaman; seçim yapılmadıysa yalnızca zorunlu. */
export function consentAllows(category) {
    if (category === 'essential') {
        return true;
    }
    const prefs = readPreferences();
    return prefs ? prefs[category] === true : false;
}

function save(prefs) {
    const value = `v2.f${prefs.functional ? 1 : 0}.a${prefs.analytics ? 1 : 0}.m${prefs.marketing ? 1 : 0}`;
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${COOKIE}=${value}; Max-Age=${60 * 60 * 24 * 365}; Path=/; SameSite=Lax${secure}`;
    // İşlevsel izin kaldırıldıysa tema tercihi oturumluk çereze çevrilir.
    if (!prefs.functional) {
        const theme = document.cookie.match(/(?:^|; )tema=(light|dark|auto)/);
        if (theme) {
            document.cookie = `tema=${theme[1]}; Path=/; SameSite=Lax${secure}`;
        }
    }
    window.dispatchEvent(new CustomEvent('store-consent', { detail: prefs }));
}

export function cookieConsent() {
    const prefs = readPreferences();
    return {
        banner: prefs === null,
        modal: false,
        choices: prefs || { functional: true, analytics: false, marketing: false },
        init() {
            // "Çerez tercihleri" bağlantıları ayrıntılı pencereyi açar.
            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-cookie-settings]')) {
                    event.preventDefault();
                    this.openModal();
                }
            });
        },
        openModal() {
            this.choices = readPreferences() || this.choices;
            this.modal = true;
            document.documentElement.classList.add('overflow-hidden');
        },
        closeModal() {
            this.modal = false;
            document.documentElement.classList.remove('overflow-hidden');
        },
        toggle(category) {
            this.choices[category] = !this.choices[category];
        },
        isOn(category) {
            return this.choices[category] === true;
        },
        acceptAll() {
            this.finish({ functional: true, analytics: true, marketing: true });
        },
        rejectAll() {
            this.finish({ functional: false, analytics: false, marketing: false });
        },
        saveChoices() {
            this.finish({ ...this.choices });
        },
        finish(choices) {
            this.choices = choices;
            save(choices);
            this.banner = false;
            this.closeModal();
        },
    };
}

window.storeConsentAllows = consentAllows;
window.storeConsent = () => {
    const prefs = readPreferences();
    if (!prefs) {
        return null;
    }
    return CATEGORIES.every((c) => prefs[c]) ? 'all' : 'essential';
};
