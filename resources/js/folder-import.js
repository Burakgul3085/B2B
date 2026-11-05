/**
 * Klasörden toplu ürün ekleme (B2B toptancı paneli ve mağaza yönetimi; docs/03-is-kurallari.md §2.4).
 *
 * Klasör düzeni:   [Ürünler/] Kategori/[Alt kategori/…] Marka/Ürün adı ; Fiyat ; Barkod ; Stok/1.jpg 2.jpg aciklama.txt
 * Seçenekli ürün:  Marka/Jel Kalem [Renk] ; 15,00/Mavi ; ; barkod ; stok/1.jpg …  (her alt klasör bir seçenek)
 *
 * Klasör tamamen tarayıcıda okunur ve önce gösterilir; hiçbir şey onaysız yazılmaz. Yüklemede:
 *  - var olan ürün (aynı kod veya barkod) atlanır: aynı klasör yeniden bırakılırsa kaldığı yerden sürer,
 *  - resimler tarayıcıda küçültülür (en uzun kenar 2000 px), ürünler 4 paralel istekle gönderilir,
 *  - geçici ağ/sunucu hatası birkaç kez yeniden denenir; hatalı ürün diğerlerini durdurmaz.
 * Sunucu her ürünü tek ürün ekleme ekranıyla aynı kurallarla yeniden doğrular; buradaki denetimler yalnızca
 * kullanıcıya erken ve anlaşılır uyarı içindir.
 */

const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];
const UNSUPPORTED_IMAGE = ['heic', 'heif', 'gif', 'bmp', 'tif', 'tiff', 'avif'];
const HIDDEN = /^(\.|~\$)|^(thumbs\.db|desktop\.ini)$/i;
const DESCRIPTION = /^a[cç][ıi]klama\.txt$/iu;
const WRAPPER = /^(ürünler|urunler|ürün|urun|products)$/iu;
const NO_BRAND = /^(markas[ıi]z|marka yok|-)$/iu;
const BARCODE = /^[A-Za-z0-9-]{1,64}$/;
const MAX_EDGE = 2000;
const KEEP_BYTES = 4 * 1024 * 1024;
const PARALLEL = 4;
const LOOKUP_CHUNK = 1000;
const MAX_SHOWN = 500;

const collator = new Intl.Collator('tr', { numeric: true, sensitivity: 'base' });
const byName = (a, b) => collator.compare(a.name, b.name);
const extOf = (name) => (name.includes('.') ? name.split('.').pop().toLowerCase() : '');
const key = (text) => String(text).toLowerCase().replace(/\s+/g, ' ').trim();
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** "1.234,56" / "49,90" / "49.90" / "49,90 TL" → "1234.56"; geçersiz veya sıfırsa null. */
export function parsePrice(raw, decimals) {
    let s = String(raw).replace(/\s|₺|tl/giu, '');
    if (s === '') {
        return null;
    }
    const frac = '(?:[.,]\\d{1,' + decimals + '})?';
    if (new RegExp('^\\d{1,3}(\\.\\d{3})+(,\\d{1,' + decimals + '})?$').test(s)) {
        s = s.replace(/\./g, '').replace(',', '.');
    } else if (new RegExp('^\\d+' + frac + '$').test(s)) {
        s = s.replace(',', '.');
    } else {
        return null;
    }

    return Number(s) > 0 ? s : null;
}

/** "Jel Kalem [Renk] ; 15,00 ; 869… ; 100" → parçalar. Yalnızca ad zorunludur. */
export function parseFolderName(name) {
    const parts = name.split(';').map((p) => p.trim());
    let title = parts[0] || '';
    let optionName = null;
    const marker = title.match(/\[([^\]]*)\]/);
    if (marker) {
        optionName = marker[1].trim();
        title = title.replace(marker[0], ' ').replace(/\s+/g, ' ').trim();
    }

    return { title, optionName, price: parts[1] || '', barcode: parts[2] || '', stock: parts[3] || '', extra: parts.length > 4 };
}

