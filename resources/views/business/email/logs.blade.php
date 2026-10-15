@php use App\Domain\Mail\EmailTemplates; use App\Domain\Mail\MailFailure; use App\Models\EmailLog; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="E-posta Kayıtları">
    <x-page-header title="Gönderim kayıtları" subtitle="E-posta gönderilemese bile sipariş ve diğer işlemler geri alınmaz; başarısız olanları buradan tekrar gönderebilirsiniz.">
        <x-slot:breadcrumb><a href="{{ route('business.email.settings') }}" class="hover:underline">E-posta Ayarları</a></x-slot:breadcrumb>
    </x-page-header>
    @error('email')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <nav class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Tümü'] + EmailLog::STATUSES as $k => $label)
            <a href="{{ route('business.email.logs', $k ? ['status' => $k] : []) }}" @class(['rounded-full px-3 py-1.5 text-sm font-medium', 'bg-primary text-white' => ($status ?? '') === $k, 'bg-white text-muted ring-1 ring-line' => ($status ?? '') !== $k])>{{ $label }}</a>
        @endforeach
    </nav>

    <x-card :padding="false">
        @if ($logs->isEmpty())
            <x-empty-state icon="inbox" title="Kayıt yok" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Tarih</th><th>Tür</th><th>Alıcı</th><th>Konu</th><th>Durum</th><th></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap text-muted tabular-nums">{{ $log->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td class="text-sm">{{ EmailTemplates::DEFAULTS[$log->template_key]['label'] ?? $log->template_key }}</td>
                                <td class="text-sm">{{ $log->to_email }}</td>
                                <td class="max-w-64 truncate text-sm">{{ $log->subject }}</td>
                                <td>
                                    <x-badge :tone="['queued' => 'info', 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'neutral'][$log->status]">{{ EmailLog::STATUSES[$log->status] }}</x-badge>
                                    @if ($log->status === 'failed' && $log->last_error_code)<p class="mt-1 max-w-56 text-xs text-muted">{{ MailFailure::MESSAGES[$log->last_error_code] ?? '' }}</p>@endif
                                </td>
                                <td class="text-right">
                                    @if (in_array($log->status, ['failed', 'skipped'], true))
                                        <form method="POST" action="{{ route('business.email.retry', $log) }}">@csrf<button class="text-sm text-primary hover:underline">Tekrar gönder</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())<div class="border-t border-line p-4">{{ $logs->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
