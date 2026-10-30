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
