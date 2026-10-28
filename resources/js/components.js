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

const DISCOUNT_TEMPLATES = {
    cart: { kind: 'campaign', type: 'percent', audience: 'all', name: 'Sepette indirim' },
    products: { kind: 'campaign', type: 'percent', audience: 'all', name: 'Seçili ürünlerde indirim' },
    welcome: { kind: 'campaign', type: 'percent', audience: 'new_members', newDays: '30', name: 'Hoş geldin indirimi' },
    shipping: { kind: 'campaign', type: 'free_shipping', audience: 'all', name: 'Kargo bizden' },
    coupon: { kind: 'coupon', type: 'percent', audience: 'all', name: '' },
};

/**
 * Mağaza yönetimi kampanya formu: hazır şablonlar, müşterinin göreceği canlı özet ve çoklu ürün seçici.
 * Özet yalnızca yol göstericidir; kurallar ve tutarlar sunucuda (DiscountService) denetlenir.
 */
export function discountForm(state, lookupUrl) {
    return {
        template: null,
        q: '',
        results: [],
        open: false,
        loading: false,
        ...state,
        applyTemplate(key) {
            const t = DISCOUNT_TEMPLATES[key];
            this.template = key;
            this.kind = t.kind;
            this.type = t.type;
            this.audience = t.audience;
            if (t.newDays && !this.newDays) {
                this.newDays = t.newDays;
            }
            if (t.name && !this.name.trim()) {
                this.name = t.name;
            }
            this.$nextTick(() => {
                const target = this.$refs['focus_' + key];
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.focus({ preventScroll: true });
                }
            });
        },
        isTemplate(key) {
            return this.template === key;
        },
        countCategories() {
            this.categoryCount = this.$root.querySelectorAll('input[name="category_ids[]"]:checked').length;
        },
        get scoped() {
            return this.categoryCount > 0 || this.products.length > 0;
        },
        get headline() {
            const value = String(this.value || '').trim() || '…';
            const benefit = this.type === 'free_shipping' ? 'ücretsiz kargo' : this.type === 'percent' ? '%' + value + ' indirim' : value + ' ₺ indirim';
            const min = String(this.minSubtotal || '').trim();
            let where = this.scoped ? 'Seçili ürünlerde' : 'Tüm ürünlerde';
            if (min) {
                where = min + ' ₺ ve üzeri ' + (this.scoped ? 'kampanyalı ürün alışverişlerinde' : 'alışverişlerde');
            }
            const who = { members: ' · yalnızca üyelere', new_members: ' · yeni üyelerin ilk siparişinde' }[this.audience] || '';
            const how = this.kind === 'coupon' ? ' · kupon koduyla' : ' · sepette kendiliğinden uygulanır';

            return where + ' ' + benefit + who + how;
        },
        async search() {
            if (this.q.trim().length < 2) {
                this.results = [];
                this.open = false;
                return;
            }
            this.loading = true;
            const res = await fetch(lookupUrl + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
            this.results = res.ok ? await res.json() : [];
            this.loading = false;
            this.open = true;
        },
        add(product) {
            if (!this.products.some((p) => p.id === product.id)) {
                this.products.push(product);
            }
            this.q = '';
            this.results = [];
            this.open = false;
        },
        remove(id) {
            this.products = this.products.filter((p) => p.id !== id);
        },
        isPicked(id) {
            return this.products.some((p) => p.id === id);
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
            clearInterval(this.timer);
            if (this.n > 1) this.timer = setInterval(() => (this.i = (this.i + 1) % this.n), 2000);
        },
        stop() {
            clearInterval(this.timer);
        },
        go(index) {
            this.i = index;
        },
        next() {
            this.i = (this.i + 1) % this.n;
        },
        prev() {
            this.i = (this.i - 1 + this.n) % this.n;
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
/**
 * Tüm kategoriler açılır menüsü. İçerik (store.front._menu-desktop) düğmenin üzerine gelince veya açılınca bir kez yüklenir;
 * yüklenen parça setNames() ile arama için kategori adlarını bildirir.
 */
export function megaMenu(url) {
    return {
        ...lazyMenu(url),
        open: false,
        active: 0,
        query: '',
        names: [],
        timer: null,
        init() {
            this.$watch('query', () => {
                if (! this.matches(this.active)) {
                    const first = this.names.findIndex((name, i) => this.matches(i));
                    if (first >= 0) {
                        this.active = first;
                    }
                }
            });
        },
        toggle() {
            this.open = ! this.open;
            this.load();
        },
        // Fareyle çapraz geçerken üzerinden geçilen kategoriler açılmasın diye kısa bekleme; sağ panele girilince iptal.
        show(index) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                this.active = index;
            }, 90);
        },
        pick(index) {
            clearTimeout(this.timer);
            this.active = index;
        },
        hold() {
            clearTimeout(this.timer);
        },
        isActive(index) {
            return this.active === index;
        },
        matches(index) {
            return nameMatches(this.names[index], this.query);
        },
        get noMatch() {
            return this.query.trim() !== '' && ! this.names.some((name, i) => this.matches(i));
        },
        close() {
            this.open = false;
        },
    };
}

