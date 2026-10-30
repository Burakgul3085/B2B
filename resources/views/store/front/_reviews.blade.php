{{-- Ürün yorumları: yalnızca onaylı yorumlar ve onlardan hesaplanan puan. Form yalnızca ürünü teslim almış üyeye açılır. --}}
<section id="yorumlar" class="mt-14 scroll-mt-32" aria-labelledby="yorumlar-baslik">
    <p class="text-xs font-bold tracking-[0.14em] text-puzzle-yellow uppercase">Müşteri yorumları</p>
    <h2 id="yorumlar-baslik" class="mt-1 font-display text-2xl font-extrabold tracking-tight">Bu ürünü alanlar ne diyor?</h2>

    <div class="mt-6 grid gap-8 lg:grid-cols-[18rem_1fr]">
        <div class="space-y-5">
            <div class="rounded-2xl bg-surface p-5">
                @if ($reviewSummary['count'])
                    <p class="flex items-end gap-2"><span class="font-display text-4xl font-extrabold tabular-nums">{{ number_format($reviewSummary['average'], 1, ',', '') }}</span><span class="pb-1.5 text-sm text-muted">/ 5</span></p>
                    <div class="mt-1">@include('store.front._stars', ['rating' => $reviewSummary['average'], 'size' => 'size-5'])</div>
                    <p class="mt-1 text-sm text-muted">{{ $reviewSummary['count'] }} değerlendirme</p>
                    <ul class="mt-4 space-y-1.5 text-xs">
                        @foreach ($reviewSummary['bars'] as $star => $count)
                            <li class="flex items-center gap-2">
                                <span class="w-3 text-right tabular-nums">{{ $star }}</span>
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full bg-puzzle-yellow" style="width: {{ round($count / $reviewSummary['count'] * 100) }}%"></span></span>
                                <span class="w-6 text-right text-muted tabular-nums">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm font-semibold">Henüz yorum yok</p>
                    <p class="mt-1 text-sm text-muted">Bu ürünü satın alıp teslim aldıysanız ilk yorumu siz yazın.</p>
                @endif
            </div>

            @if (session('review_sent'))
                <p class="rounded-xl bg-success-soft px-4 py-3 text-sm text-success" role="status">Teşekkürler! Yorumunuz incelendikten sonra yayımlanacak.</p>
            @elseif ($reviewEligibility === 'allowed')
                <form method="POST" action="{{ route('store.review.store', $product->slug) }}" enctype="multipart/form-data" class="space-y-4 rounded-2xl border border-line p-5" x-data="starRating(@alpine((int) old('rating', 0)))">
                    @csrf
                    <p class="text-sm font-semibold">Yorum yazın</p>
                    <div>
                        <input type="hidden" name="rating" :value="value || ''">
                        <div class="flex gap-1" role="radiogroup" aria-label="Puan" @mouseleave="hover = 0">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" role="radio" :aria-checked="value === {{ $i }}" @click="set({{ $i }})" @mouseenter="hover = {{ $i }}" aria-label="{{ $i }} yıldız"
                                    :class="lit({{ $i }}) ? 'text-puzzle-yellow' : 'text-line'" class="transition">
                                    <svg viewBox="0 0 24 24" class="size-7" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.55.95-4.75 4.6 1.12 6.55L12 17.6l-5.87 3.1 1.12-6.55L2.5 9.55l6.55-.95z"/></svg>
                                </button>
                            @endfor
                        </div>
                        @error('rating')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-field name="title" label="Başlık (isteğe bağlı)" :value="old('title')" />
                    <x-field name="body" label="Yorumunuz" textarea :value="old('body')" required />
                    <div>
                        <label for="yorum_foto" class="mb-1.5 block text-sm font-medium">Fotoğraflar (isteğe bağlı)</label>
                        <input id="yorum_foto" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                        <p class="mt-1 text-xs text-muted">En fazla {{ \App\Models\Store\StoreReview::MAX_PHOTOS }} fotoğraf (JPG, PNG).</p>
                        @error('photos')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        @if ($errors->has('photos.*'))<p class="mt-1 text-xs text-danger">{{ collect($errors->get('photos.*'))->flatten()->first() }}</p>@endif
                    </div>
                    <p class="text-xs text-muted">Yorumunuz adınız ve soyadınızın baş harfiyle ({{ \App\Domain\Store\Reviews\ReviewService::displayName(auth('customer')->user()) }}) onaydan sonra yayımlanır.</p>
                    <x-button class="w-full">Gönder</x-button>
                </form>
            @elseif ($reviewEligibility === 'guest')
                <p class="text-sm text-muted">Yorum yazmak için <a href="{{ route('store.login') }}" class="font-semibold text-navy-ink underline">giriş yapın</a>. Yalnızca ürünü satın alıp teslim alan müşterilerimiz yorum yazabilir.</p>
            @elseif ($reviewEligibility === 'reviewed')
                <p class="text-sm text-muted">Bu ürün için yorumunuzu aldık, teşekkürler. Yorumunuzu <a href="{{ route('store.reviews') }}" class="font-semibold text-navy-ink underline">Değerlendirmelerim</a> sayfasında görebilirsiniz.</p>
            @else
                <p class="text-sm text-muted">Yalnızca bu ürünü satın alıp teslim alan müşterilerimiz yorum yazabilir.</p>
            @endif
        </div>

        <div>
            @forelse ($reviews as $review)
                <article class="border-b border-line py-5 first:pt-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @include('store.front._stars', ['rating' => $review->rating])
                        <span class="text-sm font-semibold">{{ $review->author_name }}</span>
                        <span class="inline-flex items-center gap-1 text-xs text-success"><x-icon name="check-circle" class="size-3.5" /> Satın aldı</span>
                        <time class="text-xs text-muted" datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</time>
                    </div>
                    @if ($review->title)<h3 class="mt-2 font-semibold">{{ $review->title }}</h3>@endif
                    <p class="mt-1.5 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $review->body }}</p>
                    @if ($review->photos->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($review->photos as $photo)
                                <a href="{{ route('store.review-photo', $photo->id) }}" target="_blank" rel="noopener" class="block size-20 overflow-hidden rounded-xl ring-1 ring-line transition hover:ring-navy">
                                    <img src="{{ route('store.review-photo', $photo->id) }}" alt="{{ $review->author_name }} fotoğrafı {{ $loop->iteration }}" loading="lazy" class="size-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($review->reply)
                        <div class="mt-3 rounded-xl bg-surface px-4 py-3 text-sm">
                            <p class="text-xs font-bold text-navy-ink">{{ config('store.name') }} yanıtı</p>
                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ $review->reply }}</p>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-line px-6 py-10 text-center text-sm text-muted">Bu ürün için henüz yayımlanmış yorum yok.</div>
            @endforelse
        </div>
    </div>
</section>
