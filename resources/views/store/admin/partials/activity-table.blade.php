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
    ];
    $entityNames = ['StoreProduct' => 'Ürün', 'StoreProductVariant' => 'Seçenek', 'StoreCategory' => 'Kategori', 'StoreBrand' => 'Marka'];
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
                            default => '—',
                        };
                        $subject = $log->entity_type === 'StoreAdmin' && $log->entity_id !== $log->actor_id ? ($names[$log->entity_id] ?? null) : null;
                        $detail = match (true) {
                            $log->action === 'auth.login_failed' => $log->new_values['email'] ?? null,
                            $log->action === 'store.products_imported' => ($log->new_values['file'] ?? '').' · '.($log->new_values['created'] ?? 0).' eklendi, '.($log->new_values['updated'] ?? 0).' güncellendi',
                            isset($entityNames[$log->entity_type]) => $entityNames[$log->entity_type].' #'.$log->entity_id
                                .(isset($log->new_values['name']) ? ' · '.$log->new_values['name'] : (isset($log->old_values['name']) ? ' · '.$log->old_values['name'] : ''))
                                .(isset($log->new_values['sku']) ? ' · '.$log->new_values['sku'] : ''),
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
