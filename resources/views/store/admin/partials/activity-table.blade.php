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
    ];
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
                        $detail = $log->action === 'auth.login_failed' ? ($log->new_values['email'] ?? null) : $subject;
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
