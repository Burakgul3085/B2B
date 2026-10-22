{{-- Mağaza işlem geçmişi tablosu: $logs, $names (mağaza yöneticisi id => ad) --}}
@php
    $labels = [
        'auth.login' => 'Giriş yaptı', 'auth.login_failed' => 'Başarısız giriş denemesi', 'auth.password_changed' => 'Parolasını değiştirdi',
        'auth.password_reset' => 'Parolasını sıfırladı', 'auth.email_changed' => 'Giriş e-postasını değiştirdi',
        'store.two_factor_enabled' => 'İki adımlı doğrulamayı açtı', 'store.two_factor_disabled' => 'İki adımlı doğrulamayı kapattı',
        'store.two_factor_failed' => 'Hatalı doğrulama kodu',
        'store.admin_created' => 'Yönetici hesabı açıldı', 'store.admin_updated' => 'Yönetici bilgileri değişti',
        'store.admin_password_set' => 'Yöneticiye yeni şifre belirlendi', 'store.admin_activated' => 'Yönetici hesabı kullanıma açıldı',
        'store.admin_deactivated' => 'Yönetici hesabı pasife alındı', 'store.admin_two_factor_reset' => 'Yöneticinin iki adımlı doğrulaması sıfırlandı',
        'store.product_created' => 'Ürün eklendi', 'store.product_updated' => 'Ürün güncellendi', 'store.product_deleted' => 'Ürün silindi',
        'store.product_published' => 'Ürün yayına alındı', 'store.product_unpublished' => 'Ürün yayından kaldırıldı',
        'store.variant_created' => 'Ürün seçeneği eklendi', 'store.variant_updated' => 'Fiyat / seçenek güncellendi', 'store.variant_deleted' => 'Ürün seçeneği silindi',
        'store.product_image_added' => 'Ürün görseli eklendi', 'store.product_image_removed' => 'Ürün görseli kaldırıldı', 'store.product_image_primary' => 'Ana görsel değişti',
        'store.products_imported' => 'Excel ile ürün aktarıldı',
        'store.category_created' => 'Kategori eklendi', 'store.category_updated' => 'Kategori güncellendi', 'store.category_deleted' => 'Kategori silindi',
        'store.brand_created' => 'Marka eklendi', 'store.brand_updated' => 'Marka güncellendi', 'store.brand_deleted' => 'Marka silindi',
        'store.brand_logo_updated' => 'Marka logosu güncellendi', 'store.brand_logo_removed' => 'Marka logosu kaldırıldı',
        'store.banner_created' => 'Banner eklendi', 'store.banner_updated' => 'Banner güncellendi', 'store.banner_deleted' => 'Banner silindi',
        'store.page_created' => 'Sayfa eklendi', 'store.page_updated' => 'Sayfa güncellendi', 'store.page_deleted' => 'Sayfa silindi',
        'store.settings_updated' => 'Mağaza ayarları güncellendi', 'store.opened' => 'Mağaza ziyaretçilere açıldı', 'store.closed' => 'Mağaza ziyaretçilere kapatıldı',
        'store.stock_received' => 'Stok girişi', 'store.stock_counted' => 'Stok sayımı', 'store.stock_adjusted' => 'Stok düzeltmesi', 'store.stock_reversed' => 'Stok hareketi geri alındı',
        'store.order_placed' => 'Yeni sipariş', 'store.order_approved' => 'Sipariş onaylandı', 'store.order_preparing' => 'Sipariş hazırlanıyor',
        'store.order_shipped' => 'Sipariş yola çıktı', 'store.order_delivered' => 'Sipariş teslim edildi', 'store.order_not_delivered' => 'Sipariş teslim alınmadı',
        'store.order_cancelled' => 'Sipariş iptal edildi', 'store.order_paid_without_stock' => 'Ödeme alındı, stok yetersiz (kontrol edin)',
        'store.cod_unblocked' => 'Telefon kapıda ödeme engelinden çıkarıldı',
        'store.legal_published' => 'Yasal metin yayımlandı', 'store.payment_settings_updated' => 'Ödeme ve teslimat ayarları güncellendi',
        'store.payment_secret_removed' => 'Ödeme / SMS anahtarı silindi',
        'store.return_requested' => 'İade talebi açıldı', 'store.return_approved' => 'İade onaylandı', 'store.return_rejected' => 'İade reddedildi',
        'store.return_received' => 'İade ürünü teslim alındı', 'store.return_refunded' => 'İade tamamlandı',
        'store.refund_succeeded' => 'Para iadesi yapıldı', 'store.refund_failed' => 'Para iadesi başarısız',
        'store.invoice_added' => 'Fatura eklendi', 'store.invoice_replaced' => 'Fatura değiştirildi',
        'store.customer_closed' => 'Müşteri hesabını kapattı', 'store.payment_logos_updated' => 'Ödeme logo bandı güncellendi', 'store.payment_logos_removed' => 'Ödeme logo bandı kaldırıldı',
        'store.credit_note_added' => 'İade faturası eklendi', 'store.discount_codes_generated' => 'Toplu kupon kodu üretildi',
        'store.discount_created' => 'İndirim oluşturuldu', 'store.discount_updated' => 'İndirim güncellendi', 'store.discount_deleted' => 'İndirim silindi',
        'store.carrier_created' => 'Kargo firması eklendi', 'store.carrier_updated' => 'Kargo firması güncellendi', 'store.carrier_deleted' => 'Kargo firması silindi',
    ];
    $entityNames = ['StoreProduct' => 'Ürün', 'StoreProductVariant' => 'Seçenek', 'StoreCategory' => 'Kategori', 'StoreBrand' => 'Marka'];
    $legalTitles = \App\Models\Store\StoreLegalDocument::KEYS;