/**
 * Mobil kategori menüsü: ana kategori › alt kategori › alt kategori ekranları arasında ileri/geri geçilir.
 * İçerik (store.front._menu-mobile) çekmece ilk açıldığında yüklenir.
 */
export function drillMenu(url) {
    return {
        ...lazyMenu(url),
        stack: [],
        query: '',
        names: [],
        init() {
            this.$watch('menu', (value) => {
                if (value) {
                    this.load();
                }
            });
        },
        go(screen) {
            this.stack.push(screen);
        },
        back() {
            this.stack.pop();
        },
        isScreen(screen) {
            return (this.stack.length ? this.stack[this.stack.length - 1] : '') === screen;
        },
        matches(index) {
            return nameMatches(this.names[index], this.query);
        },
        get noMatch() {
            return this.query.trim() !== '' && ! this.names.some((name, i) => this.matches(i));
        },
    };
}

function lazyMenu(url) {
    return {
        url: url,
        loaded: false,
        loading: false,
        failed: false,
        load() {
            if (! this.url || this.loaded || this.loading) {
                return;
            }
            this.loading = true;
            this.failed = false;
            fetch(this.url, { headers: { Accept: 'text/html' } })
                .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
                .then((html) => {
                    // Alpine eklenen parçayı kendiliğinden başlatır; parça bu bileşenin kapsamını kullanır.
                    this.$refs.menuBody.innerHTML = html;
                    this.loaded = true;
                })
                .catch(() => {
                    this.failed = true;
                })
                .finally(() => {
                    this.loading = false;
                });
        },
        setNames(names) {
            this.names = (names || []).map((name) => String(name).toLocaleLowerCase('tr'));
        },
    };
}

function nameMatches(name, query) {
    const q = String(query || '').trim().toLocaleLowerCase('tr');

    return q === '' || (name || '').indexOf(q) !== -1;
}

/**
 * Menü sekmelerinin altındaki kayan vurgu: fareyle gezilen sekmeye kayar, çıkınca etkin sekmeye döner.
 * Etkin sekme yoksa vurgu gizlenir. Sekmeler @mouseenter/@focus="move" ile bağlanır.
 */
