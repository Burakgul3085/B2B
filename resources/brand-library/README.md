# Hazır marka kütüphanesi

Mağaza ve B2B "Yeni marka" formundaki öneri listesi (`App\Domain\Support\BrandLibrary`, `<x-brand-picker>`).

- `brands.json`: `{name, slug, categories, logo}` listesi. Kategoriler: `kirtasiye`, `ofis`, `parti`, `oyuncak`. `logo` boşsa marka logosuz eklenir.
- `logos/<slug>.webp`: kenarı kırpılmış, en uzun kenarı 480 px logo. Eklenirken her taraf logoyu kendi görsel kuralıyla yeniden kodlayıp kendi dizinine kopyalar; bu klasör doğrudan sunulmaz (yalnızca giriş yapmış panel kullanıcısına önizleme olarak).
- Kaynak (2026-11-07): Türkiye'deki kırtasiye, ofis, parti ve oyuncak satıcılarının marka listeleri birleştirilip ayıklandı. Logolar Wikidata/Wikimedia Commons ve markaların resmi sitelerinden alındı ve tek tek gözle kontrol edildi; emin olunamayanlar logosuz bırakıldı.
- Logolar markaların tescilli işaretleridir; yalnızca satılan ürünün markasını göstermek için kullanılır.

Yeni marka eklemek için `brands.json`'a satır, varsa `logos/` altına WebP/PNG logo eklenir (`slug`: küçük harf, rakam ve tire). `BrandLibraryTest::test_shipped_library_is_valid` dosyayı denetler.
