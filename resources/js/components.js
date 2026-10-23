/**
 * Şablonlarda kullanılan küçük Alpine bileşenleri.
 * Alpine'in CSP sürümü yalnızca basit ifadeleri yorumlar (tek deyim; ok fonksiyonu, ?., ??, şablon dizgesi,
 * genel nesne yok). Birden fazla adım veya tarayıcı API'si gereken davranışlar bu yüzden burada tanımlanır;
 * şablon yalnızca x-data="ad(...)" ve metot çağrısı içerir. Denetim: npm run denetim:alpine
 */

/** Çoklu dosya seçimi: seçilen dosya adları ve toplam boyut sınırı (duyuru ekleri). */
export function fileList(maxTotalBytes) {
    return {
        names: [],
        tooBig: false,
        pick(event) {
            const files = Array.from(event.target.files);
            this.names = files.map((f) => f.name);
            this.tooBig = files.reduce((total, f) => total + f.size, 0) > maxTotalBytes;
        },
    };
}

/** x-ref="url" alanındaki adresi panoya kopyalar. */
export function copyLink() {
    return {
        copied: false,
        copy() {
            navigator.clipboard.writeText(this.$refs.url.value);
            this.copied = true;
        },
    };
}

/** Vitrin slayt formu: canlı önizleme; yeni dosya seçilince önizleme o dosyaya geçer. */
export function bannerForm(state) {
    return {
        ...state,
        pickMedia(event) {
            const file = event.target.files[0];
            if (file) {
                this.fileName = file.name;
                this.isVideo = file.type.startsWith('video/');
                this.preview = URL.createObjectURL(file);
            }
        },
    };
}

