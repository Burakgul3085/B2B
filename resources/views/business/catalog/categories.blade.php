<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Kategoriler">
    <x-page-header title="Kategoriler" subtitle="Ürünlerinizi gruplamak için ana ve alt kategoriler." />

    @foreach (['category'] as $key)
        @error($key)<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @endforeach

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Yeni kategori">
            <form method="POST" action="{{ route('business.categories.store') }}" class="space-y-4">
                @csrf
                <x-field name="name" label="Kategori adı" required />
                <x-field name="parent_id" label="Üst kategori" :options="['' => 'Yok (ana kategori)'] + $categories->pluck('name', 'id')->all()" hint="En fazla iki seviye: ana ve alt kategori." />
                <x-field name="sort" label="Sıra" type="number" min="0" value="0" />
                <x-button icon="plus">Ekle</x-button>
            </form>
        </x-card>

        <x-card title="Kategoriler" class="lg:col-span-2" :padding="false">
            @if ($categories->isEmpty())
                <x-empty-state icon="list" title="Henüz kategori yok" text="Ana kategorileri ekleyin; gerekirse alt kategoriler oluşturun." />
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($categories as $category)
                        @include('business.catalog._category-row', ['category' => $category, 'level' => 0])
                        @foreach ($category->children as $child)
                            @include('business.catalog._category-row', ['category' => $child, 'level' => 1])
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.panel>
