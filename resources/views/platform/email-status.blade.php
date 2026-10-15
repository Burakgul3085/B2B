@php use App\Domain\Mail\MailFailure; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="E-posta Altyapısı">
    <x-page-header title="E-posta altyapısı" subtitle="İşletmelerin kendi Gmail ayarlarının teknik durumu (son 7 gün). İçerik, alıcı ve şifreler gösterilmez." />

    <x-card title="Sistem e-postası" class="mb-6">
        <p class="text-sm">
            @if ($systemConfigured)<x-badge tone="success">Yapılandırıldı</x-badge>@else<x-badge tone="warning">Yapılandırılmadı</x-badge>@endif
            <span class="ml-2 text-muted">Platformun kendi e-postaları (işletme yetkilisi daveti, parola sıfırlama). <code>.env</code> içindeki <code>SYSTEM_MAIL_*</code> değerleri.</span>
        </p>
    </x-card>

    <x-card :padding="false">
        <table class="table-base">
            <thead><tr><th>İşletme</th><th>Durum</th><th class="text-right">Gönderildi</th><th class="text-right">Başarısız</th><th class="text-right">Atlandı</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($businesses as $b)
                    @php $s = $settings->get($b->id); $st = $stats->get($b->id, collect()); @endphp
                    <tr>
                        <td><a href="{{ route('platform.businesses.show', $b) }}" class="font-medium hover:text-primary">{{ $b->display_name }}</a></td>
                        <td>
                            @if (! $s)<x-badge>Ayar yok</x-badge>
                            @elseif ($s->status === 'verified')<x-badge tone="success">Doğrulandı</x-badge>
                            @elseif ($s->status === 'unverified')<x-badge tone="warning">Test edilmedi</x-badge>
                            @else<x-badge tone="danger">Hatalı</x-badge><p class="mt-1 max-w-72 text-xs text-muted">{{ MailFailure::MESSAGES[$s->last_error_code] ?? '' }}</p>@endif
                        </td>
                        <td class="text-right tabular-nums">{{ $st['sent'] ?? 0 }}</td>
                        <td @class(['text-right tabular-nums', 'font-semibold text-danger' => ($st['failed'] ?? 0) > 0])>{{ $st['failed'] ?? 0 }}</td>
                        <td class="text-right tabular-nums text-muted">{{ $st['skipped'] ?? 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
</x-layouts.panel>
