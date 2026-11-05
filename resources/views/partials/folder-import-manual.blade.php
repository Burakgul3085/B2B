{{--
    Klasörden ürün ekleme — ayrıntılı kullanıcı kılavuzu (B2B ve mağaza ortak). Yazdırılabilir.
    $mode: 'b2b' | 'store', $maxImages: ürün başına resim, $importUrl: klasörden ekleme sayfası
--}}
@php
    $b2b = $mode === 'b2b';
    $tree = fn (string $text) => '<pre class="my-3 overflow-x-auto rounded-lg bg-slate-50 p-4 font-mono text-xs leading-6 text-ink print:whitespace-pre-wrap">'.e($text).'</pre>';
    $h2 = 'mt-10 mb-3 scroll-mt-6 text-lg font-bold text-ink first:mt-0';
    $h3 = 'mt-6 mb-2 text-base font-semibold text-ink';
    $ok = 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900';
    $warn = 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900';
    $sections = [
        'genel' => 'Kısaca nasıl çalışır?',
        'duzen' => '1. Klasör düzeni',
        'kategori' => '2. Kategori ve alt kategoriler',
        'marka' => '3. Marka klasörü',
        'urun' => '4. Ürün klasörünün adı',
        'fiyat' => '5. Fiyat nasıl yazılır?',
        'resim' => '6. Resimler',
        'aciklama' => '7. Ürün açıklaması',
        'secenek' => '8. Seçenekli ürünler (renk, boyut…)',
        'yukleme' => '9. Yükleme adım adım',
        'ozet' => '10. Özet ekranı',
        'parca' => '11. Çok sayıda ürün ve parça parça yükleme',
        'sonra' => '12. Yükledikten sonra',
        'hatalar' => '13. Sık yapılan hatalar',
        'sorular' => '14. Sık sorulan sorular',
        'kontrol' => 'Yüklemeden önce kontrol listesi',
    ];
