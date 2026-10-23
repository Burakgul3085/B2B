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
