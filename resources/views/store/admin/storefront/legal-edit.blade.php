<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$document->title">
    <x-page-header :title="$document->title" :subtitle="'Güncel sürüm '.$document->version">
        <x-slot:breadcrumb><a href="{{ route('store_admin.legal.index') }}" class="hover:underline">Yasal Metinler</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2">
            <form method="POST" action="{{ route('store_admin.legal.update', $key) }}" class="space-y-5" data-confirm="Yeni sürüm yayınlansın mı? Bundan sonraki siparişlerde bu metin onaylatılır.">
                @csrf @method('PUT')
                <x-field name="title" label="Başlık" :value="$document->title" required maxlength="160" />
                <div>
                    <label for="f_body" class="mb-1.5 block text-sm font-medium">Metin (Markdown)</label>
                    <textarea id="f_body" name="body" rows="28" class="field-input font-mono text-sm leading-relaxed">{{ old('body', $document->body) }}</textarea>
                    @error('body')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <x-button>Yeni sürüm olarak yayınla</x-button>
            </form>
        </x-card>
        <div class="space-y-6">
            <x-card title="Yer tutucular" description="Sipariş anında doldurulur.">
                <ul class="flex flex-wrap gap-1.5">
                    @foreach ($placeholders as $placeholder)<li class="rounded bg-surface px-1.5 py-0.5 font-mono text-xs">{{ '{'.$placeholder.'}' }}</li>@endforeach
                </ul>
            </x-card>
            <x-card title="Sürümler">
                <ul class="space-y-1 text-sm">
                    @foreach ($history as $item)
                        <li>Sürüm {{ $item->version }} <span class="text-muted">· {{ \Illuminate\Support\Carbon::parse($item->created_at)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span></li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