/** Kısa, kararlı kod: aynı kategori/marka/ad her yüklemede aynı ürün kodunu verir (ürün iki kez eklenmez). */
export function stableCode(text) {
    const hash = (seed) => {
        let h1 = 0xdeadbeef ^ seed;
        let h2 = 0x41c6ce57 ^ seed;
        for (let i = 0; i < text.length; i++) {
            const ch = text.charCodeAt(i);
            h1 = Math.imul(h1 ^ ch, 2654435761);
            h2 = Math.imul(h2 ^ ch, 1597334677);
        }
        h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
        h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);

        return (4294967296 * (2097151 & h2) + (h1 >>> 0)).toString(16).padStart(14, '0');
    };

    return 'YK-' + (hash(1) + hash(7)).slice(0, 12).toUpperCase();
}

/** Bırakılan klasörü okur: [{ path: 'Ürünler/Boyalar/…/1.jpg', file }]. */
async function readDropped(entries) {
    const out = [];
    const readAll = (dir) => new Promise((resolve, reject) => {
        const reader = dir.createReader();
        const all = [];
        const next = () => reader.readEntries((batch) => {
            if (batch.length === 0) {
                resolve(all);
            } else {
                all.push(...batch);
                next();
            }
        }, reject);
        next();
    });
    const walk = async (entry) => {
        if (entry.isFile) {
            const file = await new Promise((resolve, reject) => entry.file(resolve, reject));
            out.push({ path: entry.fullPath.replace(/^\/+/, ''), file });
        } else if (entry.isDirectory) {
            await Promise.all((await readAll(entry)).map(walk));
        }
    };
    await Promise.all(entries.map(walk));

    return out;
}

/**
 * Dosya listesinden ürünleri çıkarır. Sunucuya bir şey göndermez.
 * @param {{path: string, file: File}[]} files
 * @param {{mode: 'b2b'|'store', maxDepth: number, maxImages: number}} rules
 */
