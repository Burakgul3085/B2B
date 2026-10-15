@php
    $actionLabels = [
        'auth.login' => 'Giriş', 'auth.login_failed' => 'Başarısız giriş', 'auth.two_factor_enabled' => '2FA etkinleştirildi',
        'auth.two_factor_failed' => 'Hatalı 2FA kodu', 'auth.password_changed' => 'Parola değiştirildi', 'auth.password_reset' => 'Parola sıfırlandı',
        'platform.business_created' => 'İşletme oluşturuldu', 'platform.business_updated' => 'İşletme güncellendi',
        'platform.business_status_changed' => 'İşletme durumu değişti', 'platform.license_added' => 'Lisans eklendi',
        'platform.modules_updated' => 'Modüller güncellendi', 'platform.activation_resent' => 'Aktivasyon yeniden gönderildi',
        'platform.activation_link_shown' => 'Aktivasyon bağlantısı ekranda gösterildi',
    ];
@endphp
@if ($logs->isEmpty())
    <x-empty-state icon="file" title="Kayıt yok" class="py-8" />
@else
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Tarih</th><th>İşlem</th><th>Kayıt</th><th>IP</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-muted tabular-nums">{{ $log->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                        <td class="font-medium">{{ $actionLabels[$log->action] ?? $log->action }}</td>
                        <td class="text-muted">
                            @if ($log->business_id)
                                <a href="{{ route('platform.businesses.show', $log->business_id) }}" class="text-primary hover:underline">İşletme #{{ $log->business_id }}</a>
                            @elseif ($log->entity_type)
                                {{ $log->entity_type }} #{{ $log->entity_id }}
                            @else
                                {{ $log->new_values['email'] ?? '—' }}
                            @endif
                        </td>
                        <td class="text-muted tabular-nums">{{ $log->ip }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
