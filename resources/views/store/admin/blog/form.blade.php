@php
    $title = $post->exists ? $post->title : 'Yeni yazı';
    $publishedAt = old('published_at', $post->published_at?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i'));
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$title">
    <x-page-header :title="$title">
        <x-slot:breadcrumb><a href="{{ route('store_admin.blog.index') }}" class="hover:underline">Blog</a></x-slot:breadcrumb>
        @if ($post->exists && $post->isLive())<x-button :href="route('store.blog.post', $post->slug)" variant="secondary" icon="eye" target="_blank">Mağazada gör</x-button>@endif
    </x-page-header>

    <form method="POST" action="{{ $post->exists ? route('store_admin.blog.update', $post) : route('store_admin.blog.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3">
        @csrf @if ($post->exists) @method('PUT') @endif
        <div class="space-y-6 xl:col-span-2">
            <x-card>
                <div class="space-y-5">
                    <x-field name="title" label="Başlık" :value="$post->title" required maxlength="160" placeholder="ör. Okula dönüş alışveriş listesi" />
                    <x-field name="excerpt" label="Özet" :value="$post->excerpt" textarea maxlength="300" hint="Listede ve başlığın altında görünen 1–2 cümle. Boşsa yazının başından alınır." />
                    <div x-data="markdownToolbar()">
                        <p class="mb-1.5 text-sm font-medium">Yazı <span class="text-danger">*</span></p>
                        <div class="mb-2 flex flex-wrap gap-1.5 text-sm">
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 font-bold hover:bg-slate-50" @click="bold">K</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="heading">Ara başlık</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="bullet">• Madde</button>
                            <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="link">Bağlantı</button>
                        </div>
                        <textarea name="body" rows="20" x-ref="text" class="field-input text-sm leading-relaxed" maxlength="60000" required>{{ old('body', $post->body) }}</textarea>
                        @error('body')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Paragraflar arasında bir boş satır bırakın. Ara başlık için satırın başına “## ”, madde için “- ” yazın.</p>@enderror
                    </div>
                </div>
            </x-card>

            <x-card title="Yazıda geçen ürünler" description="Yazının altında kart olarak gösterilir.">
                <x-field name="product_skus" label="Ürünler (stok kodları)" :value="$skus" textarea rows="4" class="font-mono"
                    hint="Her satıra veya virgülle ayırarak bir stok kodu (SKU); en fazla {{ \App\Models\Store\StoreBlogPost::MAX_PRODUCTS }} ürün." />
            </x-card>

            <x-card title="Arama motorları" description="Boş bırakılırsa başlık ve özet kullanılır.">
                <div class="space-y-5">
                    <x-field name="slug" label="Adres" :value="$post->slug" maxlength="180" hint="Mağazadaki adres: /blog/…  Boşsa başlıktan üretilir." />
                    <x-field name="seo_title" label="SEO başlığı" :value="$post->seo_title" maxlength="70" />
                    <x-field name="seo_description" label="SEO açıklaması" :value="$post->seo_description" textarea maxlength="160" />
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Yayın">
                <div class="space-y-5">
                    <x-toggle name="is_published" label="Yayında" :checked="$post->is_published" />
                    <x-field name="published_at" type="datetime-local" label="Yayın tarihi" :value="$publishedAt" hint="İleri bir tarih seçerseniz yazı o an yayına girer. Boşsa kaydettiğiniz an." />
                    <x-field name="category_id" label="Kategori" :options="$categories" :value="$post->category_id" />
                    <x-button class="w-full justify-center">Kaydet</x-button>
                </div>
            </x-card>
            <x-card title="Kapak fotoğrafı">
                @if ($post->cover_path)
                    <img src="{{ route('store_admin.blog.cover', $post) }}" alt="" class="mb-2 aspect-[16/10] w-full rounded-lg bg-surface object-contain">
                    <x-toggle name="remove_cover" label="Kapağı kaldır" class="mb-2" />
                @endif
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="field-input">
                @error('cover')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Yatay fotoğraf (16:9 veya 16:10). JPG, PNG veya WebP, en fazla 8 MB.</p>@enderror
            </x-card>
            @if ($post->exists)
                <x-card>
                    <button form="sil" class="text-sm text-danger hover:underline">Yazıyı sil</button>
                </x-card>
            @endif
        </div>
    </form>
    @if ($post->exists)
        <form id="sil" method="POST" action="{{ route('store_admin.blog.destroy', $post) }}" data-confirm="Yazı kalıcı olarak silinsin mi?">@csrf @method('DELETE')</form>
    @endif
</x-layouts.panel>