export function scan(files, rules) {
    const limits = rules.mode === 'store'
        ? { name: 200, optionName: 60, optionValue: 80, decimals: 2 }
        : { name: 255, optionName: 40, optionValue: 60, decimals: 4 };
    const folders = new Map();
    const notes = { ignored: 0, unsupported: 0, loose: 0, empty: [] };

    const folder = (path) => {
        if (!folders.has(path)) {
            const segs = path.split('/');
            const item = { path, name: segs[segs.length - 1], depth: segs.length, images: [], description: null, children: [] };
            folders.set(path, item);
            if (segs.length > 1) {
                folder(segs.slice(0, -1).join('/')).children.push(item);
            }
        }

        return folders.get(path);
    };

    for (const { path, file } of files) {
        const segs = path.split('/').filter(Boolean);
        const name = segs.pop();
        if (HIDDEN.test(name) || segs.some((s) => s.startsWith('.'))) {
            continue;
        }
        if (segs.length === 0) {
            notes.loose++;
            continue;
        }
        const target = folder(segs.join('/'));
        const ext = extOf(name);
        if (IMAGE_EXT.includes(ext)) {
            target.images.push({ name, file });
        } else if (DESCRIPTION.test(name)) {
            target.description = file;
        } else if (UNSUPPORTED_IMAGE.includes(ext)) {
            notes.unsupported++;
        } else {
            notes.ignored++;
        }
    }

    const products = [];
    const product = (dir, ancestors, info) => {
        const errors = [];
        const warnings = [];
        const categories = ancestors.slice(0, -1);
        const brand = ancestors.length ? ancestors[ancestors.length - 1] : '';
        if (ancestors.length < 2) {
            errors.push('Ürün klasörü "Kategori / Marka / Ürün" düzeninde olmalı; kategori veya marka klasörü eksik.');
        } else if (categories.length > rules.maxDepth) {
            errors.push('Kategori en fazla ' + rules.maxDepth + ' seviye olabilir: ' + categories.join(' › ') + '.');
        }
        if ([...categories, brand].some((n) => n.length > 120)) {
            errors.push('Kategori ve marka adları en fazla 120 karakter olabilir.');
        }
        if (info.title === '') {
            errors.push('Ürün adı yazılmamış.');
        } else if (info.title.length > limits.name) {
            errors.push('Ürün adı en fazla ' + limits.name + ' karakter olabilir.');
        }
        const price = parsePrice(info.price, limits.decimals);
        if (info.price !== '' && price === null) {
            errors.push('Fiyat okunamadı: "' + info.price + '". Örnek: 29,90');
        }
        if (info.extra) {
            errors.push('Klasör adında en fazla 4 bölüm olabilir: Ad ; Fiyat ; Barkod ; Stok.');
        }

        const item = {
            path: dir.path,
            name: info.title,
            categories,
            brand: NO_BRAND.test(brand) ? null : brand,
            price,
            barcode: null,
            stock: null,
            optionName: info.optionName,
            options: [],
            images: [...dir.images].sort(byName),
            description: dir.description,
            errors,
            warnings,
            sku: null,
            status: 'new',
        };

        if (info.optionName === null) {
            item.barcode = info.barcode || null;
            item.stock = info.stock || null;
            if (dir.children.length) {
                warnings.push('Alt klasörler yok sayıldı. Seçenekli ürünse klasör adına seçenek adını yazın: ' + info.title + ' [Renk] ; ' + (info.price || 'Fiyat'));
            }
        } else {
            if (info.optionName === '' || info.optionName.length > limits.optionName) {
                errors.push('Seçenek adı köşeli parantez içinde 1–' + limits.optionName + ' karakter olmalı (ör. [Renk]).');
            }
            if (info.barcode || info.stock) {
                warnings.push('Seçenekli üründe barkod ve stok seçenek klasörlerine yazılır; ürün klasöründekiler yok sayıldı.');
            }
            const seen = new Set();
            for (const child of [...dir.children].sort(byName)) {
                const o = parseFolderName(child.name);
                const value = o.title;
                const optionPrice = o.price === '' ? null : parsePrice(o.price, limits.decimals);
                if (value === '' || value.length > limits.optionValue) {
                    errors.push('"' + child.name + '": seçenek adı 1–' + limits.optionValue + ' karakter olmalı.');
                } else if (seen.has(key(value))) {
                    errors.push('"' + value + '" seçeneği iki kez var.');
                }
                seen.add(key(value));
                if (o.price !== '' && optionPrice === null) {
                    errors.push('"' + child.name + '": fiyat okunamadı.');
                }
                if (o.optionName !== null || o.extra) {
                    errors.push('"' + child.name + '": seçenek klasörü "Değer ; Fiyat ; Barkod ; Stok" şeklinde olmalı.');
                }
                if (child.children.length) {
                    warnings.push('"' + child.name + '" içindeki alt klasörler yok sayıldı.');
                }
                item.options.push({ value, price: optionPrice, barcode: o.barcode || null, stock: o.stock || null, images: [...child.images].sort(byName), sku: null, status: 'new' });
            }
            if (item.options.length === 0) {
                errors.push('Seçenek klasörü yok. Her seçenek (ör. Mavi, Kırmızı) ürün klasörünün içinde ayrı bir klasör olmalı.');
            } else if (item.options.length > 30) {
                errors.push('Bir üründe en fazla 30 seçenek olabilir.');
            }
        }

        // Fiyat ürün klasöründe yazılır; seçenekli üründe her seçeneğin kendi fiyatı varsa gerekmez.
        if (info.price === '' && (item.options.length === 0 || item.options.some((o) => o.price === null))) {
            errors.push('Fiyat yazılmamış. Klasör adı "Ürün adı ; Fiyat" şeklinde olmalı (ör. Pastel Boya 12 Renk ; 29,90).');
        }

        for (const row of [item, ...item.options]) {
            if (row.barcode !== null && !BARCODE.test(row.barcode)) {
                errors.push('Barkod yalnızca harf, rakam ve - içerebilir: "' + row.barcode + '".');
            }
            if (row.stock !== null && !/^\d{1,8}$/.test(row.stock)) {
                errors.push('Stok 0 veya daha büyük bir tam sayı olmalı: "' + row.stock + '".');
            }
        }

        const imageCount = rules.mode === 'store'
            ? item.images.length + item.options.reduce((n, o) => n + o.images.length, 0)
            : Math.max(item.images.length, ...item.options.map((o) => o.images.length));
        if (imageCount > rules.maxImages) {
            warnings.push('Bir ürüne en fazla ' + rules.maxImages + ' resim eklenir; fazlası atlandı.');
        }
        if (imageCount === 0) {
            warnings.push('Resim yok.');
        }

        // Ürün kodu: barkod varsa barkod, yoksa kategori + marka + addan türetilen kararlı kod.
        const base = key(categories.join('/')) + '|' + key(brand) + '|' + key(info.title);
        if (item.optionName === null) {
            item.sku = item.barcode || stableCode(base);
        } else {
            item.options.forEach((o) => {
                o.sku = o.barcode || stableCode(base + '|' + key(o.value));
            });
            item.sku = item.options.length ? item.options[0].sku : stableCode(base);
        }

        products.push(item);
    };

    const visit = (dir, ancestors) => {
        const info = parseFolderName(dir.name);
        const isProduct = info.optionName !== null || dir.images.length > 0 || dir.description !== null
            || (dir.children.length === 0 && dir.name.includes(';'));
        if (isProduct) {
            product(dir, ancestors, info);
        } else if (dir.children.length === 0) {
            notes.empty.push(dir.path);
        } else {
            [...dir.children].sort(byName).forEach((child) => visit(child, [...ancestors, dir.name]));
        }
    };

    [...folders.values()].filter((f) => f.depth === 1).sort(byName).forEach((root) => {
        if (WRAPPER.test(root.name) && root.images.length === 0) {
            [...root.children].sort(byName).forEach((child) => visit(child, []));
        } else {
            visit(root, []);
        }
    });

    // Aynı klasörde iki kez yazılmış kod veya barkod
    const codes = new Map();
    for (const item of products) {
        for (const row of item.optionName === null ? [item] : item.options) {
            for (const code of [row.sku, row.barcode]) {
                if (!code) {
                    continue;
                }
                const k = code.toLowerCase();
                if (codes.has(k) && codes.get(k) !== item) {
                    item.errors.push('Aynı ürün veya barkod (' + code + ') başka bir klasörde de var: ' + codes.get(k).path);
                }
                codes.set(k, item);
            }
        }
    }

    return { products, notes };
}

