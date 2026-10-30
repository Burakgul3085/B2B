# Mağaza: Değerlendirmelerim ve fotoğraflı yorumlar

Ürün yorumu altyapısı vardı (ürün sayfasında form, yalnızca ürünü teslim almış üye, yönetici onayı, kartlarda yıldız). Eklenenler:

- **Hesabım → Değerlendirmelerim** (`/hesabim/degerlendirmeler`): Teslim alınan, yayında olan ve henüz yorumlanmamış ürünler (en son teslim alınan önce), her biri için açılır form: yıldız, başlık, yorum, fotoğraflar. Altında üyenin yorumları: durum (Onay bekliyor / Yayında / Yayımlanmadı), fotoğraflar, mağaza yanıtı.
- **Genel bakış:** "N ürün değerlendirmenizi bekliyor" kutusu.
- **Sipariş sayfası:** Teslim edilmiş üye siparişinde her ürünün altında "Değerlendir" bağlantısı (fotokopi hariç).
- **Teslim e-postası (yeni):** Sipariş teslim edildi yapılınca müşteriye e-posta gider; üyeye "Ürünleri değerlendir" düğmesiyle.
- **Fotoğraflar:** Yorum başına en fazla 5 (JPG, PNG, WEBP; her biri 10 MB). Yeniden kodlanır, özel diskte (`store/reviews`). `/yorum-fotografi/{id}`: yayındaki yorumda herkese (önbelleklenir), onay bekleyende yalnızca yorumun sahibine. Ürün sayfasında yorumun altında küçük resimler; yönetimde Yorumlar ekranında görünür. Yorum silinince fotoğraflar da silinir.
- **Yöneticiye e-posta:** Yeni yorum geldiğinde "Onay bekleyen yorum" (Yönetici bildirimleri → iade tercihiyle birlikte).
- Ürün kartlarında (ana sayfa, listeler) yayındaki yorumlardan yıldız ve yorum sayısı zaten gösteriliyor.

Teknik: migration `2026_12_02_000100_create_store_review_photos`, `ReviewService::awaiting / submit / canSeePhoto`, `Front\ReviewController::index / photo`, `Admin\ReviewController::photo`, `OrderMailer::delivered`. Test: `tests/Feature/Store/StorefrontEngagementTest.php`.

## Soru & Cevap ve yeni yorum tasarımı

- **Soru & Cevap:** Ürün sayfasında "Değerlendirmeler / Soru & Cevap" sekmeleri. Ziyaretçi üye olmadan ad, soyad, e-posta ve soruyla sorar (onay kutusu zorunlu; bot tuzağı; IP başına 10 dakikada 5 istek; bir e-postadan en fazla 3 cevap bekleyen soru). Soru vitrinde görünmez; Katalog → **Ürün Soruları** ekranında cevaplanınca soru ve cevap birlikte yayımlanır, cevap soranın e-postasına bir kez gider. Soran "Ayşe K." olarak görünür, e-posta hiçbir yerde gösterilmez. Yönetici cevabı düzenleyebilir, soru metnindeki yazım hatasını düzeltebilir, soruyu gizleyebilir veya silebilir. Yeni soruda yöneticiye e-posta gider; menüde cevap bekleyen sayısı rozettir. `#sorular` bağlantısı Soru & Cevap sekmesini açar; telefonda cevaplanan sorular formdan önce gelir.
- **Değerlendirmelerin yeni görünümü:** Lacivert özet kartı (puan, yıldız, doğrulanmış değerlendirme sayısı), tavsiye oranı (4–5 yıldız), tıklanınca o yıldıza süzen dağılım çubukları, müşteri fotoğrafları şeridi, sıralama (en yeni / en yüksek / en düşük / fotoğraflı), "Daha fazla değerlendirme göster", baş harfli avatar ve "Doğrulanmış alışveriş" rozetli kartlar, mağaza yanıtı logolu girintili blokta. Fotoğraflar tek galeride büyütülür (ok tuşları, Esc).
- Teknik: migration `2026_12_03_000100_create_store_product_questions`, `StoreProductQuestion`, `QuestionService`, `Front\QuestionController`, `Admin\QuestionController`, `ProductController::feedback`, `feedbackTabs` bileşeni. Test: `StorefrontEngagementTest`.
