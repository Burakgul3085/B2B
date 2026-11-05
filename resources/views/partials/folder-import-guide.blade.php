{{-- Klasör düzeni özeti (klasörden ürün ekleme ekranı). $maxDepth: kategori seviyesi, $maxImages: ürün başına resim --}}
<details class="rounded-xl border border-line bg-white shadow-xs" open>
    <summary class="cursor-pointer px-5 py-4 text-sm font-semibold">Klasör nasıl hazırlanır?</summary>
    <div class="grid gap-6 border-t border-line px-5 py-5 text-sm lg:grid-cols-2">
        <div>
            <pre class="overflow-x-auto rounded-lg bg-slate-50 p-4 font-mono text-xs leading-6 text-ink">Ürünler/
  Boyalar/                              ← kategori
    Pastel Boya/                        ← alt kategori (isteğe bağlı)
      Faber-Castell/                    ← marka
        Pastel Boya 24 Renk ; 49,90 ; 8690826123456/
          1.jpg  2.jpg  aciklama.txt
        Pastel Boya 12 Renk ; 29,90 ; ; 100/
          1.jpg
  Kalemler/
    Pilot/
      Jel Kalem [Renk] ; 15,00/         ← seçenekli ürün
        Mavi/  1.jpg
        Kırmızı ; 16,00 ; 8690000000123 ; 50/  1.jpg
    Markasız/
      Kurşun Kalem ; 3,50/  1.jpg</pre>
        </div>
        <ul class="list-disc space-y-2 pl-5 text-muted">
            <li><b class="text-ink">Ürün klasörünün adı:</b> <code>Ürün adı ; Fiyat ; Barkod ; Stok</code>. Ad ve fiyat zorunlu; barkod ve stok boş kalabilir (<code>Ad ; 29,90 ; ; 100</code>). Fiyatta kuruş virgülle yazılır.</li>
            <li><b class="text-ink">Ürünün üstündeki klasör markadır</b>, onun üstündekiler kategoridir (en fazla {{ $maxDepth }} seviye). Markası olmayan ürünler <code>Markasız</code> klasörüne konur.</li>
            <li><b class="text-ink">Resimler</b> ürün klasörünün içindedir: JPG, PNG veya WEBP, ürün başına en fazla {{ $maxImages }}. Ad sırasıyla dizilir; <code>1.jpg</code> kapak resmidir. Büyük resimler kendiliğinden küçültülür.</li>
            <li><b class="text-ink">Açıklama</b> isteğe bağlıdır: ürün klasörüne <code>aciklama.txt</code> koyun.</li>
            <li><b class="text-ink">Seçenekli ürün</b> (renk, boyut): ürün klasörünün adına köşeli parantezle seçenek adını yazın (<code>[Renk]</code>); her seçenek içinde ayrı bir klasördür: <code>Değer ; Fiyat ; Barkod ; Stok</code>. Fiyatı boş bırakılan seçenek ürünün fiyatını alır.</li>
            <li>En dıştaki <code>Ürünler</code> klasörü kategori sayılmaz. Yeni kategori ve markalar onayınızla oluşturulur; sistemde olan ürünler (aynı barkod veya aynı kategori + marka + ad) atlanır.</li>
        </ul>
    </div>
</details>