/** Görseli yüklemeye hazırlar: büyükse tarayıcıda küçültülür (sunucu zaten yeniden kodlar; amaç hız). */
async function prepareImage(image) {
    let bitmap;
    try {
        bitmap = await createImageBitmap(image.file);
    } catch (e) {
        return null; // bozuk veya desteklenmeyen dosya
    }
    try {
        const edge = Math.max(bitmap.width, bitmap.height);
        if (edge <= MAX_EDGE && image.file.size <= KEEP_BYTES) {
            return image.file;
        }
        const scale = Math.min(1, MAX_EDGE / edge);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff'; // saydam PNG beyaz zemine
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));

        return blob ? new File([blob], image.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : null;
    } finally {
        bitmap.close();
    }
}

/** aciklama.txt: UTF-8 değilse Türkçe Windows kodlamasıyla okunur. */
async function readDescription(file) {
    if (!file) {
        return '';
    }
    const bytes = await file.arrayBuffer();
    try {
        return new TextDecoder('utf-8', { fatal: true }).decode(bytes).replace(/^﻿/, '').trim();
    } catch (e) {
        return new TextDecoder('windows-1254').decode(bytes).trim();
    }
}

/**
 * Alpine bileşeni. config: { mode, urls: {lookup, prepare, store, products}, csrf, maxImages, maxDepth, taxes: [{id, label}], defaultTax }
 * Büyük veri (dosyalar, ürünler) reaktif olmayan değişkenlerde tutulur; ekranda yalnızca sayılar ve kısa listeler vardır.
 */
