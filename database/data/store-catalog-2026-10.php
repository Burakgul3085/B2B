<?php

/*
| Mağazanın ilk gerçek kataloğu (28.10.2026, iyzico başvurusu öncesi): 4 ana kategori × 2 alt kategori × 2 ürün.
| Ürün görselleri ve başlangıç fiyatları Kaya Kırtasiye'nin (kayakirtasiye.com.tr) izniyle onların sitesinden alındı
| (mağaza sahibi resmî yazılı izin aldığını bildirdi). Açıklamalar mağaza için yazıldı. Fiyat, KDV ve stok mağaza
| yönetiminden güncellenmelidir. Yükleme: php artisan store:import-catalog (aynı stok kodu varsa atlanır).
|
| products: [kategori, alt kategori, marka, ad, stok kodu, fiyat (KDV dahil), KDV, stok, açıklama, [görsel adresleri]]
*/

$img = fn (string $path) => 'https://www.kayakirtasiye.com.tr'.$path;

return [
    'categories' => [
        'Kalemler' => ['Tükenmez Kalemler', 'Kurşun Kalemler'],
        'Defter ve Bloknotlar' => ['Spiralli Defterler', 'Bloknotlar'],
        'Boyalar' => ['Kuru Boyalar', 'Sulu Boyalar'],
        'Ofis Malzemeleri' => ['Zımba Makinaları', 'Delgeçler'],
    ],
    'products' => [
        ['Kalemler', 'Tükenmez Kalemler', 'Schneider', 'Schneider Slider Edge XB Mavi Tükenmez Kalem', 'YB-152203', '69.00', '20', 10,
            "Üçgen gövdeli, kaymaz tutuşlu tükenmez kalem. XB (ekstra kalın) uç, Viscoglide mürekkeple akıcı ve kesintisiz yazar.\nRenk: Mavi.",
            [$img('/productimages/188701/original/37.27.230.362-1.png')]],
        ['Kalemler', 'Tükenmez Kalemler', 'Scrikss', 'Scrikss F108 Sky Yarım Krom Tükenmez Kalem', 'YB-SCR-F108', '202.00', '20', 10,
            'Yarım krom kaplama metal gövdeli, basmalı tükenmez kalem. Günlük kullanım ve hediye için şık bir seçenek.',
            [$img('/productimages/135009/original/37.27.233.386-1.png')]],
        ['Kalemler', 'Kurşun Kalemler', 'Adel', 'Adel BL Mozaik HB Kurşun Kalem 72\'li', 'YB-20610000490', '881.00', '20', 10,
            "HB sertlikte, desenli gövdeli kurşun kalem. 72'li kutu; okul, sınıf ve kırtasiye satışı için uygundur.",
            [$img('/productimages/281837/original/37.16.007.002-1.png'), $img('/productimages/281837/original/37.16.007.002-2.png')]],
        ['Kalemler', 'Kurşun Kalemler', 'Bic', 'Bic Evolution Pastel HB Kurşun Kalem 5\'li', 'YB-518980', '84.00', '20', 10,
            "Kırılmaya dayanıklı, ahşap içermeyen gövdeli HB kurşun kalem. Pastel renkli gövdeler, 5'li paket.",
            [$img('/productimages/282763/original/37.16.029.029-1.png')]],

        ['Defter ve Bloknotlar', 'Spiralli Defterler', 'Çınar', 'Çınar Colormaxi A4 96 Yaprak Çizgili Spiralli Defter', 'YB-73004', '85.00', '20', 10,
            'A4 boyutunda, 96 yaprak çizgili spiralli defter. Dayanıklı PP (plastik) kapak.',
            [$img('/productimages/125348/original/11.12.086.042-1.png')]],
        ['Defter ve Bloknotlar', 'Spiralli Defterler', 'Gıpta', 'Gıpta Enviro 17x24 120 Yaprak Çizgili Spiralli Sert Kapak Defter', 'YB-GPT-3992', '224.00', '20', 10,
            '17x24 cm, 120 yaprak çizgili, ayraçlı (separatörlü) spiralli defter. Sert kapaklı.',
            [$img('/productimages/279954/original/11.12.091.020-1.png'), $img('/productimages/279954/original/11.12.091.020-2.png'), $img('/productimages/279954/original/11.12.091.020-3.png')]],
        ['Defter ve Bloknotlar', 'Bloknotlar', 'Dilman', 'Dilman A5 40 Yaprak Kareli Spiralli Bloknot', 'YB-DLM-A5-KS', '51.00', '20', 10,
            'A5 boyutunda, 40 yaprak kareli, üstten spiralli bloknot. Not almak ve hesap için pratik.',
            [$img('/productimages/124777/original/11.04.051.140-1.png'), $img('/productimages/124777/original/11.04.051.140-2.png')]],
        ['Defter ve Bloknotlar', 'Bloknotlar', 'Gıpta', 'Gıpta Rafadan Tayfa A7 Mini Bloknot', 'YB-GPT-6355', '31.00', '20', 10,
            'Rafadan Tayfa karakterli, A7 boyutunda cep bloknotu. Çantaya ve cebe sığar.',
            [$img('/productimages/249086/original/11.04.091.816-1.png')]],

        ['Boyalar', 'Kuru Boyalar', 'Faber-Castell', 'Faber-Castell 12 Renk Tam Boy Kuru Boya Kalemi', 'YB-51711163120', '182.00', '20', 10,
            '12 renk, tam boy kuru boya kalemi. Canlı renkler, kolay açılan uçlar.',
            [$img('/productimages/122402/original/07.11.079.074-1.png')]],
        ['Boyalar', 'Kuru Boyalar', 'Bic', 'Bic Tropicolors 24 Renk Kuru Boya', 'YB-9375183', '309.00', '20', 10,
            '24 renk kuru boya kalemi seti. Okul ve hobi çalışmaları için geniş renk seçeneği.',
            [$img('/productimages/122374/original/07.11.029.039-1.png')]],
        ['Boyalar', 'Sulu Boyalar', 'Faber-Castell', 'Faber-Castell 21 Renk Büyük Boy Sulu Boya', 'YB-52921250210', '445.00', '20', 10,
            '21 renk, büyük boy tabletli sulu boya. Kolay karışan, parlak renkler; fırçasıyla birlikte.',
            [$img('/productimages/122847/original/07.17.079.034-1.png'), $img('/productimages/122847/original/07.17.079.034-2.png')]],
        ['Boyalar', 'Sulu Boyalar', 'Adel', 'Adel 12 Renk Küçük Boy Sulu Boya', 'YB-933001', '108.00', '20', 10,
            '12 renk, 24 mm tabletli küçük boy sulu boya. Okul resim dersleri için uygundur.',
            [$img('/productimages/141695/original/07.17.007.001-1.png'), $img('/productimages/141695/original/07.17.007.001-2.png'), $img('/productimages/141695/original/07.17.007.001-3.png')]],

        ['Ofis Malzemeleri', 'Zımba Makinaları', 'Max', 'Max HD-10D Zımba Makinası Siyah', 'YB-MAX-HD10D-S', '237.00', '20', 10,
            'No:10 zımba teli kullanan, avuç içi boyutunda zımba makinası. Renk: Siyah.',
            [$img('/productimages/130320/original/25.22.148.104-1.png'), $img('/productimages/130320/original/25.22.148.104-2.png')]],
        ['Ofis Malzemeleri', 'Zımba Makinaları', 'Leitz', 'Leitz 5501 Zımba Makinası 24/6 Mavi', 'YB-LTZ-5501-35', '468.00', '20', 10,
            '24/6 zımba teliyle 25 sayfaya kadar zımbalar. Metal mekanizma, masaüstü kullanım. Renk: Mavi.',
            [$img('/productimages/130296/original/25.22.136.079-1.png')]],
        ['Ofis Malzemeleri', 'Delgeçler', 'Maped', 'Maped Essentials 10-12 Sayfa Delgeç', 'YB-401111', '231.00', '20', 10,
            'Tek seferde 10-12 sayfa delen, ölçü kılavuzlu çift delikli delgeç.',
            [$img('/ProductImages/129541/original/ok_12402354.jpg')]],
        ['Ofis Malzemeleri', 'Delgeçler', 'Kangaro', 'Kangaro DP-600 Delgeç 22 Sayfa', 'YB-KNG-DP600', '246.00', '20', 10,
            'Tek seferde 22 sayfaya kadar delen, ölçü kılavuzlu metal delgeç.',
            [$img('/productimages/209479/original/25.04.122.002-1.png'), $img('/productimages/209479/original/25.04.122.002-2.png'), $img('/productimages/209479/original/25.04.122.002-3.png')]],
    ],
];