@endphp
<div class="grid gap-6 lg:grid-cols-4">
    <nav aria-label="Kılavuz bölümleri" class="lg:sticky lg:top-6 lg:self-start print:hidden">
        <div class="rounded-xl border border-line bg-white p-4 text-sm shadow-xs">
            <p class="mb-2 text-xs font-semibold tracking-wider text-muted uppercase">Bölümler</p>
            <ol class="space-y-1">
                @foreach ($sections as $id => $title)
                    <li><a href="#{{ $id }}" class="text-ink/80 hover:text-primary hover:underline">{{ $title }}</a></li>
                @endforeach
            </ol>
            <div class="mt-4 flex flex-col gap-2 border-t border-line pt-4">
                <x-button variant="secondary" size="sm" icon="printer" type="button" data-print>Yazdır / PDF olarak kaydet</x-button>
                <x-button size="sm" icon="arrow-right" :href="$importUrl">Klasörden ekle sayfasına dön</x-button>
            </div>
        </div>
    </nav>

    <article class="rounded-xl border border-line bg-white p-6 text-sm leading-7 text-ink/90 shadow-xs lg:col-span-3 print:border-0 print:p-0 print:shadow-none">

        {{-- ================= GENEL ================= --}}
        <h2 id="genel" class="{{ $h2 }}">Kısaca nasıl çalışır?</h2>
        <p>Ürünlerinizi bilgisayarınızda <b>klasörlere</b> yerleştirirsiniz. Klasörlerin <b>adları</b> sisteme ürünün kategorisini, markasını, adını, fiyatını, barkodunu ve stoğunu söyler; klasörün <b>içindeki resimler</b> ürünün resimleri olur. Hazırladığınız klasörü {{ $b2b ? 'Ürünler' : 'Katalog › Ürünler' }} › <b>Klasörden ekle</b> sayfasına sürükleyip bırakırsınız. Sistem önce her şeyi okur ve size bir <b>özet</b> gösterir; siz onaylamadan hiçbir ürün eklenmez.</p>
        <ul class="mt-3 list-disc space-y-1 pl-5">
            <li>Excel dosyası, şablon veya kod yazmanız gerekmez; yalnızca klasör açıp adlandırırsınız.</li>
            <li>10 ürün de olur, 10.000 ürün de. Hepsini bir kerede de bırakabilirsiniz, kategori kategori de.</li>
            <li>Sistemde zaten olan ürünler <b>hiçbir zaman değiştirilmez ve iki kez eklenmez</b>. Aynı klasörü yeniden bıraktığınızda yalnızca eksik olanlar eklenir.</li>
            <li>Kategori ve markalar sistemde yoksa siz onayladığınızda kendiliğinden açılır.</li>
        </ul>
        @if ($b2b)
            <p class="mt-3">Bu kılavuz <b>B2B toptancı paneli</b> içindir. Mağaza yönetim panelinin kılavuzu ayrıdır; iki panel birbirinden bağımsızdır, aynı klasörü ikisine ayrı ayrı yükleyebilirsiniz.</p>
        @else
            <p class="mt-3">Bu kılavuz <b>mağaza yönetim paneli</b> içindir. B2B toptancı panelinin kılavuzu ayrıdır; iki panel birbirinden bağımsızdır, aynı klasörü ikisine ayrı ayrı yükleyebilirsiniz.</p>
        @endif

        {{-- ================= DÜZEN ================= --}}
        <h2 id="duzen" class="{{ $h2 }}">1. Klasör düzeni</h2>
        <p>Masaüstünüzde (veya istediğiniz yerde) <b>Ürünler</b> adında bir klasör açın. Bu en dıştaki kutudur; kategori sayılmaz. İçine şu sırayla klasörler açılır:</p>
        {!! $tree("Ürünler/                         ← en dış kutu (kategori sayılmaz)\n   Kategori/                     ← ana kategori\n      Alt kategori/              ← isteğe bağlı; istediğiniz kadar iç içe\n         Marka/                  ← ürünün markası\n            Ürün adı ; Fiyat ; Barkod ; Stok/    ← ürün klasörü\n               1.jpg             ← kapak resmi\n               2.jpg\n               aciklama.txt      ← isteğe bağlı açıklama") !!}
        <div class="{{ $ok }}"><b>Altın kural:</b> ürün klasörünün <b>hemen üstündeki</b> klasör <b>markadır</b>. Markanın üstündeki klasörlerin <b>hepsi kategoridir</b> (en üstteki ana kategori, altındakiler sırasıyla alt kategoriler).</div>

        <h3 class="{{ $h3 }}">Tam bir örnek</h3>
        {!! $tree("Ürünler/\n   Boyalar/\n      Pastel Boya/\n         Faber-Castell/\n            Çantalı Pastel Boya 24 Renk ; 49,90 ; 8690826123456 ; 120/\n               1.jpg\n               2.jpg\n               aciklama.txt\n            Pastel Boya 12 Renk ; 29,90/\n               1.jpg\n      Sulu Boya/\n         Faber-Castell/\n            Sulu Boya 12 Renk ; 39,90 ; ; 80/\n               1.jpg\n   Kalemler/\n      Pilot/\n         Jel Kalem 0.7 [Renk] ; 15,00/\n            Mavi/\n               1.jpg\n            Kırmızı ; 16,00 ; 8690000000123 ; 50/\n               1.jpg\n      Markasız/\n         Kurşun Kalem HB ; 3,50 ; ; 500/\n            1.jpg\n   Okul/\n      Defterler/\n         Spiralli/\n            A4/\n               Gıpta/\n                  Spiralli Defter A4 80 Yaprak Kareli ; 89,90 ; 8690000000100 ; 200/\n                     1.jpg") !!}
        <p>Bu örnekte:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li><i>Çantalı Pastel Boya 24 Renk</i> → kategori <b>Boyalar › Pastel Boya</b>, marka <b>Faber-Castell</b>, fiyat 49,90, barkod 8690826123456, stok 120.</li>
            <li><i>Jel Kalem 0.7</i> → kategori <b>Kalemler</b> (alt kategorisi yok), marka <b>Pilot</b>, iki seçenekli: Mavi ve Kırmızı.</li>
            <li><i>Kurşun Kalem HB</i> → kategori <b>Kalemler</b>, markasız.</li>
            <li><i>Spiralli Defter</i> → kategori <b>Okul › Defterler › Spiralli › A4</b> (4 seviye), marka <b>Gıpta</b>.</li>
        </ul>

        <h3 class="{{ $h3 }}">Klasör nasıl açılır ve adı nasıl değiştirilir?</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li><b>Windows:</b> boş bir yere sağ tıklayın › <i>Yeni</i> › <i>Klasör</i>. Adını değiştirmek için klasörü seçip <kbd class="rounded border border-line bg-slate-50 px-1">F2</kbd> tuşuna basın, yazın ve <kbd class="rounded border border-line bg-slate-50 px-1">Enter</kbd>'a basın.</li>
            <li><b>Mac:</b> Finder'da sağ tıklayın › <i>Yeni Klasör</i>. Adını değiştirmek için klasörü seçip <kbd class="rounded border border-line bg-slate-50 px-1">Return</kbd> tuşuna basın.</li>
            <li>Resimleri ürün klasörüne sürükleyerek veya kopyala-yapıştır ile koyabilirsiniz.</li>
        </ul>
        <div class="{{ $warn }} mt-3">Windows klasör adında şu işaretlere izin vermez: <code>\ / : * ? " &lt; &gt; |</code>. Bunları ürün adında kullanmayın; ör. “A4/A5” yerine “A4-A5” yazın. Klasör adının sonunda nokta veya boşluk bırakmayın.</div>

        {{-- ================= KATEGORİ ================= --}}
        <h2 id="kategori" class="{{ $h2 }}">2. Kategori ve alt kategoriler</h2>
        <p>Kategori sayısında ve derinliğinde sınır yoktur: ana kategorinin altına istediğiniz kadar alt kategori açabilirsiniz. Ürünün hangi seviyede duracağına siz karar verirsiniz.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li><b>Alt kategori istemiyorsanız</b> marka klasörünü doğrudan ana kategorinin içine koyun: <code>Kalemler/Pilot/Ürün</code> → ürün doğrudan <b>Kalemler</b> kategorisine girer.</li>
            <li><b>Bir alt kategori:</b> <code>Boyalar/Pastel Boya/Faber-Castell/Ürün</code> → <b>Boyalar › Pastel Boya</b>.</li>
            <li><b>Daha fazla:</b> <code>Okul/Defterler/Spiralli/A4/Gıpta/Ürün</code> → <b>Okul › Defterler › Spiralli › A4</b>.</li>
            <li>Aynı kategoride hem doğrudan ürün hem alt kategori olabilir: <code>Kalemler/Pilot/…</code> ve <code>Kalemler/Tükenmez/Bic/…</code> birlikte kullanılabilir.</li>
        </ul>
        <h3 class="{{ $h3 }}">Sistemde olan kategoriler</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li>Klasör adı sistemdeki bir kategorinin adıyla <b>aynıysa</b> o kategori kullanılır; yenisi açılmaz. Büyük/küçük harf fark etmez: “boyalar” = “Boyalar”.</li>
            <li>Eşleşme <b>yol</b> ile yapılır: <code>Okul/Defterler</code> ile <code>Ofis/Defterler</code> iki ayrı kategoridir.</li>
            <li>Sistemde olmayan kategoriler özet ekranında “<i>yeni kategori açılacak</i>” olarak sayılır ve yalnızca siz <b>ekle</b> düğmesine bastığınızda açılır.</li>
            <li>Harf hatasına dikkat: “Kalemler” ile “Kalemlr” iki ayrı kategori olur. Sistemdeki adı {{ $b2b ? 'Ürünler › Kategoriler' : 'Katalog › Kategoriler' }} sayfasından kontrol edip klasöre aynısını yazın.</li>
        </ul>
        <div class="{{ $warn }} mt-3"><b>Kategori ve marka klasörlerinin içine doğrudan resim koymayın.</b> İçinde resim olan klasör ürün sayılır. Resimler yalnızca ürün klasörlerinin (ve seçenek klasörlerinin) içinde olur.</div>

        {{-- ================= MARKA ================= --}}
        <h2 id="marka" class="{{ $h2 }}">3. Marka klasörü</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Ürün klasörlerini markalarına göre gruplayın: <code>Pilot/</code>, <code>Faber-Castell/</code>, <code>Gıpta/</code>…</li>
            <li>Markası olmayan ürünler için marka klasörünün adını <b>Markasız</b> yapın (“Marka yok” veya yalnızca “-” de olur). Bu ürünler markasız eklenir.</li>
            <li>Sistemde olan markalar adıyla eşleşir (büyük/küçük harf fark etmez); olmayanlar onayınızla açılır.</li>
            <li>Marka klasörü <b>atlanamaz</b>: <code>Kalemler/Ürün</code> şeklinde, arada marka olmadan konan ürün hatalı sayılır. Markasızsa araya <code>Markasız</code> klasörünü koyun.</li>
        </ul>

        {{-- ================= ÜRÜN ================= --}}
        <h2 id="urun" class="{{ $h2 }}">4. Ürün klasörünün adı</h2>
        <p>Ürün klasörünün adı noktalı virgülle (<code>;</code>) ayrılan en fazla dört bölümden oluşur:</p>
        <p class="my-3 rounded-lg bg-slate-50 p-4 text-center font-mono text-base font-semibold text-ink">Ürün adı ; Fiyat ; Barkod ; Stok</p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b border-line"><th class="py-2 pr-4">Bölüm</th><th class="py-2 pr-4">Zorunlu mu?</th><th class="py-2">Açıklama</th></tr></thead>
                <tbody class="divide-y divide-line">
                    <tr><td class="py-2 pr-4 font-medium">Ürün adı</td><td class="py-2 pr-4">Evet</td><td class="py-2">{{ $b2b ? 'Bayilerin' : 'Müşterilerin' }} göreceği ad. En fazla {{ $b2b ? 255 : 200 }} karakter.</td></tr>
                    <tr><td class="py-2 pr-4 font-medium">Fiyat</td><td class="py-2 pr-4">Evet</td><td class="py-2">{{ $b2b ? 'Bayi fiyatı.' : 'KDV dahil satış fiyatı.' }} Yazım kuralları bir sonraki bölümde.</td></tr>
                    <tr><td class="py-2 pr-4 font-medium">Barkod</td><td class="py-2 pr-4">Hayır</td><td class="py-2">Yalnızca harf, rakam ve <code>-</code>; en fazla 64 karakter. Varsa mutlaka yazın (aşağıda nedeni var).</td></tr>
                    <tr><td class="py-2 pr-4 font-medium">Stok</td><td class="py-2 pr-4">Hayır</td><td class="py-2">Başlangıç stoğu; 0 veya daha büyük tam sayı (ör. <code>120</code>). Boşsa stok girilmez.</td></tr>
                </tbody>
            </table>
        </div>
        <h3 class="{{ $h3 }}">Doğru örnekler</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li><code>Spiralli Defter A4 80 Yaprak ; 89,90</code> → yalnızca ad ve fiyat.</li>
            <li><code>Spiralli Defter A4 80 Yaprak ; 89,90 ; 8690000000100</code> → barkodlu.</li>
            <li><code>Spiralli Defter A4 80 Yaprak ; 89,90 ; ; 200</code> → barkodsuz ama stoklu. Aradaki <code>; ;</code> “barkod boş” demektir; stok yazacaksanız barkodun yerini boş bırakmanız gerekir.</li>
            <li><code>Spiralli Defter A4 80 Yaprak ; 89,90 ; 8690000000100 ; 200</code> → hepsi.</li>
            <li>Boşluklar önemli değildir: <code>Defter;89,90;8690000000100;200</code> da aynı şekilde okunur.</li>
        </ul>
        <h3 class="{{ $h3 }}">Yanlış örnekler</h3>
        <ul class="list-disc space-y-1 pl-5">
            <li><code>Spiralli Defter A4</code> → fiyat yok; ürün <b>eklenmez</b>, hata listesinde görünür.</li>
            <li><code>Spiralli Defter ; 89,90 ; 200</code> → 200 barkod sanılır. Stok için <code>; ; 200</code> yazın.</li>
            <li><code>Defter ; 89,90 ; 8690000000100 ; 200 ; mavi</code> → beşinci bölüm olamaz.</li>
            <li><code>Defter - 89,90</code> → ayırıcı tire değil, noktalı virgüldür.</li>
        </ul>
        <h3 class="{{ $h3 }}">Barkod neden önemli?</h3>
        <p>Sistem her ürüne bir <b>ürün kodu</b> verir. Barkod yazdıysanız ürün kodu barkodun kendisidir. Barkod yoksa sistem kategori + marka + ürün adından <code>YK-…</code> ile başlayan sabit bir kod üretir. Aynı klasör yeniden yüklendiğinde ürünler bu kodla tanınır ve tekrar eklenmez.</p>
        <div class="{{ $warn }} mt-3">Barkodsuz bir ürünü yükledikten sonra klasördeki <b>adını, kategorisini veya markasını</b> değiştirirseniz, sistem onu <b>yeni bir ürün</b> sanar ve bir daha ekler. Barkodlu ürünlerde bu sorun olmaz. Bu yüzden barkodu olan ürünlerde barkodu mutlaka yazın.</div>

        {{-- ================= FİYAT ================= --}}
        <h2 id="fiyat" class="{{ $h2 }}">5. Fiyat nasıl yazılır?</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b border-line"><th class="py-2 pr-4">Yazdığınız</th><th class="py-2">Sistemin okuduğu</th></tr></thead>
                <tbody class="divide-y divide-line font-mono">
                    <tr><td class="py-1.5 pr-4">49,90</td><td class="py-1.5">49,90 ₺</td></tr>
                    <tr><td class="py-1.5 pr-4">49</td><td class="py-1.5">49,00 ₺</td></tr>
                    <tr><td class="py-1.5 pr-4">49,90 TL &nbsp;/&nbsp; 49,90 ₺</td><td class="py-1.5">49,90 ₺</td></tr>
                    <tr><td class="py-1.5 pr-4">1.250,00</td><td class="py-1.5">1.250,00 ₺</td></tr>
                    <tr><td class="py-1.5 pr-4">1250,5</td><td class="py-1.5">1.250,50 ₺</td></tr>
                    <tr><td class="py-1.5 pr-4">49.90</td><td class="py-1.5">49,90 ₺</td></tr>
                    <tr class="text-red-800"><td class="py-1.5 pr-4">1.250</td><td class="py-1.5">1.250,00 ₺ (bin iki yüz elli; “1,25” değil!)</td></tr>
                    <tr class="text-red-800"><td class="py-1.5 pr-4">0 &nbsp;/&nbsp; abc &nbsp;/&nbsp; 49,9,0</td><td class="py-1.5">okunamaz → ürün eklenmez</td></tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3"><b>En güvenlisi:</b> kuruşu her zaman virgülle yazın (<code>49,90</code>) ve binlik ayırıcı kullanmayın (<code>1250,00</code>).</p>
        @if ($b2b)
            <p class="mt-2">Kuruştan sonra en fazla 4 hane yazılabilir (ör. <code>0,4575</code>).</p>
            <div class="{{ $ok }} mt-3"><b>KDV:</b> Klasördeki fiyat <b>bayi fiyatıdır</b>. Özet ekranında “<i>Klasördeki fiyatlar KDV dahil</i>” kutusunu işaretlerseniz fiyatlar KDV dahil, işaretlemezseniz KDV hariç sayılır. KDV oranını özet ekranında her ana kategori için ayrı seçersiniz (Ayarlar › KDV Oranları'nda tanımlı oranlar arasından).</div>
        @else
            <p class="mt-2">Kuruştan sonra en fazla 2 hane yazılabilir.</p>
            <div class="{{ $ok }} mt-3"><b>KDV:</b> Mağazada klasördeki fiyat her zaman müşterinin ödeyeceği <b>KDV dahil satış fiyatıdır</b>. KDV oranını (%0, %1, %10, %20) özet ekranında her ana kategori için ayrı seçersiniz.</div>
        @endif

        {{-- ================= RESİM ================= --}}
        <h2 id="resim" class="{{ $h2 }}">6. Resimler</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Resimleri ürün klasörünün <b>içine</b> koyun. Biçim: <b>JPG, JPEG, PNG veya WEBP</b>.</li>
            <li>Bir ürüne en fazla <b>{{ $maxImages }} resim</b> eklenir; fazlası atlanır ve özet ekranında uyarı çıkar.</li>
            <li>Resimler <b>ad sırasıyla</b> dizilir, ilki <b>kapak resmi</b> olur. En kolayı <code>1.jpg</code>, <code>2.jpg</code>, <code>3.jpg</code> diye adlandırmaktır. Sayılar doğru sıralanır: <code>2.jpg</code>, <code>10.jpg</code>'den önce gelir.</li>
            <li>Resmin adı önemli değildir (ör. <code>IMG_4521.jpg</code> da olur); yalnızca sıralamayı belirler.</li>
            <li>Büyük resimleri küçültmenize gerek yok: yüklerken sizin bilgisayarınızda uygun boyuta (en uzun kenar 2000 piksel) kendiliğinden küçültülür.</li>
            <li>Resmi olmayan ürün de eklenir (özette “Resim yok” uyarısı çıkar); resmi sonradan ürün ekranından ekleyebilirsiniz.</li>
            <li>En iyi sonuç için: beyaz veya sade arka plan, kare (1:1) veya yatay, ürün ortada ve net.</li>
        </ul>
        <div class="{{ $warn }} mt-3"><b>iPhone resimleri:</b> iPhone'dan aktarılan <code>.HEIC</code> resimler desteklenmez ve atlanır. iPhone'da <i>Ayarlar › Kamera › Biçimler › En Uyumlu</i> seçeneğini açın ya da resimleri JPG olarak dışa aktarın. GIF, BMP, TIFF ve AVIF de desteklenmez.</div>

        {{-- ================= AÇIKLAMA ================= --}}
        <h2 id="aciklama" class="{{ $h2 }}">7. Ürün açıklaması (isteğe bağlı)</h2>
        <ol class="list-decimal space-y-1 pl-5">
            <li>Ürün klasörünün içinde sağ tıklayın › <i>Yeni</i> › <i>Metin Belgesi</i> (Mac'te TextEdit ile düz metin olarak kaydedin).</li>
            <li>Dosyanın adını tam olarak <code>aciklama.txt</code> yapın (“açıklama.txt” de olur).</li>
            <li>Açın, ürün açıklamasını yazın, kaydedin. Türkçe karakterler sorunsuz okunur; satır boşlukları korunur.</li>
        </ol>
        <p class="mt-2">Açıklama en fazla {{ $b2b ? '5.000' : '20.000' }} karakter olabilir. Word (.docx) dosyası okunmaz; yalnızca .txt.</p>
        <div class="{{ $warn }} mt-3">Windows'ta dosya uzantıları gizliyse dosya yanlışlıkla <code>aciklama.txt.txt</code> olabilir ve okunmaz. Dosya Gezgini'nde <i>Görünüm › Göster › Dosya adı uzantıları</i>'nı açıp kontrol edin.</div>

        {{-- ================= SEÇENEK ================= --}}
        <h2 id="secenek" class="{{ $h2 }}">8. Seçenekli ürünler (renk, boyut…)</h2>
        <p>Aynı ürünün renk, boyut, desen gibi seçenekleri varsa:</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li>Ürün klasörünün adına seçenek adını <b>köşeli parantezle</b> yazın: <code>Jel Kalem 0.7 [Renk] ; 15,00</code></li>
            <li>Bu klasörün içine her seçenek için <b>ayrı bir klasör</b> açın. Seçenek klasörünün adı: <code>Değer ; Fiyat ; Barkod ; Stok</code></li>
            <li>Her seçeneğin resmini kendi klasörüne koyun.</li>
        </ol>
        {!! $tree("Jel Kalem 0.7 [Renk] ; 15,00/\n   Mavi/                                 ← fiyatı 15,00 (üründen)\n      1.jpg\n   Siyah ; ; 8690000000124 ; 40/         ← fiyatı 15,00, barkodlu, stok 40\n      1.jpg\n   Kırmızı ; 16,00 ; 8690000000123 ; 50/  ← kendi fiyatı 16,00\n      1.jpg") !!}
        <ul class="list-disc space-y-1 pl-5">
            <li>Seçenek klasöründe fiyat boşsa ürün klasöründeki fiyat kullanılır. Ürün klasöründe de fiyat yoksa her seçeneğe fiyat yazılmalıdır.</li>
            <li>Seçenekli üründe <b>barkod ve stok seçenek klasörlerine</b> yazılır; ürün klasörüne yazılanlar dikkate alınmaz.</li>
            <li>Seçenek adı (köşeli parantezin içi) en fazla {{ $b2b ? 40 : 60 }}, seçenek değeri en fazla {{ $b2b ? 60 : 80 }} karakter olabilir. Bir üründe en fazla 30 seçenek olur; aynı değer iki kez yazılamaz.</li>
            <li>Örnek seçenek adları: <code>[Renk]</code>, <code>[Boyut]</code>, <code>[Desen]</code>, <code>[Uç kalınlığı]</code>.</li>
        </ul>
        @if ($b2b)
            <div class="{{ $ok }} mt-3"><b>B2B'de:</b> her seçenek kendi ürün kodu, fiyatı ve stoğu olan ayrı bir ürün olarak eklenir ve hepsi birbirine bağlanır; bayi ürün sayfasında seçenekler arasında geçiş yapar. Seçenek klasöründe resim yoksa ürün klasöründeki resimler o seçeneğe kullanılır.</div>
        @else
            <div class="{{ $ok }} mt-3"><b>Mağazada:</b> seçenekler aynı ürünün varyantlarıdır (tek ürün sayfası, müşteri rengi seçer). Ürün klasöründeki ve seçenek klasörlerindeki tüm resimler sırayla ürünün resimleri olur (en fazla {{ $maxImages }}).</div>
        @endif
        <p class="mt-3">Seçeneği olmayan ürünün içinde alt klasör açmayın; açarsanız alt klasörler yok sayılır ve uyarı çıkar.</p>

        {{-- ================= YÜKLEME ================= --}}
        <h2 id="yukleme" class="{{ $h2 }}">9. Yükleme adım adım</h2>
        <ol class="list-decimal space-y-2 pl-5">
            <li><b>{{ $b2b ? 'Ürünler' : 'Katalog › Ürünler' }}</b> sayfasında sağ üstteki <b>Klasörden ekle</b> düğmesine basın.</li>
            <li><b>Ürünler</b> klasörünüzü sayfadaki kutunun üzerine sürükleyip bırakın ya da <b>Klasör seç</b> düğmesiyle seçin. (Tarayıcı “dosyaları yüklemek istiyor musunuz?” diye sorarsa <i>Yükle</i> deyin; bu yalnızca okumak içindir, henüz hiçbir şey eklenmez.)</li>
            <li>Sistem klasörü okur; binlerce üründe birkaç saniye sürebilir.</li>
            <li><b>Özet ekranını</b> inceleyin (bir sonraki bölüm).</li>
            <li>KDV oranlarını seçin, isterseniz kutuları işaretleyin ve <b>“… ürünü ekle”</b> düğmesine basın.</li>
            <li>Ürünler aynı anda dörder dörder eklenir; ekranda ilerleme ve <b>kalan süre</b> görünür. İsterseniz <b>Duraklat</b> ile durdurup <b>Devam et</b> ile sürdürebilirsiniz.</li>
            <li>Bitince kaç ürünün eklendiği, kaçının zaten var olduğu ve eklenemeyenler sebepleriyle listelenir. Listeyi <b>Kopyala</b> düğmesiyle alıp düzeltme yapabilirsiniz.</li>
        </ol>
        <div class="{{ $warn }} mt-3">Yükleme sürerken <b>sekmeyi kapatmayın</b>, sayfayı yenilemeyin ve bilgisayarı uyku moduna almayın. Yine de yarıda kalırsa endişelenmeyin: aynı klasörü yeniden bırakın; eklenenler atlanır, kalanlar eklenir.</div>

        {{-- ================= ÖZET ================= --}}
        <h2 id="ozet" class="{{ $h2 }}">10. Özet ekranı</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li><b>Eklenecek:</b> sistemde olmayan ve hatasız ürünler; düğmeye basınca bunlar eklenir.</li>
            <li><b>Zaten var:</b> aynı barkodlu veya aynı ürün koduyla sistemde olan ürünler; dokunulmaz, atlanır.</li>
            <li><b>Hatalı:</b> klasörde bir sorun olan ürünler (ör. “Fiyat yazılmamış”). Bunlar eklenmez; diğerleri eklenir. Her satırda klasörün yolu ve sorunun ne olduğu yazar.</li>
            <li><b>Uyarılar:</b> ürün yine eklenir, yalnızca bilgi verir (ör. “Resim yok”, “fazla resim atlandı”).</li>
            <li><b>Yeni kategori / yeni marka:</b> sistemde olmayan ve açılacak kategori ve marka sayısı. Beklemediğiniz bir sayı görürseniz (ör. harf hatası) önce klasörü düzeltin.</li>
            <li><b>Önizleme:</b> ilk ürünlerin adı, kategorisi, markası ve fiyatı; doğru okunmuş mu bakın.</li>
            <li><b>KDV oranı:</b> her ana kategori için bir oran seçilir.</li>
            @if ($b2b)
                <li><b>Klasördeki fiyatlar KDV dahil:</b> fiyatlarınız KDV dahilse işaretleyin.</li>
                <li><b>Ürünler hemen satışa açılsın:</b> işaretlemezseniz ürünler <b>kapalı</b> eklenir ve bayiler görmez; kontrol edip ürün listesinden toplu açarsınız. İlk yüklemede kapalı eklemenizi öneririz.</li>
            @else
                <li><b>Ürünler hemen yayına alınsın:</b> işaretlemezseniz ürünler <b>yayında değil</b> olarak eklenir ve müşteriler görmez; kontrol edip ürün listesinden yayına alırsınız. İlk yüklemede yayında değil eklemenizi öneririz.</li>
            @endif
        </ul>

        {{-- ================= PARÇA ================= --}}
        <h2 id="parca" class="{{ $h2 }}">11. Çok sayıda ürün ve parça parça yükleme</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Tek seferde bırakılabilecek ürün sayısında sınır yoktur; 10.000 ürün de olur. Süre çoğunlukla resimlerin boyutuna ve internet hızına bağlıdır.</li>
            <li><b>Parça parça:</b> ürünleri kategori kategori hazırlayabilir, biten kategorinin klasörünü (ör. <code>Ürünler/Boyalar</code>) tek başına bırakabilirsiniz. Ana kategori klasörünü doğrudan bırakmak da doğru çalışır.</li>
            <li>Sonradan bütün <code>Ürünler</code> klasörünü yeniden bırakırsanız önceden eklenenler “zaten var” sayılır, yalnızca yeniler eklenir.</li>
            <li>İlk denemenizi 5–10 ürünlük küçük bir klasörle yapın; sonucu kontrol edip sonra hepsini yükleyin.</li>
            <li>Klasörünüzü yedekleyin (ör. USB bellek veya bulut). Daha sonra yeni ürün eklerken aynı düzende devam edersiniz.</li>
        </ul>

        {{-- ================= SONRA ================= --}}
        <h2 id="sonra" class="{{ $h2 }}">12. Yükledikten sonra</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Ürünleri {{ $b2b ? 'Ürünler' : 'Katalog › Ürünler' }} listesinde kontrol edin; {{ $b2b ? 'satışa açın' : 'yayına alın' }}.</li>
            <li><b>Klasörden yükleme var olan ürünü değiştirmez.</b> Fiyat, stok, ad veya resim değişikliği için ürün ekranını{{ $b2b ? ', “Toplu fiyat” sayfasını' : '' }} veya stok sayfasını kullanın.</li>
            @if ($b2b)
                <li>Stok, varsayılan sevk deponuza “açılış stoğu” olarak girilir. Satış şekli “tek tek” olur; koli satışı, en az sipariş miktarı ve kritik stok seviyesini ürün ekranından ayarlarsınız.</li>
            @else
                <li>Stok, “açılış stoğu” olarak girilir. Kritik stok seviyesi, SEO bilgileri ve kampanyaları ürün ekranından ayarlarsınız.</li>
            @endif
            <li>Kategorilerin sırasını, adını ve yerini {{ $b2b ? 'Ürünler › Kategoriler' : 'Katalog › Kategoriler' }} sayfasından düzenleyebilirsiniz; ürünler kategorileriyle birlikte taşınır.</li>
        </ul>

        {{-- ================= HATALAR ================= --}}
        <h2 id="hatalar" class="{{ $h2 }}">13. Sık yapılan hatalar</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b border-line"><th class="py-2 pr-4">Ekrandaki mesaj</th><th class="py-2">Ne yapmalı?</th></tr></thead>
                <tbody class="divide-y divide-line align-top">
                    <tr><td class="py-2 pr-4">Fiyat yazılmamış</td><td class="py-2">Klasör adına <code>; 29,90</code> gibi fiyatı ekleyin.</td></tr>
                    <tr><td class="py-2 pr-4">Fiyat okunamadı</td><td class="py-2">Fiyatı <code>29,90</code> biçiminde yazın; harf, sıfır veya iki virgül olmasın.</td></tr>
                    <tr><td class="py-2 pr-4">“Kategori / Marka / Ürün” düzeninde olmalı</td><td class="py-2">Ürünün üstünde en az bir kategori ve bir marka klasörü olmalı. Markasızsa araya <code>Markasız</code> klasörü koyun.</td></tr>
                    <tr><td class="py-2 pr-4">En fazla 4 bölüm olabilir</td><td class="py-2">Klasör adında fazladan <code>;</code> var. Ürün adında noktalı virgül kullanmayın.</td></tr>
                    <tr><td class="py-2 pr-4">Barkod yalnızca harf, rakam ve - içerebilir</td><td class="py-2">Barkoddaki boşluğu veya işareti silin. Barkod yerine yanlışlıkla stok yazdıysanız <code>; ; 100</code> şeklinde düzeltin.</td></tr>
                    <tr><td class="py-2 pr-4">Stok 0 veya daha büyük bir tam sayı olmalı</td><td class="py-2">Stoğu <code>120</code> gibi tam sayı yazın; <code>120 adet</code> veya <code>12,5</code> olmaz.</td></tr>
                    <tr><td class="py-2 pr-4">Aynı ürün veya barkod başka bir klasörde de var</td><td class="py-2">Aynı barkod iki ürüne yazılmış ya da aynı ürün iki kez konmuş. Birini silin veya barkodu düzeltin.</td></tr>
                    <tr><td class="py-2 pr-4">Seçenek klasörü yok</td><td class="py-2">Adında <code>[Renk]</code> olan klasörün içine her seçenek için klasör açın; seçenek yoksa köşeli parantezi silin.</td></tr>
                    <tr><td class="py-2 pr-4">Seçenek iki kez var</td><td class="py-2">Aynı değerde (ör. iki “Mavi”) seçenek klasörü var; birini silin veya adını değiştirin.</td></tr>
                    <tr><td class="py-2 pr-4">Resim yok (uyarı)</td><td class="py-2">Ürün yine eklenir. Resmi sonradan ürün ekranından ekleyin; klasöre resim koyup yeniden bırakmak var olan ürüne resim eklemez (ürün “zaten var” sayılır).</td></tr>
                    <tr><td class="py-2 pr-4">Desteklenmeyen resim atlandı</td><td class="py-2">HEIC, GIF vb. resimleri JPG'ye çevirin.</td></tr>
                    <tr><td class="py-2 pr-4">Boş klasör</td><td class="py-2">İçinde ürün olmayan kategori veya marka klasörü; silebilir ya da yok sayabilirsiniz.</td></tr>
                    <tr><td class="py-2 pr-4">Oturum süresi doldu / bağlantı koptu</td><td class="py-2">Sayfayı yenileyin, gerekirse yeniden giriş yapın ve aynı klasörü yeniden bırakın; kaldığı yerden devam eder.</td></tr>
                </tbody>
            </table>
        </div>

        {{-- ================= SORULAR ================= --}}
        <h2 id="sorular" class="{{ $h2 }}">14. Sık sorulan sorular</h2>
        <dl class="space-y-4">
            <div><dt class="font-semibold text-ink">Yanlışlıkla aynı klasörü iki kez bırakırsam ne olur?</dt><dd>Hiçbir şey; ikinci seferde bütün ürünler “zaten var” sayılır, hiçbiri tekrar eklenmez.</dd></div>
            <div><dt class="font-semibold text-ink">Klasördeki fiyatı değiştirip yeniden yüklersem fiyat güncellenir mi?</dt><dd>Hayır. Var olan ürün hiçbir zaman değiştirilmez. Fiyatı ürün ekranından{{ $b2b ? ' veya “Toplu fiyat” sayfasından' : '' }} değiştirin.</dd></div>
            <div><dt class="font-semibold text-ink">Bir ürünü yanlış kategoriye yükledim, ne yapmalıyım?</dt><dd>Ürün ekranından kategorisini değiştirin. Klasörü düzeltip yeniden yüklemeyin: barkodsuz üründe ikinci bir ürün oluşur.</dd></div>
            <div><dt class="font-semibold text-ink">Kategori klasörünün adını sistemdeki kategoriden farklı yazarsam?</dt><dd>Yeni bir kategori açılır. Özet ekranındaki “yeni kategori” sayısına bakın; beklemediğiniz bir sayı varsa düğmeye basmadan önce klasör adını düzeltin.</dd></div>
            <div><dt class="font-semibold text-ink">Kaç seviye alt kategori açabilirim?</dt><dd>İstediğiniz kadar. Ürün herhangi bir seviyede durabilir; alt kategori istemezseniz marka klasörünü doğrudan ana kategorinin içine koyarsınız.</dd></div>
            @unless ($b2b)
                <div><dt class="font-semibold text-ink">Mağaza menüsünde bütün alt kategoriler görünür mü?</dt><dd>Açılır menüde ana kategori ve altındaki iki seviye görünür. Daha derindeki kategoriler kategori sayfasında ve filtrede listelenir; ürünler üst kategorilerin sayfalarında da çıkar.</dd></div>
            @endunless
            <div><dt class="font-semibold text-ink">Yükleme ne kadar sürer?</dt><dd>Ekranda kalan süre gösterilir. Resimsiz ürünler çok hızlı eklenir; süre çoğunlukla resim sayısına ve internet hızınıza bağlıdır.</dd></div>
            <div><dt class="font-semibold text-ink">Telefondan veya tabletten yükleyebilir miyim?</dt><dd>Klasör bırakma bilgisayar tarayıcısı içindir (Chrome, Edge, Firefox veya Safari'nin güncel sürümü). Telefonda klasör seçmek çoğu zaman mümkün değildir.</dd></div>
            <div><dt class="font-semibold text-ink">Resimlerim internete mi gönderiliyor?</dt><dd>Resimler yalnızca eklenen ürünler için, {{ $b2b ? 'kendi panelinize' : 'mağazanıza' }} yüklenir. Okuma ve küçültme sizin bilgisayarınızda yapılır; “zaten var” sayılan ürünlerin resimleri gönderilmez.</dd></div>
        </dl>

        {{-- ================= KONTROL ================= --}}
        <h2 id="kontrol" class="{{ $h2 }}">Yüklemeden önce kontrol listesi</h2>
        <ul class="space-y-1.5">
            @foreach ([
                'En dışta “Ürünler” klasörü var; içinde kategori klasörleri var.',
                'Her ürünün hemen üstünde bir marka klasörü (veya “Markasız”) var.',
                'Kategori adları sistemdeki adlarla aynı yazılmış (harf hatası yok).',
                'Her ürün klasörünün adında ad ve fiyat var: “Ürün adı ; 29,90”.',
                'Fiyatlarda kuruş virgülle yazılmış; binlik nokta kullanılmamış veya doğru kullanılmış.',
                'Barkodu olan ürünlere barkod yazılmış; stok yazılacaksa barkod boşsa “; ;” bırakılmış.',
                'Resimler ürün klasörlerinin içinde, JPG/PNG/WEBP; ilk resim kapak.',
                'Kategori ve marka klasörlerinde doğrudan resim yok.',
                'Seçenekli ürünlerde klasör adında [Renk] gibi seçenek adı ve içinde seçenek klasörleri var.',
                'İlk deneme küçük bir klasörle yapıldı ve sonuç kontrol edildi.',
            ] as $item)
                <li class="flex gap-2"><span class="mt-0.5 inline-block size-4 shrink-0 rounded border border-slate-400" aria-hidden="true"></span><span>{{ $item }}</span></li>
            @endforeach
        </ul>
    </article>
</div>