export function navIndicator() {
    return {
        x: 0,
        w: 0,
        shown: false,
        ready: false,
        init() {
            this.reset();
            window.addEventListener('resize', () => this.reset());
            if (document.fonts) {
                document.fonts.ready.then(() => this.reset());
            }
            requestAnimationFrame(() => {
                this.ready = true;
            });
        },
        move(e) {
            this.place(e.currentTarget);
        },
        reset() {
            const active = this.$root.querySelector('[aria-current="page"]');
            if (active) {
                this.place(active);
            } else {
                this.shown = false;
            }
        },
        place(el) {
            this.x = el.offsetLeft;
            this.w = el.offsetWidth;
            this.shown = true;
        },
        get style() {
            return 'transform: translateX(' + this.x + 'px); width: ' + this.w + 'px';
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

/** Toplu e-posta gönderim ilerlemesi: gönderim sürerken birkaç saniyede bir durumu sorar. */
export function campaignProgress(url, initial) {
    return {
        state: initial,
        timer: null,
        init() {
            if (this.state.status === 'sending') {
                this.timer = setInterval(() => this.poll(), 4000);
            }
        },
        async poll() {
            const res = await window.fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) {
                this.state = await res.json();
                if (this.state.status !== 'sending') {
                    clearInterval(this.timer);
                }
            }
        },
        get barStyle() {
            return 'width: ' + this.state.percent + '%';
        },
        destroy() {
            clearInterval(this.timer);
        },
    };
}

/** Parola alanında göster / gizle: kapsayıcıda x-data="passwordReveal", düğmede @click="toggle()". */
export function passwordReveal() {
    return {
        shown: false,
        toggle() {
            const input = this.$root.querySelector('input');
            this.shown = input.type === 'password';
            input.type = this.shown ? 'text' : 'password';
        },
    };
}

/**
 * Sayıyı ekrana girince sıfırdan sayarak gösterir: x-data="countUp('5.000+')" x-text="shown".
 * Önek/sonek korunur ("%99", "10.000+"); ondalıklı veya sayı olmayan değerler olduğu gibi kalır.
 */
export function countUp(text) {
    return {
        shown: text,
        init() {
            const m = String(text).match(/^(\D*)(\d{1,3}(?:\.\d{3})+|\d+)(\D*)$/);
            if (!m || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
                return;
            }
            const [, pre, num, post] = m;
            const target = parseInt(num.replace(/\./g, ''), 10);
            // Yıl gibi görünen değerler (ör. 1998) saydırılmaz.
            if (!pre && !post && target >= 1900 && target <= 2100) {
                return;
            }
            const grouped = num.includes('.');
            const format = (n) => pre + (grouped ? n.toLocaleString('tr-TR') : String(n)) + post;
            this.shown = format(0);
            const io = new IntersectionObserver((entries) => {
                if (!entries[0].isIntersecting) {
                    return;
                }
                io.disconnect();
                const start = performance.now();
                const step = (t) => {
                    const p = Math.min(1, (t - start) / 1400);
                    this.shown = format(Math.round(target * (1 - Math.pow(1 - p, 3))));
                    if (p < 1) {
                        requestAnimationFrame(step);
                    }
                };
                requestAnimationFrame(step);
            });
            io.observe(this.$el);
        },
    };
}

/**
 * Ana sayfa bayilik çağrısı: alan ekrana girince "is-in" eklenir, yapboz parçaları birleşir (stiller app.css → .dealer-*).
 * Başlangıç durumu "is-armed" ile kurulur; JS yoksa ya da "hareketi azalt" açıksa alan olduğu gibi görünür.
 */
export function dealerJoin() {
    return {
        init() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
                return;
            }
            const el = this.$el;
            el.classList.add('is-armed');
            const io = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    io.disconnect();
                    el.classList.add('is-in');
                }
            }, { threshold: 0.3 });
            io.observe(el);
        },
    };
}

/**
 * Dikkat çekici alarm sesi (dosya yok, tarayıcı üretir): iki tonlu hızlı bip dizisi, ardından yükselen çağrı.
 * Bir çalış yaklaşık 1,4 saniye sürer.
 */
function playAlarm(ctx) {
    const t0 = ctx.currentTime + 0.02;
    const master = ctx.createGain();
    master.gain.value = 0.32;
    master.connect(ctx.destination);
    const tone = (freq, start, length, type, toFreq) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, start);
        if (toFreq) {
            osc.frequency.exponentialRampToValueAtTime(toFreq, start + length);
        }
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(1, start + 0.01);
        gain.gain.setValueAtTime(1, start + length - 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
        osc.connect(gain);
        gain.connect(master);
        osc.start(start);
        osc.stop(start + length + 0.02);
    };
    for (let i = 0; i < 6; i++) {
        tone(i % 2 ? 1318 : 988, t0 + i * 0.13, 0.11, 'square');
    }
    tone(660, t0 + 0.85, 0.5, 'sawtooth', 1320);
}

function audioContext() {
    const AC = window.AudioContext || window.webkitAudioContext;
    return AC ? new AC() : null;
}

/**
 * Mağaza yönetimi sesli yeni sipariş alarmı: x-data="orderAlarm({url, ordersUrl, state: {count, latest}})".
 * 15 saniyede bir görülmemiş sipariş sayısını sorar; varken alarm 3 saniyede bir çalar, sekme başlığı yanıp söner,
 * sekme arkadaysa (izin verildiyse) masaüstü bildirimi çıkar. Siparişler sayfası açılınca sunucu sayacı sıfırlar.
 * Tarayıcı sesi ancak sayfaya bir kez tıklanınca çaldırır: ilk tıklamada ses açılır, o zamana kadar "Sesi aç" görünür.
 */