/** Ürün seçici (toptancı paneli): ad, kod veya barkodla arar; seçimde "product-picked" olayı yayar. */
export function productPicker(lookupUrl, selected) {
    return {
        q: '',
        results: [],
        open: false,
        loading: false,
        selected,
        async search() {
            if (this.q.trim().length < 2) {
                this.results = [];
                return;
            }
            this.loading = true;
            const res = await fetch(lookupUrl + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
            this.results = res.ok ? await res.json() : [];
            this.loading = false;
            this.open = true;
        },
        pick(p) {
            this.selected = p;
            this.open = false;
            this.q = '';
            this.$dispatch('product-picked', p);
        },
        change() {
            // Referans önce alınır: "Değiştir" düğmesi x-if içinde, seçim kalkınca DOM'dan çıkar.
            const input = this.$refs.search;
            this.selected = null;
            this.$nextTick(() => input.focus());
        },
    };
}

/** Liste içinde arama (marka listeleri). Aranan değerler sunucuda küçük harfe çevrilmiş gelir. */
export function textFilter() {
    return {
        term: '',
        match(name) {
            const t = this.term.trim().toLocaleLowerCase('tr');
            return !t || name.includes(t);
        },
        matchAny(names) {
            return names.some((name) => this.match(name));
        },
    };
}

/** Ürün görseli: fareyle üzerine gelince imlecin olduğu nokta büyütülür (dokunmatikte kapalı). */
export function imageZoom() {
    return {
        zoom: false,
        x: 50,
        y: 50,
        enter(event) {
            if (event.pointerType === 'mouse') this.zoom = true;
        },
        move(event) {
            if (event.pointerType !== 'mouse') return;
            const r = event.currentTarget.getBoundingClientRect();
            this.x = ((event.clientX - r.left) / r.width) * 100;
            this.y = ((event.clientY - r.top) / r.height) * 100;
            this.zoom = true;
        },
        get zoomStyle() {
            return `transform-origin: ${this.x}% ${this.y}%; transform: scale(${this.zoom ? 2.2 : 1})`;
        },
    };
}

/** Bayi sepete ekleme miktarı: en az "min", "step" adımlarıyla (ondalıklı birimler 3 basamağa yuvarlanır). */
export function quantityStepper(min, step) {
    return {
        q: min,
        decrease() {
            this.q = Math.max(min, +(this.q - step).toFixed(3));
        },
        increase() {
            this.q = +(+this.q + step).toFixed(3);
        },
    };
}

/** Mağaza adres formu: il seçilince ilçeler sunucudan yüklenir (il-ilçe uyumu sunucuda ayrıca denetlenir). */
export function addressForm(province, district) {
    return {
        province,
        district,
        districts: [],
        loading: false,
        async load(keep) {
            if (!keep) this.district = '';
            this.districts = [];
            if (!this.province) return;
            this.loading = true;
            const res = await fetch('/ilceler/' + this.province, { headers: { Accept: 'application/json' } });
            this.districts = res.ok ? await res.json() : [];
            this.loading = false;
        },
    };
}

/** Mağaza ürün sayfası: seçenek (varyant) ve adet. Fiyat ve stok sunucuda yeniden denetlenir. */
export function productVariants(variants, index) {
    return {
        variants,
        index,
        qty: 1,
        get v() {
            return this.variants[this.index];
        },
        select(i) {
            this.index = i;
            this.qty = 1;
        },
        decrease() {
            this.qty = Math.max(1, this.qty - 1);
        },
        increase() {
            this.qty = Math.min(this.v.available || 1, this.qty + 1);
        },
    };
}

/** Mağaza ana sayfası banner'ları: 6 saniyede bir değişir, fare üzerindeyken durur. */
export function bannerRotator(count) {
    return {
        i: 0,
        n: count,
        timer: null,
        start() {
            if (this.n > 1) this.timer = setInterval(() => (this.i = (this.i + 1) % this.n), 6000);
        },
        stop() {
            clearInterval(this.timer);
        },
    };
}

/**
 * Markdown metin alanı için biçim düğmeleri (Hakkımızda hikâyesi). Seçili metni sarar veya satır başına işaret ekler.
 * Şablon: x-data="markdownToolbar()" ve x-ref="text" olan textarea; düğmeler bold / heading / bullet / link çağırır.
 */
export function markdownToolbar() {
    return {
        wrap(before, after, placeholder) {
            const el = this.$refs.text;
            const start = el.selectionStart;
            const end = el.selectionEnd;
            const selected = el.value.slice(start, end) || placeholder;
            el.value = el.value.slice(0, start) + before + selected + after + el.value.slice(end);
            el.focus();
            el.setSelectionRange(start + before.length, start + before.length + selected.length);
        },
        prefixLines(prefix) {
            const el = this.$refs.text;
            const lineStart = el.value.lastIndexOf('\n', el.selectionStart - 1) + 1;
            const end = el.selectionEnd;
            const block = el.value.slice(lineStart, end) || 'Madde';
            const prefixed = block.split('\n').map((line) => prefix + line).join('\n');
            el.value = el.value.slice(0, lineStart) + prefixed + el.value.slice(end);
            el.focus();
        },
        bold() { this.wrap('**', '**', 'kalın yazı'); },
        heading() { this.prefixLines('## '); },
        bullet() { this.prefixLines('- '); },
        link() { this.wrap('[', '](https://)', 'bağlantı metni'); },
    };
}

/**
 * Vitrin: sol altta "başa dön" düğmesi. 400 px aşağı inince görünür; çevresindeki halka sayfanın ne kadarının
 * okunduğunu gösterir (stroke-dashoffset, çevre 2πr = 113.1 için r = 18).
 */
export function scrollTop() {
    return {
        visible: false,
        progress: 0,
        init() {
            const update = () => {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                this.visible = window.scrollY > 400;
                this.progress = max > 0 ? Math.min(1, window.scrollY / max) : 0;
            };
            update();
            window.addEventListener('scroll', update, { passive: true });
        },
        get dash() {
            return ((1 - this.progress) * 113.1).toFixed(1);
        },
        up() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}

/**
 * Vitrin üst bilgisi: arama kutusunda yazarken öneriler (ürün, kategori, marka). Sunucu: GET ara/oneri?q=…
 * Kısa bekleme (debounce) ile istek atılır; eski yanıt yenisini ezmez.
 */
export function liveSearch(url, initial) {
    return {
        q: initial || '',
        open: false,
        loading: false,
        results: { products: [], categories: [], brands: [], all: '' },
        timer: null,
        seq: 0,
        get hasResults() {
            return this.results.products.length + this.results.categories.length + this.results.brands.length > 0;
        },
        get showEmpty() {
            return this.open && ! this.loading && ! this.hasResults && this.q.trim().length >= 2;
        },
        input() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 2) {
                this.open = false;
                return;
            }
            this.timer = setTimeout(() => this.fetch(), 180);
        },
        async fetch() {
            const seq = ++this.seq;
            this.loading = true;
            this.open = true;
            try {
                const res = await window.fetch(url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                const data = res.ok ? await res.json() : null;
                if (seq === this.seq && data) {
                    this.results = data;
                }
            } finally {
                if (seq === this.seq) {
                    this.loading = false;
                }
            }
        },
        reopen() {
            if (this.q.trim().length >= 2) {
                this.open = true;
            }
        },
        close() {
            this.open = false;
        },
    };
}

/** Geniş kategori menüsü: "Tüm kategoriler" düğmesiyle açılır; soldaki ana kategorinin üzerine gelince sağ taraf değişir. */
export function megaMenu() {
    return {
        open: false,
        active: 0,
        toggle() {
            this.open = ! this.open;
        },
        show(index) {
            this.active = index;
        },
        isActive(index) {
            return this.active === index;
        },
        close() {
            this.open = false;
        },
    };
}

/**
 * Sepete ekleme formu: sayfa yenilenmeden eklenir, mini sepet açılır. JavaScript yoksa form normal gönderilir.
 * Olaylar: cart-updated (sayı), cart-error (mesaj).
 */
export function addToCart() {
    return {
        busy: false,
        async submit() {
            if (this.busy) {
                return;
            }
            this.busy = true;
            try {
                const res = await window.fetch(this.$el.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(this.$el),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.count, message: data.message, open: true } }));
                } else {
                    const first = data.errors ? Object.values(data.errors)[0][0] : null;
                    window.dispatchEvent(new CustomEvent('cart-error', { detail: { message: first || data.message || 'Ürün sepete eklenemedi.' } }));
                }
            } catch (e) {
                this.$el.submit();
            } finally {
                this.busy = false;
            }
        },
    };
}