export function folderImport(config) {
    let products = [];
    let categoryIds = new Map();
    let brandIds = new Map();
    let startedAt = 0;

    return {
        phase: 'pick', // pick → reading → review → uploading → done
        dragging: false,
        message: '',
        fatal: '',
        paused: false,
        summary: { products: 0, options: 0, images: 0, new: 0, existing: 0, invalid: 0, newCategories: 0, newBrands: 0 },
        notes: { ignored: 0, unsupported: 0, loose: 0, empty: [] },
        problems: [],
        warnings: [],
        preview: [],
        taxGroups: [],
        includesTax: config.mode === 'store',
        activate: false,
        progress: { total: 0, done: 0, created: 0, skipped: 0, failed: 0 },
        failures: [],
        eta: '',

        init() {
            window.addEventListener('beforeunload', (event) => {
                if (this.phase === 'uploading') {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        dragOver() {
            this.dragging = true;
        },
        dragLeave() {
            this.dragging = false;
        },

        async drop(event) {
            this.dragging = false;
            if (this.busy()) {
                return;
            }
            const entries = [...event.dataTransfer.items]
                .map((item) => (item.webkitGetAsEntry ? item.webkitGetAsEntry() : null))
                .filter(Boolean);
            if (entries.length === 0) {
                this.message = 'Klasör okunamadı. "Klasör seç" düğmesini kullanın.';
                return;
            }
            this.phase = 'reading';
            this.message = 'Klasör okunuyor…';
            try {
                await this.load(await readDropped(entries));
            } catch (e) {
                this.reset('Klasör okunamadı. "Klasör seç" düğmesiyle yeniden deneyin.');
            }
        },

        async pick(event) {
            const files = [...event.target.files].map((file) => ({ path: file.webkitRelativePath || file.name, file }));
            event.target.value = '';
            if (files.length === 0 || this.busy()) {
                return;
            }
            this.phase = 'reading';
            this.message = 'Klasör okunuyor…';
            await this.load(files);
        },

        busy() {
            return this.phase === 'reading' || this.phase === 'uploading';
        },

        reset(message) {
            products = [];
            this.phase = 'pick';
            this.message = message || '';
            this.fatal = '';
            this.problems = [];
            this.warnings = [];
            this.preview = [];
            this.failures = [];
        },

        startOver() {
            this.reset('');
        },

        async load(files) {
            this.fatal = '';
            const result = scan(files, { mode: config.mode, maxDepth: config.maxDepth, maxImages: config.maxImages });
            products = result.products;
            this.notes = result.notes;
            if (products.length === 0) {
                this.reset('Bu klasörde ürün bulunamadı. Ürün klasörlerinin içinde resim olmalı veya klasör adı "Ürün adı ; Fiyat" şeklinde yazılmalı.');
                return;
            }

            try {
                this.message = 'Sistemde var olan ürünler denetleniyor…';
                await this.markExisting();
                this.message = 'Kategori ve markalar eşleştiriliyor…';
                await this.matchCatalog(false);
            } catch (e) {
                this.reset(e.message || 'Sunucuya ulaşılamadı. İnternet bağlantınızı denetleyip yeniden deneyin.');
                return;
            }

            this.summarize();
            this.phase = 'review';
            this.message = '';
        },

        /** Kodu veya barkodu sistemde olan ürün (ve seçenek) "var" sayılır ve atlanır. */
        async markExisting() {
            const skus = [];
            const barcodes = [];
            for (const item of products) {
                for (const row of [item, ...item.options]) {
                    if (row.sku) {
                        skus.push(row.sku);
                    }
                    if (row.barcode) {
                        barcodes.push(row.barcode);
                    }
                }
            }
            const found = { skus: new Set(), barcodes: new Set() };
            const chunks = Math.max(Math.ceil(skus.length / LOOKUP_CHUNK), Math.ceil(barcodes.length / LOOKUP_CHUNK));
            for (let i = 0; i < chunks; i++) {
                const res = await this.post(config.urls.lookup, {
                    skus: skus.slice(i * LOOKUP_CHUNK, (i + 1) * LOOKUP_CHUNK),
                    barcodes: barcodes.slice(i * LOOKUP_CHUNK, (i + 1) * LOOKUP_CHUNK),
                });
                Object.keys(res.skus || {}).forEach((s) => found.skus.add(s.toLowerCase()));
                Object.keys(res.barcodes || {}).forEach((b) => found.barcodes.add(b.toLowerCase()));
            }
            const exists = (row) => found.skus.has(String(row.sku).toLowerCase()) || (row.barcode !== null && found.barcodes.has(row.barcode.toLowerCase()));

            for (const item of products) {
                if (item.optionName === null || config.mode === 'store') {
                    // Mağazada ürün ve seçenekleri birlikte, tek seferde oluşturulur.
                    if (exists(item) || (item.options[0] && exists(item.options[0]))) {
                        item.status = 'exists';
                    } else if (item.options.some(exists)) {
                        item.errors.push('Seçeneklerden birinin kodu veya barkodu başka bir üründe kullanılıyor.');
                    }
                    continue;
                }
                // B2B: her seçenek ayrı üründür; yarıda kalan ürünün eksik seçenekleri eklenir.
                item.options.forEach((o) => {
                    o.status = exists(o) ? 'exists' : 'new';
                });
                if (item.options.every((o) => o.status === 'exists')) {
                    item.status = 'exists';
                } else if (item.options[0].status !== 'exists' && item.options.some((o) => o.status === 'exists')) {
                    item.errors.push('Seçeneklerden birinin kodu veya barkodu başka bir üründe kullanılıyor.');
                }
            }
        },

        /** Kategori yolları ve markalar sunucudakilerle eşleştirilir; create ise eksikler oluşturulur. */
        async matchCatalog(create) {
            const live = products.filter((p) => p.status === 'new' && p.errors.length === 0);
            const paths = new Map();
            const brands = new Map();
            live.forEach((item) => {
                if (item.categories.length) {
                    paths.set(item.categories.map(key).join('>'), item.categories);
                }
                if (item.brand) {
                    brands.set(key(item.brand), item.brand);
                }
            });
            const pathKeys = [...paths.keys()];
            const brandKeys = [...brands.keys()];
            const chunks = Math.max(1, Math.ceil(pathKeys.length / LOOKUP_CHUNK), Math.ceil(brandKeys.length / LOOKUP_CHUNK));
            categoryIds = new Map();
            brandIds = new Map();
            let newCategories = 0;
            let newBrands = 0;
            for (let i = 0; i < chunks; i++) {
                const pk = pathKeys.slice(i * LOOKUP_CHUNK, (i + 1) * LOOKUP_CHUNK);
                const bk = brandKeys.slice(i * LOOKUP_CHUNK, (i + 1) * LOOKUP_CHUNK);
                const res = await this.post(config.urls.prepare, { categories: pk.map((k) => paths.get(k)), brands: bk.map((k) => brands.get(k)), create });
                pk.forEach((k, n) => categoryIds.set(k, res.categories[n]));
                bk.forEach((k, n) => brandIds.set(k, res.brands[n]));
                newCategories += res.new_categories;
                newBrands += res.new_brands;
            }
            this.summary.newCategories = newCategories;
            this.summary.newBrands = newBrands;
        },

        summarize() {
            const s = { products: products.length, options: 0, images: 0, new: 0, existing: 0, invalid: 0, newCategories: this.summary.newCategories, newBrands: this.summary.newBrands };
            const problems = [];
            const warnings = [];
            const groups = new Map();
            for (const item of products) {
                s.options += item.options.length;
                s.images += item.images.length + item.options.reduce((n, o) => n + o.images.length, 0);
                if (item.errors.length) {
                    s.invalid++;
                    if (problems.length < MAX_SHOWN) {
                        problems.push({ path: item.path, message: item.errors.join(' ') });
                    }
                    continue;
                }
                if (item.status === 'exists') {
                    s.existing++;
                    continue;
                }
                s.new++;
                if (item.warnings.length && warnings.length < MAX_SHOWN) {
                    warnings.push({ path: item.path, message: item.warnings.join(' ') });
                }
                const top = item.categories[0] || '';
                const g = groups.get(key(top)) || { name: top || 'Kategorisiz', key: key(top), count: 0, tax: String(config.defaultTax) };
                g.count++;
                groups.set(key(top), g);
            }
            this.summary = s;
            this.problems = problems;
            this.warnings = warnings;
            this.taxGroups = [...groups.values()].sort((a, b) => b.count - a.count);
            this.preview = products.filter((p) => p.status === 'new' && p.errors.length === 0).slice(0, 50).map((p) => ({
                path: p.path,
                name: p.name,
                category: p.categories.join(' › '),
                brand: p.brand || '—',
                price: p.price,
                options: p.options.map((o) => o.value).join(', '),
                images: p.images.length + p.options.reduce((n, o) => n + o.images.length, 0),
            }));
        },

        setTax(event) {
            const group = this.taxGroups.find((g) => g.key === event.target.dataset.group);
            if (group) {
                group.tax = event.target.value;
            }
        },

        canStart() {
            return this.phase === 'review' && this.summary.new > 0;
        },

        async start() {
            if (!this.canStart()) {
                return;
            }
            this.phase = 'uploading';
            this.paused = false;
            this.fatal = '';
            this.failures = [];
            const queue = products.filter((p) => p.status === 'new' && p.errors.length === 0);
            this.progress = { total: queue.length, done: 0, created: 0, skipped: 0, failed: 0 };
            this.message = 'Kategori ve markalar hazırlanıyor…';
            try {
                await this.matchCatalog(true);
            } catch (e) {
                this.fatal = e.message || 'Kategori ve markalar oluşturulamadı.';
                this.phase = 'review';
                return;
            }
            this.message = '';
            startedAt = Date.now();
            const taxes = new Map(this.taxGroups.map((g) => [g.key, g.tax]));

            let next = 0;
            const worker = async () => {
                while (next < queue.length && !this.fatal) {
                    const item = queue[next++];
                    try {
                        const created = await this.upload(item, taxes.get(key(item.categories[0] || '')));
                        this.progress[created ? 'created' : 'skipped']++;
                    } catch (e) {
                        if (e.fatal) {
                            this.fatal = e.message;
                            break;
                        }
                        this.progress.failed++;
                        if (this.failures.length < MAX_SHOWN) {
                            this.failures.push({ path: item.path, message: e.message });
                        }
                    }
                    this.progress.done++;
                    this.updateEta();
                }
            };
            await Promise.all(Array.from({ length: PARALLEL }, worker));
            this.phase = 'done';
        },

        togglePause() {
            this.paused = !this.paused;
        },

        updateEta() {
            const { done, total } = this.progress;
            const left = total - done;
            if (done < 3 || left === 0) {
                this.eta = '';
                return;
            }
            const seconds = Math.round(((Date.now() - startedAt) / done) * left / 1000);
            this.eta = seconds < 60 ? 'yaklaşık ' + seconds + ' sn kaldı' : 'yaklaşık ' + Math.ceil(seconds / 60) + ' dk kaldı';
        },

        percent() {
            return this.progress.total ? Math.floor((this.progress.done / this.progress.total) * 100) : 0;
        },

        barStyle() {
            return 'width: ' + this.percent() + '%';
        },

        /** Bir ürünü gönderir; true: eklendi, false: zaten vardı. */
        async upload(item, tax) {
            const description = await readDescription(item.description);
            const categoryId = item.categories.length ? categoryIds.get(item.categories.map(key).join('>')) : null;
            const brandId = item.brand ? brandIds.get(key(item.brand)) : null;
            const common = { name: item.name, description, category_id: categoryId || '', brand_id: brandId || '' };

            if (config.mode === 'store') {
                const first = item.options[0] || null;
                const fields = {
                    ...common,
                    variant_name: item.optionName || '',
                    is_published: this.activate ? '1' : '0',
                    sku: first ? first.sku : item.sku,
                    barcode: (first ? first.barcode : item.barcode) || '',
                    option_value: first ? first.value : '',
                    price: (first && first.price) || item.price,
                    vat_rate: tax,
                    stock: (first ? first.stock : item.stock) || '',
                };
                item.options.slice(1).forEach((o, i) => {
                    fields['options[' + i + '][option_value]'] = o.value;
                    fields['options[' + i + '][sku]'] = o.sku;
                    fields['options[' + i + '][barcode]'] = o.barcode || '';
                    fields['options[' + i + '][price]'] = o.price || item.price;
                    fields['options[' + i + '][stock]'] = o.stock || '';
                });
                const images = [...item.images, ...item.options.flatMap((o) => o.images)].slice(0, config.maxImages);

                return (await this.send(fields, images)).status === 'created';
            }

            const b2b = { ...common, list_price: item.price, tax_rate_id: tax, price_includes_tax: this.includesTax ? '1' : '0', is_active: this.activate ? '1' : '0' };
            if (item.optionName === null) {
                const fields = { ...b2b, sku: item.sku, barcode: item.barcode || '', stock: item.stock || '' };

                return (await this.send(fields, item.images.slice(0, config.maxImages))).status === 'created';
            }

            // B2B seçenekli ürün: her seçenek kendi kodu, stoğu ve resimleri olan ayrı üründür; ilki ana üründür.
            let created = false;
            for (const [i, o] of item.options.entries()) {
                if (o.status === 'exists') {
                    continue;
                }
                const fields = {
                    ...b2b,
                    list_price: o.price || item.price,
                    sku: o.sku,
                    barcode: o.barcode || '',
                    stock: o.stock || '',
                    option_name: item.optionName,
                    option_value: o.value,
                    parent_sku: i === 0 ? '' : item.options[0].sku,
                };
                const images = (o.images.length ? o.images : item.images).slice(0, config.maxImages);
                try {
                    created = (await this.send(fields, images)).status === 'created' || created;
                } catch (e) {
                    if (i === 0 || e.fatal) {
                        throw e;
                    }
                    throw new Error('"' + o.value + '" seçeneği eklenemedi: ' + e.message + ' Diğer seçenekler eklendi; klasörü yeniden bıraktığınızda yalnızca eksikler eklenir.');
                }
                o.status = 'exists';
            }

            return created;
        },

        async send(fields, images) {
            while (this.paused && !this.fatal) {
                await sleep(400);
            }
            const files = [];
            for (const image of images) {
                const file = await prepareImage(image);
                if (file) {
                    files.push(file);
                }
            }
            const body = new FormData();
            Object.entries(fields).forEach(([name, value]) => body.append(name, value === null ? '' : String(value)));
            files.forEach((file) => body.append('images[]', file, file.name));

            return this.request(config.urls.store, body);
        },

        post(url, data) {
            return this.request(url, JSON.stringify(data), { 'Content-Type': 'application/json' });
        },

        /** Geçici hatalar (ağ, 5xx, 429) birkaç kez yeniden denenir; 419/401/403 yüklemeyi durdurur. */
        async request(url, body, headers) {
            const fatal = (message) => Object.assign(new Error(message), { fatal: true });
            for (let attempt = 0; ; attempt++) {
                let res;
                try {
                    res = await fetch(url, {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest', ...(headers || {}) },
                    });
                } catch (e) {
                    if (attempt < 4) {
                        await sleep(2000 * (attempt + 1));
                        continue;
                    }
                    throw new Error('Sunucuya ulaşılamadı. İnternet bağlantınızı denetleyin.');
                }
                if (res.status === 429) {
                    await sleep((Number(res.headers.get('Retry-After')) || 20) * 1000);
                    attempt--;
                    continue;
                }
                if (res.status >= 500 && attempt < 3) {
                    await sleep(3000 * (attempt + 1));
                    continue;
                }
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    return data;
                }
                if (res.status === 419 || res.status === 401) {
                    throw fatal('Oturumunuzun süresi doldu. Sayfayı yenileyip giriş yapın, sonra aynı klasörü yeniden bırakın: eklenen ürünler atlanır, kalanlar eklenir.');
                }
                if (res.status === 403) {
                    throw fatal('Bu işlem için yetkiniz yok.');
                }
                if (res.status === 413) {
                    throw new Error('Ürünün resimleri çok büyük.');
                }
                const first = data.errors ? Object.values(data.errors)[0] : null;
                throw new Error((first && first[0]) || data.message || 'Ürün eklenemedi (hata ' + res.status + ').');
            }
        },

        failureText() {
            return this.failures.map((f) => f.path + ' → ' + f.message).join('\n');
        },

        copyFailures() {
            navigator.clipboard.writeText(this.failureText());
            this.message = 'Hata listesi panoya kopyalandı.';
        },
    };
}