export function orderAlarm(config) {
    return {
        count: config.state.count,
        latest: config.state.latest,
        blocked: false,
        ctx: null,
        poller: null,
        ringer: null,
        flasher: null,
        title: document.title,
        get active() {
            return this.count > 0;
        },
        get heading() {
            return this.count === 1 ? 'Yeni sipariş geldi!' : this.count + ' yeni sipariş geldi!';
        },
        init() {
            this.unlock = () => this.resume();
            document.addEventListener('pointerdown', this.unlock);
            document.addEventListener('keydown', this.unlock);
            this.onVisible = () => {
                if (!document.hidden) {
                    this.poll();
                }
            };
            document.addEventListener('visibilitychange', this.onVisible);
            this.poller = setInterval(() => this.poll(), 15000);
            this.update();
        },
        destroy() {
            clearInterval(this.poller);
            this.stop();
            document.removeEventListener('pointerdown', this.unlock);
            document.removeEventListener('keydown', this.unlock);
            document.removeEventListener('visibilitychange', this.onVisible);
        },
        async poll() {
            let res;
            try {
                res = await window.fetch(config.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            } catch (e) {
                return; // bağlantı koptu: sonraki denemede yeniden sorulur
            }
            if (!res.ok || !(res.headers.get('content-type') || '').includes('json')) {
                if ([401, 403, 419].includes(res.status) || res.redirected) {
                    clearInterval(this.poller); // oturum kapandı
                }
                return;
            }
            const state = await res.json();
            const grew = state.count > this.count;
            this.count = state.count;
            this.latest = state.latest;
            if (grew) {
                this.notifyDesktop();
            }
            this.update();
        },
        update() {
            if (this.active) {
                this.start();
            } else {
                this.stop();
            }
        },
        start() {
            if (this.ringer) {
                return;
            }
            this.ring();
            this.ringer = setInterval(() => this.ring(), 3000);
            let on = false;
            this.flasher = setInterval(() => {
                on = !on;
                document.title = on ? '🔔 (' + this.count + ') Yeni sipariş!' : this.title;
            }, 1000);
        },
        stop() {
            clearInterval(this.ringer);
            clearInterval(this.flasher);
            this.ringer = null;
            this.flasher = null;
            document.title = this.title;
        },
        ring() {
            if (!this.ctx) {
                this.ctx = audioContext();
            }
            if (!this.ctx) {
                return;
            }
            if (this.ctx.state === 'suspended') {
                this.ctx.resume().catch(() => {});
            }
            this.blocked = this.ctx.state !== 'running';
            if (!this.blocked) {
                playAlarm(this.ctx);
            }
        },
        resume() {
            if (this.ctx && this.ctx.state === 'suspended') {
                this.ctx.resume().then(() => {
                    this.blocked = false;
                }).catch(() => {});
            }
        },
        enableSound() {
            if (!this.ctx) {
                this.ctx = audioContext();
            }
            if (this.ctx) {
                this.ctx.resume().then(() => {
                    this.blocked = false;
                    playAlarm(this.ctx);
                }).catch(() => {});
            }
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },
        notifyDesktop() {
            if (!document.hidden || !('Notification' in window) || Notification.permission !== 'granted') {
                return;
            }
            const n = new Notification('Yeni sipariş geldi', { body: this.latest || '', tag: 'magaza-siparis', requireInteraction: true });
            n.onclick = () => {
                window.focus();
                window.location.href = config.ordersUrl;
            };
        },
    };
}

/** Mağaza Ayarları'ndaki "Alarmı dene" düğmesi: alarm sesini iki kez çalar ve masaüstü bildirimi izni ister. */
export function alarmPreview() {
    return {
        play() {
            const ctx = audioContext();
            if (!ctx) {
                return;
            }
            ctx.resume().then(() => {
                playAlarm(ctx);
                setTimeout(() => playAlarm(ctx), 1600);
            });
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },
    };
}

/** Fotoğraf galerisi büyük görünümü: x-data="photoGallery([{src, caption}])", open(i) / close() / next() / prev(). */
export function photoGallery(photos) {
    return {
        photos,
        index: null,
        get current() {
            return this.index === null ? { src: '', caption: '' } : this.photos[this.index];
        },
        get counter() {
            return this.index === null ? '' : (this.index + 1) + ' / ' + this.photos.length;
        },
        open(i) {
            this.index = i;
            document.documentElement.classList.add('overflow-hidden');
        },
        close() {
            this.index = null;
            document.documentElement.classList.remove('overflow-hidden');
        },
        next() {
            if (this.index !== null) {
                this.index = (this.index + 1) % this.photos.length;
            }
        },
        prev() {
            if (this.index !== null) {
                this.index = (this.index - 1 + this.photos.length) % this.photos.length;
            }
        },
    };
}

/** Sayfayı yazdırır: düğmede x-data="printPage" @click="print()". */
export function printPage() {
    return {
        print() {
            window.print();
        },
    };
}

/**
 * Mağaza adı + slogan (x-store.wordmark): iki satırın harf kenarları milimetrik hizalanır. Harflerin kendi iç
 * boşlukları (ör. "Y"nin solu, "K"nin sağı) farklı olduğundan, mürekkep kenarları tuval üzerinde ölçülür ve slogan
 * bunlara göre yerleştirilir (x ve textLength). Yazı tipi yüklenince ve pencere boyutu değişince yeniden hesaplanır.
 * Şablon: x-data="wordmark" ve x-ref="name", x-ref="text" (SVG text).
 */
export function wordmark() {
    return {
        init() {
            const fit = () => this.fit();
            (document.fonts ? document.fonts.ready : Promise.resolve()).then(fit);
            window.addEventListener('resize', fit, { passive: true });
        },
        fit() {
            const name = this.$refs.name;
            const text = this.$refs.text;
            if (!name || !text || !text.getBBox || text.ownerSVGElement.getBoundingClientRect().width === 0) {
                return;
            }
            const ctx = document.createElement('canvas').getContext('2d');
            const measure = (str, style, spacing) => {
                ctx.font = style;
                if ('letterSpacing' in ctx) {
                    ctx.letterSpacing = spacing || '0px';
                }
                return ctx.measureText(str);
            };

            const cs = getComputedStyle(name);
            const nameText = name.textContent.trim();
            const nm = measure(nameText, `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`, cs.letterSpacing);
            const inkStart = -nm.actualBoundingBoxLeft;
            const inkEnd = nm.actualBoundingBoxRight;

            const ts = getComputedStyle(text);
            const tagStyle = `${text.getAttribute('font-weight')} ${text.getAttribute('font-size')}px ${ts.fontFamily}`;
            const tag = text.textContent.trim();
            const first = measure(tag.charAt(0), tagStyle);
            const last = measure(tag.charAt(tag.length - 1), tagStyle);
            const leftBearing = -first.actualBoundingBoxLeft;
            const rightBearing = last.width - last.actualBoundingBoxRight;

            const x = inkStart - leftBearing;
            const length = inkEnd + rightBearing - x;
            if (length > 0) {
                text.setAttribute('x', x.toFixed(2));
                text.setAttribute('textLength', length.toFixed(2));
            }
        },
    };
}

/**
 * Havale / EFT banka hesapları (mağaza yönetimi): satır ekle/sil; IBAN'ın banka kodundan banka adı ve logosu
 * canlı gösterilir (liste sunucudan gelir: TurkishBanks). Tanınmayan bankada ad elle yazılır. Doğrulama sunucuda.
 */
export function bankAccounts(rows, banks, errors, max) {
    const blank = () => ({ bank: '', holder: '', iban: '' });
    return {
        rows: rows.length ? rows : [blank()],
        errors,
        max,
        add() {
            if (this.rows.length < this.max) this.rows.push(blank());
        },
        remove(index) {
            this.rows.splice(index, 1);
            this.errors = {};
            if (!this.rows.length) this.rows.push(blank());
        },
        canAdd() {
            return this.rows.length < this.max;
        },
        digits(row) {
            return String(row.iban || '').replace(/\s+/g, '').toUpperCase();
        },
        bank(row) {
            const iban = this.digits(row);
            return /^TR\d{7}/.test(iban) ? banks[iban.substring(4, 9)] || null : null;
        },
        known(row) {
            return this.bank(row) !== null;
        },
        // Banka kodu yazıldı ama listede yok: ad elle istenir.
        unknown(row) {
            return /^TR\d{7}/.test(this.digits(row)) && !this.known(row);
        },
        bankName(row) {
            const b = this.bank(row);
            return b ? b.name : row.bank || 'Banka';
        },
        bankLogo(row) {
            const b = this.bank(row);
            return b ? b.logo : '';
        },
        title(row) {
            if (this.known(row)) return this.bankName(row);
            return this.unknown(row) ? 'Banka tanınmadı' : 'Yeni hesap';
        },
        status(row) {
            if (this.known(row)) return "IBAN'dan tanındı";
            return this.unknown(row) ? 'Banka adını aşağıya yazın' : 'IBAN yazınca banka tanınır';
        },
        initials(row) {
            const name = String(row.bank || '').trim();
            return name ? name.split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toLocaleUpperCase('tr') : '?';
        },
        // Yazarken IBAN'ı TR00 0000 ... diye dörtlü gruplar.
        tidy(row) {
            const iban = this.digits(row).replace(/[^A-Z0-9]/g, '').substring(0, 26);
            row.iban = iban.replace(/(.{4})/g, '$1 ').trim();
        },
        error(index, field) {
            return this.errors[index + '.' + field] || '';
        },
    };
}

/**
 * Ürün listesi (mağaza yönetimi): seçim kutuları ve görsel önizleme. Küçük görselin üzerine gelince yanında büyük
 * önizleme çıkar (ekrana sığacak yöne yerleşir); tıklayınca tam ekran açılır. Escape ile kapanır.
 */
export function productList() {
    const CARD = 300; // önizleme kartının genişliği/yüksekliği (px)
    return {
        selected: [],
        all: false,
        hover: null,
        preview: null,
        x: 0,
        y: 0,
        show(event, data) {
            const r = event.currentTarget.getBoundingClientRect();
            const right = r.right + 14 + CARD <= window.innerWidth;
            this.x = right ? r.right + 14 : Math.max(8, r.left - 14 - CARD);
            this.y = Math.min(Math.max(8, r.top + r.height / 2 - CARD / 2), window.innerHeight - CARD - 64);
            this.hover = data;
        },
        hide() {
            this.hover = null;
        },
        open(data) {
            this.hover = null;
            this.preview = data;
        },
        close() {
            this.preview = null;
        },
        get hoverStyle() {
            return 'left: ' + this.x + 'px; top: ' + this.y + 'px; width: ' + CARD + 'px';
        },
    };
}

/**
 * Fotokopi sayfası: belgeler parça parça (chunk) yüklenir, sunucu sayfa sayısını çıkarınca seçenekler açılır.
 * Fiyat her değişiklikte sunucudan alınır (tarayıcı fiyat hesaplamaz). Yarım kalan yükleme 409 yanıtındaki
 * "received" değerinden sürer. config: { urls: {start, file}, files, maxBytes, maxFiles, maxCopies }.
 * urls.file "__T__" yer tutuculu belge adresidir (durum, parça, önizleme, fiyat, sepet bunun altındadır).
 */
export function printOrder(config) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const fileUrl = (token, suffix) => config.urls.file.replace('__T__', token) + (suffix || '');
    const defaults = () => ({ color: 'bw', duplex: '0', layout: 1, size: 'A4', range: '', copies: 1, binding: 'none', note: '' });
    let uid = 0;
    const prepare = (f) => Object.assign({ uid: ++uid, progress: 100, total: null, summary: '', lines: [], busy: false, added: false, opts: defaults(), timer: null }, f, { error: f.error || '' });

    return {
        files: (config.files || []).map(prepare),
        consent: false,
        dragging: false,
        message: '',
        init() {
            this.files.forEach((f) => {
                if (f.status === 'processing') {
                    this.poll(f);
                } else if (f.status === 'ready') {
                    this.quote(f);
                }
            });
        },
        get hasFiles() {
            return this.files.length > 0;
        },
        pick(event) {
            this.addFiles(event.target.files);
            event.target.value = '';
        },
        drop(event) {
            this.dragging = false;
            this.addFiles(event.dataTransfer.files);
        },
        dragOver() {
            this.dragging = true;
        },
        dragLeave() {
            this.dragging = false;
        },
        addFiles(list) {
            this.message = '';
            if (!this.consent) {
                this.message = 'Yüklemeden önce belgeyi çoğaltma hakkınız olduğunu onaylayın.';
                return;
            }
            Array.from(list).forEach((file) => {
                if (this.files.length >= config.maxFiles) {
                    this.message = 'En fazla ' + config.maxFiles + ' belge yükleyebilirsiniz.';
                } else if (file.size > config.maxBytes) {
                    this.message = file.name + ': dosya ' + Math.round(config.maxBytes / 1048576) + ' MB sınırını aşıyor.';
                } else {
                    this.upload(file);
                }
            });
        },
        async request(url, method, body) {
            const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
            if (body && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(body);
            }
            const res = await window.fetch(url, { method: method, headers: headers, body: body, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            return { status: res.status, ok: res.ok, data: data };
        },
        async upload(source) {
            this.files.push(prepare({ token: null, name: source.name, size: source.size, received: 0, status: 'uploading', pages: null, preview: null, image: false, progress: 0 }));
            const f = this.files[this.files.length - 1];
            try {
                const started = await this.request(config.urls.start, 'POST', { name: source.name, size: source.size, consent: this.consent ? 1 : 0 });
                if (!started.ok) {
                    throw new Error(started.data.message || 'Yükleme başlatılamadı.');
                }
                f.token = started.data.token;
                const chunk = started.data.chunk;
                let offset = 0;
                let retries = 0;
                while (offset < source.size) {
                    const form = new FormData();
                    form.append('offset', offset);
                    form.append('chunk', source.slice(offset, offset + chunk), 'parca');
                    let res;
                    try {
                        res = await this.request(fileUrl(f.token, '/parca'), 'POST', form);
                    } catch (e) {
                        res = { status: 0, ok: false, data: {} };
                    }
                    if (res.ok) {
                        offset = res.data.received;
                        retries = 0;
                        f.status = res.data.status;
                        f.progress = Math.floor((offset / source.size) * 100);
                    } else if (res.status === 409 && typeof res.data.received === 'number') {
                        offset = res.data.received;
                    } else if ((res.status === 0 || res.status >= 500) && retries < 5) {
                        retries++;
                        await new Promise((r) => setTimeout(r, 2000 * retries));
                    } else {
                        throw new Error(res.data.message || 'Yükleme yarıda kaldı.');
                    }
                }
                f.progress = 100;
                this.poll(f);
            } catch (e) {
                f.status = 'failed';
                f.error = e.message;
            }
        },
        poll(f) {
            setTimeout(async () => {
                if (!this.files.includes(f)) {
                    return;
                }
                const res = await this.request(fileUrl(f.token), 'GET').catch(() => ({ ok: false }));
                if (res.ok) {
                    Object.assign(f, res.data, { error: res.data.error || '' });
                }
                if (f.status === 'ready') {
                    this.quote(f);
                } else if (f.status === 'processing' || !res.ok) {
                    this.poll(f);
                }
            }, 2000);
        },
        requote(f) {
            f.added = false;
            clearTimeout(f.timer);
            f.timer = setTimeout(() => this.quote(f), 350);
        },
        async quote(f) {
            const res = await this.request(fileUrl(f.token, '/fiyat'), 'POST', f.opts);
            if (res.ok) {
                f.total = res.data.total;
                f.summary = res.data.summary;
                f.lines = res.data.quote.lines;
                f.error = '';
            } else {
                f.total = null;
                f.lines = [];
                f.error = res.data.message || 'Fiyat hesaplanamadı.';
            }
        },
        async addToCart(f) {
            if (f.busy || !f.total) {
                return;
            }
            f.busy = true;
            const res = await this.request(fileUrl(f.token, '/sepet'), 'POST', f.opts).catch(() => ({ ok: false, data: {} }));
            f.busy = false;
            if (res.ok) {
                f.added = true;
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: res.data.count, open: true } }));
            } else {
                f.error = res.data.message || 'Sepete eklenemedi.';
            }
        },
        async remove(f) {
            this.files = this.files.filter((x) => x !== f);
            if (f.token) {
                await this.request(fileUrl(f.token), 'DELETE').catch(() => null);
            }
        },
        isReady(f) {
            return f.status === 'ready';
        },
        isBusy(f) {
            return f.status === 'uploading' || f.status === 'processing';
        },
        isFailed(f) {
            return f.status === 'failed';
        },
        statusText(f) {
            if (f.status === 'uploading') {
                return 'Yükleniyor… %' + f.progress;
            }
            if (f.status === 'processing') {
                return 'Sayfalar sayılıyor…';
            }
            if (f.status === 'ready') {
                return f.pages + ' sayfa';
            }
            return 'Yüklenemedi';
        },
        sizeText(f) {
            return f.size >= 1048576 ? (f.size / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
        },
        progressStyle(f) {
            return 'width: ' + f.progress + '%';
        },
        stepCopies(f, delta) {
            const next = (parseInt(f.opts.copies, 10) || 1) + delta;
            f.opts.copies = Math.min(Math.max(next, 1), config.maxCopies || 100);
            this.requote(f);
        },
        /** Dosya türü rozeti: etiket ve renk sınıfı. */
        extLabel(f) {
            return (f.name.split('.').pop() || '').toUpperCase().slice(0, 4);
        },
        extClass(f) {
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (ext === 'pdf') {
                return 'bg-red-50 text-red-600 ring-red-200';
            }
            if (['doc', 'docx', 'odt'].includes(ext)) {
                return 'bg-puzzle-blue-soft text-puzzle-blue ring-puzzle-blue/30';
            }
            if (['ppt', 'pptx', 'odp'].includes(ext)) {
                return 'bg-puzzle-orange-soft text-puzzle-orange ring-puzzle-orange/30';
            }
            if (['xls', 'xlsx', 'ods'].includes(ext)) {
                return 'bg-puzzle-green-soft text-puzzle-green ring-puzzle-green/30';
            }
            return 'bg-violet-50 text-violet-700 ring-violet-200';
        },
        isChoice(f, key, value) {
            return String(f.opts[key]) === String(value);
        },
        choose(f, key, value) {
            f.opts[key] = value;
            this.requote(f);
        },
        canAdd(f) {
            return f.status === 'ready' && f.total !== null && !f.busy;
        },
    };
}