@endphp
@if ($logs->isEmpty())
    <x-empty-state icon="list" title="Henüz kayıt yok" class="py-8" />
@else
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Tarih</th><th>Kim</th><th>İşlem</th><th>Ayrıntı</th><th>IP</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($logs as $log)
                    @php
                        $who = match ($log->actor_type) {
                            'store_admin' => $names[$log->actor_id] ?? 'Yönetici #'.$log->actor_id,
                            'platform' => 'Platform yöneticisi',
                            'customer' => 'Müşteri',
                            default => '—',
                        };
                        $subject = $log->entity_type === 'StoreAdmin' && $log->entity_id !== $log->actor_id ? ($names[$log->entity_id] ?? null) : null;
                        $detail = match (true) {
                            $log->action === 'auth.login_failed' => $log->new_values['email'] ?? null,
                            $log->action === 'store.products_imported' => ($log->new_values['file'] ?? '').' · '.($log->new_values['created'] ?? 0).' eklendi, '.($log->new_values['updated'] ?? 0).' güncellendi',
                            isset($log->new_values['order_no']) => 'Sipariş '.$log->new_values['order_no'],
                            $log->action === 'store.legal_published' => ($legalTitles[$log->new_values['key'] ?? ''] ?? '').' · sürüm '.($log->new_values['version'] ?? ''),
                            $log->action === 'store.cod_unblocked' => $log->old_values['phone'] ?? null,
                            isset($entityNames[$log->entity_type]) => $entityNames[$log->entity_type].' #'.$log->entity_id
                                .(isset($log->new_values['name']) ? ' · '.$log->new_values['name'] : (isset($log->old_values['name']) ? ' · '.$log->old_values['name'] : ''))
                                .(isset($log->new_values['sku']) ? ' · '.$log->new_values['sku'] : '')
                                .(isset($log->new_values['quantity']) ? ' · '.sprintf('%+d', $log->new_values['quantity']).' adet' : '')
                                .(isset($log->new_values['stock'], $log->old_values['stock']) ? ' · '.$log->old_values['stock'].' → '.$log->new_values['stock'] : ''),
                            default => $subject,
                        };
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap text-muted tabular-nums">{{ $log->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                        <td class="font-medium">{{ $who }}</td>
                        <td>{{ $labels[$log->action] ?? $log->action }}</td>
                        <td class="text-muted">{{ $detail ?? '—' }}</td>
                        <td class="text-muted tabular-nums">{{ $log->ip }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
