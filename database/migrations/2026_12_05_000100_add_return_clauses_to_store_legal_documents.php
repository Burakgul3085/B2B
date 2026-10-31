<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yasal metinlere iade / iptal yeniliklerini anlatan cümleler eklenir (docs/31-yasal-metin-ekleri.md): kartlı siparişte
 * iptalde otomatik para iadesi, iade numarası ve takip bilgisi, iade kargosu ücreti, iade fotoğrafı, fotokopide cayma
 * hakkı istisnası, KVKK'da iade fotoğrafı / ürün sorusu / yorum fotoğrafı, çerez politikasında iyzico rozeti.
 *
 * Her metnin güncel sürümüne (yönetimde düzenlenmiş olsa da) yalnızca ekleme yapılır ve yeni sürüm olarak kaydedilir;
 * eski sürümler ve siparişlerdeki onaylar korunur. Bir metinde beklenen yer (ör. değiştirilmiş bir cümle) bulunamazsa o
 * metne hiç dokunulmaz; cümleler Yasal Metinler ekranından elle eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->edits() as $key => $edits) {
            $latest = DB::table('store_legal_documents')->where('key', $key)->orderByDesc('version')->first();
            if (! $latest) {
                continue;
            }

            $body = str_replace("\r\n", "\n", $latest->body);
            $changed = false;
            foreach ($edits as [$search, $replace, $marker]) {
                if (str_contains($body, $marker)) {
                    continue; // daha önce eklenmiş
                }
                if (substr_count($body, $search) !== 1) {
                    continue 2; // metin değiştirilmiş: bu belgeye dokunma
                }
                $body = str_replace($search, $replace, $body);
                $changed = true;
            }

            if ($changed) {
                DB::table('store_legal_documents')->insert([
                    'key' => $key, 'version' => $latest->version + 1, 'title' => $latest->title, 'body' => $body, 'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Yasal metin sürümleri yalnızca eklenir; geri alınmaz.
    }

    /**
     * Belge => [aranan metin, yerine gelecek metin, eklendiğini gösteren ifade].
     *
     * @return array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private function edits(): array
    {
        $returnShipping = "SATICI'nın bildirdiği kargo firması ve iade kodu dışında bir taşıyıcıyla yapılan iadelerde gönderim masrafı ALICI'ya aittir. "
            ."SATICI iade kodu bildirmemişse ALICI'nın ödediği gönderim masrafı iade tutarına eklenir.";

        return [
            'iptal-iade' => [
                [
                    'İptal için herhangi bir ücret alınmaz.',
                    'İptal için herhangi bir ücret alınmaz. Kartla ödenmiş siparişlerde iptalle birlikte ödemeniz kartınıza otomatik olarak iade edilir. '
                        ."Havale / EFT ile ödenmiş siparişlerde iptal talebinizde adınıza kayıtlı banka hesabının IBAN'ını bildirirsiniz; tutar bu hesaba gönderildikten sonra siparişiniz iptal edilir.",
                    'ödemeniz kartınıza otomatik olarak iade edilir',
                ],
                [
                    '4. Talebiniz onaylandığında size iade gönderim bilgisi iletilir. Ürünleri, bildirim tarihinden itibaren **10 gün** içinde, mümkünse orijinal kutusu ve faturası veya sipariş numarasıyla birlikte aşağıdaki adrese gönderin:',
                    '4. Talebiniz onaylandığında size iade gönderim bilgisi iletilir. Ürünleri, iade talebinizi oluşturduğunuz tarihten itibaren **10 gün** içinde, kutusuna iade numaranızı yazarak ve mümkünse orijinal kutusu ile birlikte aşağıdaki adrese gönderin. '
                        .'Gönderdikten sonra kargo firmasını ve takip numarasını sipariş sayfanızdaki iade bölümüne girin.',
                    'kutusuna iade numaranızı yazarak',
                ],
                [
                    'Tarafımızca bildirilen kargo firması ve gönderim bilgisiyle yapılan iadelerde **kargo ücreti tarafımıza aittir**.',
                    'Tarafımızca bildirilen kargo firması ve gönderim bilgisiyle yapılan iadelerde **kargo ücreti tarafımıza aittir**. '
                        .'Bildirdiğimiz kargo firması ve iade kodu dışında bir kargo firmasıyla gönderilen iadelerde kargo ücreti size aittir. '
                        .'İade kodu bildirilmemişse gönderimi gönderici ödemeli yapabilirsiniz; ödediğiniz kargo ücreti iade tutarınıza eklenir.'
                        ."\n\n".'İade talebinde ürün fotoğrafı ekleyebilirsiniz. Ürünün hasarlı, yanlış veya açıklamadaki gibi olmadığını bildirdiğiniz taleplerde en az bir fotoğraf eklemeniz istenir. '
                        .'Cayma hakkını gerekçe göstermeden ("Vazgeçtim") kullanırken fotoğraf zorunlu değildir.',
                    'İade talebinde ürün fotoğrafı ekleyebilirsiniz.',
                ],
                [
                    "- Kişiye özel hazırlanan, isim baskılı veya isteğinize göre üretilen ürünler (ör. kişiye özel kaşe, damga, baskılı ürünler);\n",
                    "- Kişiye özel hazırlanan, isim baskılı veya isteğinize göre üretilen ürünler (ör. kişiye özel kaşe, damga, baskılı ürünler);\n"
                        ."- Fotokopi ve belge baskıları gibi, gönderdiğiniz dosyaya göre size özel basılan ürünler (baskı hatası hâlinde ücretsiz yeniden basılır);\n",
                    'Fotokopi ve belge baskıları gibi',
                ],
            ],
            'mesafeli-satis' => [
                [
                    "gönderim bilgisiyle yapılan iadelerde gönderim masrafı SATICI'ya aittir. İade adresi:",
                    "gönderim bilgisiyle yapılan iadelerde gönderim masrafı SATICI'ya aittir. {$returnShipping} İade adresi:",
                    'iade kodu dışında bir taşıyıcıyla',
                ],
                [
                    'kişisel ihtiyaçları doğrultusunda hazırlanan mallarda,',
                    'kişisel ihtiyaçları doğrultusunda hazırlanan mallarda (fotokopi ve belge baskıları dahil),',
                    'hazırlanan mallarda (fotokopi ve belge baskıları dahil)',
                ],
            ],
            'on-bilgilendirme' => [
                [
                    "gönderim bilgisiyle yapılan iadelerde gönderim masrafı SATICI'ya aittir. İade adresi:",
                    "gönderim bilgisiyle yapılan iadelerde gönderim masrafı SATICI'ya aittir. {$returnShipping} "
                        .'Kartla ödenmiş siparişler kargoya verilmeden ALICI tarafından sitede iptal edildiğinde bedel ödeme aracına otomatik iade edilir. İade adresi:',
                    'iade kodu dışında bir taşıyıcıyla',
                ],
                [
                    '(ör. isim baskılı ürünler, kişiye özel kaşe ve damgalar)',
                    '(fotokopi ve belge baskıları dahil; ör. isim baskılı ürünler, kişiye özel kaşe ve damgalar)',
                    '(fotokopi ve belge baskıları dahil;',
                ],
            ],
            'kvkk' => [
                [
                    'iletişim formu mesajları, ürün yorumları |',
                    'iletişim formu mesajları, ürün yorumları; iade talebinde yüklediğiniz ürün fotoğrafları ve iade kargosu firma / takip bilgisi |',
                    'iade talebinde yüklediğiniz ürün fotoğrafları',
                ],
                [
                    '| **İşlem güvenliği** |',
                    '| **Ürün soruları** | Ürün sayfasından soru sorduğunuzda adınız, soyadınız, e-posta adresiniz ve sorunuz. E-posta adresiniz yalnızca cevabımızı size iletmek için kullanılır ve sitede gösterilmez; '
                        .'sorunuz cevabıyla birlikte, adınız ve soyadınızın baş harfiyle (ör. Ayşe K.) ürün sayfasında yayımlanır. |'
                        ."\n".'| **Ürün yorumları** | Ürün değerlendirmesinde yüklediğiniz fotoğraflar, yorumunuz yayımlandığında ürün sayfasında herkese açık olarak gösterilir. |'
                        ."\n| **İşlem güvenliği** |",
                    '| **Ürün soruları** |',
                ],
                [
                    "| Siparişin alınması, ödemenin tahsili, teslimat, iade ve müşteri hizmetleri | Sözleşmenin kurulması ve ifası (m. 5/2-c) |\n",
                    "| Siparişin alınması, ödemenin tahsili, teslimat, iade ve müşteri hizmetleri | Sözleşmenin kurulması ve ifası (m. 5/2-c) |\n"
                        ."| İade talebinin değerlendirilmesi ve ürünün durumunun tespiti (yüklenen fotoğraflar yalnızca bu amaçla, yetkili personelce görüntülenir) | Sözleşmenin kurulması ve ifası (m. 5/2-c) |\n",
                    'İade talebinin değerlendirilmesi ve ürünün durumunun tespiti',
                ],
            ],
            'gizlilik-cerez' => [
                [
                    'iyzico, ödeme güvenliği için kendi çerezlerini kullanabilir.',
                    'iyzico, ödeme güvenliği için kendi çerezlerini kullanabilir.'
                        ."\n\n".'**iyzico Korumalı Alışveriş rozeti:** Sitemizin sayfalarında, iyzico Korumalı Alışveriş kapsamında olduğumuzu göstermek için iyzico\'nun sağladığı rozet betiği yüklenir. '
                        .'Bu betik iyzico sunucularından yüklenir ve iyzico kendi çerezlerini kullanabilir.',
                    'iyzico Korumalı Alışveriş rozeti',
                ],
            ],
        ];
    }
};