/**
 * Fatura taslağı düzenleyici (Faturalar): kalem ekleme / silme ve canlı toplamlar. Buradaki hesap yalnızca
 * gösterim içindir; kesin tutarlar kaydedilirken sunucuda hesaplanır (App\Domain\Store\Invoicing\InvoiceMath).
 */
export function invoiceEditor(config) {
    const blank = () => ({ order_item_id: '', name: '', sku: '', quantity: '1', unit_price: '', discount: '', vat_rate: config.defaultVat });
    const money = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
    const num = (value) => {
        let s = String(value ?? '').replace(/[\s₺]/g, '');
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        const n = Number.parseFloat(s);
        return Number.isFinite(n) ? n : 0;
    };
    const round = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
    return {
        rows: config.lines.length ? config.lines : [blank()],
        buyerType: config.buyerType,
        errors: config.errors,
        max: config.max,
        expected: num(config.expected),
        add() {
            if (this.rows.length < this.max) this.rows.push(blank());
        },
        remove(index) {
            this.rows.splice(index, 1);
            this.errors = {};
            if (!this.rows.length) this.rows.push(blank());
        },
        canAdd() {
            return this.rows.length < this.max;
        },
        isCorporate() {
            return this.buyerType === 'corporate';
        },
        lineTotal(row) {
            return round(round(num(row.quantity) * num(row.unit_price)) - num(row.discount));
        },
        lineNet(row) {
            return round((this.lineTotal(row) * 100) / (100 + num(row.vat_rate)));
        },
        lineText(row) {
            return money.format(this.lineTotal(row));
        },
        total() {
            return round(this.rows.reduce((sum, row) => sum + this.lineTotal(row), 0));
        },
        subtotal() {
            return round(this.rows.reduce((sum, row) => sum + this.lineNet(row), 0));
        },
        totalText() {
            return money.format(this.total());
        },
        subtotalText() {
            return money.format(this.subtotal());
        },
        vatText() {
            return money.format(round(this.total() - this.subtotal()));
        },
        difference() {
            return round(this.total() - this.expected);
        },
        hasDifference() {
            return Math.abs(this.difference()) >= 0.005;
        },
        differenceText() {
            const d = this.difference();
            return (d > 0 ? '+' : '') + money.format(d);
        },
        error(index, field) {
            return this.errors['lines.' + index + '.' + field] || '';
        },
    };
}

/** Listede seçim kutuları (Faturalar): hiçbiri seçili değilken toplu işlem düğmesi kapalıdır. */
export function pickList() {
    return {
        picked: [],
        get none() {
            return this.picked.length === 0;
        },
    };
}
