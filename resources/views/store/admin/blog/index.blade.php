<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Blog">
    <x-page-header title="Blog" subtitle="Mağazanın blog yazıları. Yayındaki yazılar /blog adresinde, ana sayfanın “Blogdan” bölümünde ve site haritasında görünür.">
        <x-button :href="route('store_admin.blog.create')" icon="plus">Yeni yazı</x-button>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-card :padding="false">
                @if ($posts->isEmpty())
                    <x-empty-state icon="book-open" title="Henüz yazı yok" text="İlk yazınızı ekleyin: ör. “Okula dönüş alışveriş listesi”." />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>Yazı</th><th>Kategori</th><th>Durum</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($posts as $post)
                                    <tr>
                                        <td>
                                            <a href="{{ route('store_admin.blog.edit', $post) }}" class="font-semibold hover:text-primary">{{ $post->title }}</a>
                                            <p class="text-xs text-muted">{{ $post->published_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Tarih yok' }}</p>
                                        </td>
                                        <td class="text-sm">{{ $post->category?->name ?? '—' }}</td>
                                        <td>
                                            @if ($post->isLive())<x-badge tone="success">Yayında</x-badge>
                                            @elseif ($post->is_published)<x-badge tone="info">Zamanlandı</x-badge>
                                            @else<x-badge>Taslak</x-badge>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($posts->hasPages())<div class="border-t border-line p-4">{{ $posts->links() }}</div>@endif
                @endif
            </x-card>
        </div>

        <x-card title="Kategoriler" description="Sırayla yazın; adını boşaltıp kaydettiğiniz kategori silinir (yazıları kategorisiz kalır).">
            <form method="POST" action="{{ route('store_admin.blog.categories') }}" class="space-y-3">
                @csrf @method('PUT')
                @foreach ($categories as $i => $category)
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="categories[{{ $i }}][id]" value="{{ $category->id }}">
                        <input name="categories[{{ $i }}][name]" value="{{ $category->name }}" maxlength="60" class="field-input" aria-label="Kategori adı">
                        <span class="w-14 shrink-0 text-right text-xs text-muted tabular-nums">{{ $category->posts_count }} yazı</span>
                    </div>
                @endforeach
                @for ($j = 0; $j < 2; $j++)
                    <input name="categories[{{ $categories->count() + $j }}][name]" maxlength="60" class="field-input" placeholder="Yeni kategori (ör. Okul)" aria-label="Yeni kategori adı">
                @endfor
                @error('rows.*.name')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                <x-button size="sm" class="w-full justify-center">Kategorileri kaydet</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
