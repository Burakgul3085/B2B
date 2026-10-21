<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$page->exists ? $page->title : 'Yeni sayfa'">
    <x-page-header :title="$page->exists ? $page->title : 'Yeni sayfa'">
        <x-slot:breadcrumb><a href="{{ route('store_admin.pages.index') }}" class="hover:underline">Sayfalar</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="xl:col-span-2">
            <form method="POST" action="{{ $page->exists ? route('store_admin.pages.update', $page) : route('store_admin.pages.store') }}" class="space-y-5">
                @csrf @if ($page->exists) @method('PUT') @endif
                <x-field name="title" label="Başlık" :value="$page->title" required maxlength="160" />
                <div>
                    <label for="f_body" class="mb-1.5 block text-sm font-medium text-ink">İçerik</label>
                    <textarea id="f_body" name="body" rows="18" class="field-input font-mono text-sm leading-relaxed">{{ old('body', $page->body) }}</textarea>
                    @error('body')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="slug" label="Adres (kısa ad)" :value="$page->slug" maxlength="180" hint="/sayfa/kisa-ad — boşsa başlıktan üretilir." />
                    <x-field name="seo_title" label="SEO başlığı" :value="$page->seo_title" maxlength="160" />
                    <x-field name="seo_description" label="SEO açıklaması" :value="$page->seo_description" maxlength="320" class="sm:col-span-2" />
                </div>
                <div class="flex flex-wrap gap-6">
                    <x-toggle name="is_active" label="Yayında" :checked="$page->exists ? $page->is_active : true" />
                    <x-toggle name="show_in_footer" label="Alt bilgide göster" :checked="$page->exists ? $page->show_in_footer : true" />
                </div>
                <x-button>{{ $page->exists ? 'Kaydet' : 'Sayfayı oluştur' }}</x-button>
            </form>
        </x-card>

        <div class="space-y-6">
            <x-card title="Yazım kılavuzu">
                <ul class="space-y-2 font-mono text-xs text-slate-700">
                    <li>## Ara başlık</li>
                    <li>**kalın yazı**</li>
                    <li>- madde işareti</li>
                    <li>1. numaralı madde</li>
                    <li>[bağlantı metni](https://...)</li>
                </ul>
                <p class="mt-3 text-xs text-muted">Paragraflar arasında bir boş satır bırakın. Güvenlik için HTML kodu çalıştırılmaz.</p>
            </x-card>
            @if ($page->exists)
                <x-card title="Sayfayı sil">
                    <form method="POST" action="{{ route('store_admin.pages.destroy', $page) }}" data-confirm="&quot;{{ $page->title }}&quot; silinsin mi?">
                        @csrf @method('DELETE')
                        <x-button variant="danger" size="sm" icon="trash">Sil</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
