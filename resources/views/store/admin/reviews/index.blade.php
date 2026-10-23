@php use App\Models\Store\StoreReview; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yorumlar">
    <x-page-header title="Yorumlar" subtitle="Ürünü teslim almış üyelerin yorumları. Yorum siz yayımlayana kadar mağazada görünmez; puan ortalaması yalnızca yayındaki yorumlardan hesaplanır." />

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (StoreReview::STATUSES as $key => $label)
            @php $count = $counts[$key] ?? 0; $active = $status === $key; @endphp
            <a href="{{ route('store_admin.reviews.index', ['status' => $key]) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $active,
                'bg-white text-ink ring-line hover:ring-primary' => ! $active,
            ])>{{ $label }}@if ($count) <span class="ml-1 tabular-nums opacity-80">{{ $count }}</span>@endif</a>
        @endforeach
    </div>

    @if ($reviews->isEmpty())
        <x-card><x-empty-state icon="star" title="Yorum yok" text="Bu durumda yorum bulunmuyor." /></x-card>
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('store.product', $review->product->slug) }}" target="_blank" rel="noopener" class="font-semibold hover:text-primary">{{ $review->product->name }}</a>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                                <span class="font-semibold text-amber-500" aria-label="{{ $review->rating }} yıldız">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                                <span>{{ $review->author_name }}</span>
                                <span>· {{ $review->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span>
                            </p>
                        </div>
                        <x-badge :tone="$review->statusTone()">{{ $review->statusLabel() }}</x-badge>
                    </div>
                    @if ($review->title)<p class="mt-3 font-semibold">{{ $review->title }}</p>@endif
                    <p class="mt-1 text-sm whitespace-pre-line text-slate-700">{{ $review->body }}</p>

                    <form method="POST" action="{{ route('store_admin.reviews.update', $review) }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <x-field name="reply" label="Mağaza yanıtı (isteğe bağlı, yorumun altında görünür)" textarea :value="$review->reply" />
                        <div class="flex flex-wrap gap-2">
                            <x-button size="sm" name="status" value="approved" icon="check-circle">{{ $review->status === 'approved' ? 'Kaydet' : 'Yayımla' }}</x-button>
                            @if ($review->status !== 'rejected')<x-button size="sm" variant="secondary" name="status" value="rejected">Yayımlama</x-button>@endif
                        </div>
                    </form>
                    <form method="POST" action="{{ route('store_admin.reviews.destroy', $review) }}" data-confirm="Yorum kalıcı olarak silinsin mi?" class="mt-2">
                        @csrf @method('DELETE')
                        <button class="text-xs text-danger hover:underline">Sil</button>
                    </form>
                </x-card>
            @endforeach
        </div>
        @if ($reviews->hasPages())<div class="mt-4">{{ $reviews->links() }}</div>@endif
    @endif
</x-layouts.panel>