/** Sağdan açılan mini sepet. İçerik sunucudan HTML parçası olarak gelir (GET sepet/cekmece), x-ref="body" içine konur. */
export function miniCart(url) {
    return {
        open: false,
        init() {
            window.addEventListener('cart-updated', (e) => this.load(e.detail && e.detail.open));
            window.addEventListener('open-cart', () => this.load(true));
        },
        async load(show) {
            const res = await window.fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            if (res.ok) {
                // x-html CSP sürümünde yasak; içerik (sunucunun kendi HTML'i) doğrudan yerleştirilir.
                this.$refs.body.innerHTML = await res.text();
            }
            if (show) {
                this.open = true;
            }
        },
        close() {
            this.open = false;
        },
    };
}

/** Üst bilgideki ve mobil alt menüdeki sepet sayısı. */
export function cartBadge(count) {
    return {
        count: count,
        init() {
            window.addEventListener('cart-updated', (e) => {
                if (e.detail && typeof e.detail.count === 'number') {
                    this.count = e.detail.count;
                }
            });
        },
        openCart() {
            window.dispatchEvent(new CustomEvent('open-cart'));
        },
    };
}

/** Kısa bildirim balonu (sepete eklendi / hata). */
export function toast() {
    return {
        message: '',
        error: false,
        visible: false,
        timer: null,
        init() {
            window.addEventListener('cart-error', (e) => this.show(e.detail.message, true));
            window.addEventListener('store-toast', (e) => this.show(e.detail.message, false));
        },
        show(message, error) {
            this.message = message;
            this.error = error;
            this.visible = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.visible = false;
            }, 4000);
        },
        hide() {
            this.visible = false;
        },
    };
}

/** Sekmeler (ana sayfa ürün vitrini): x-data="tabs('featured')", düğmede @click="select('new')", içerikte x-show="is('new')". */
export function tabs(initial) {
    return {
        active: initial,
        select(key) {
            this.active = key;
        },
        is(key) {
            return this.active === key;
        },
    };
}

/** Kupon kodunu panoya kopyalar (ana sayfa kampanya kartı). */
export function copyCode(code) {
    return {
        copied: false,
        copy() {
            navigator.clipboard.writeText(code).then(() => {
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            });
        },
    };
}

/**
 * Favori kalbi: x-data="favorite(url, durum, girişAdresi)", düğmede @click.prevent="toggle()".
 * Üye değilse giriş sayfasına gider; sonuç toast ile bildirilir.
 */
export function favorite(url, initial, loginUrl) {
    return {
        on: initial,
        busy: false,
        async toggle() {
            if (this.busy) {
                return;
            }
            this.busy = true;
            try {
                const res = await window.fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });
                if (res.status === 401) {
                    window.location.href = loginUrl;
                    return;
                }
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    this.on = data.favorite;
                    window.dispatchEvent(new CustomEvent('store-toast', { detail: { message: data.message } }));
                }
            } finally {
                this.busy = false;
            }
        },
    };
}

/** Yıldız seçimi (yorum formu): x-data="starRating(0)", gizli alan :value="value". */
export function starRating(initial) {
    return {
        value: initial,
        hover: 0,
        set(n) {
            this.value = n;
        },
        lit(n) {
            return (this.hover || this.value) >= n;
        },
    };
}
