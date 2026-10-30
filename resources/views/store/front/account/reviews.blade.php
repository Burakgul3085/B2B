{{--
    Hesabım → Değerlendirmelerim: teslim alınan ve henüz yorumlanmayan ürünler (fotoğraflı yorum formu) ve üyenin yorumları.
    Formlar aynı alan adlarını kullandığı için hata ve eski değerler yalnızca gönderilen ürünün formunda gösterilir (product_form).
--}}
@php use App\Models\Store\StoreReview; $sent = (int) old('product_form'); @endphp
<x-store.account-shell title="Değerlendirmelerim" active="reviews">
    <p class="text-xs font-bold tracking-[0.18em] text-puzzle-orange uppercase">Hesabım</p>
    <h1 class="mt-1 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Değerlendirmelerim</h1>
    <p class="mt-1 text-muted">Teslim aldığınız ürünlere puan verin, isterseniz fotoğraf ekleyin. Yorumlar incelendikten sonra ürün sayfasında yayımlanır.</p>

    @if (session('status'))<p class="mt-6 rounded-2xl bg-success-soft px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</p>@endif

    {{-- Değerlendirme bekleyenler --}}
    <section class="mt-8" aria-labelledby="bekleyen">
        <h2 id="bekleyen" class="font-display text-xl font-extrabold">Değerlendirmenizi bekleyen ürünler <span class="text-base font-semibold text-muted tabular-nums">({{ $awaiting->count() }})</span></h2>
        @if ($awaiting->isEmpty())
            <div class="mt-4 rounded-3xl border border-dashed border-line px-6 py-10 text-center text-sm text-muted">
                Değerlendirilecek ürün yok. Siparişleriniz teslim edildiğinde ürünler burada listelenir.
            </div>
        @else
            <div class="mt-4 space-y-4">
                @foreach ($awaiting as $product)
                    @php $mine = $sent === $product->id; @endphp
                    <details class="group rounded-3xl border border-line bg-white" @if ($mine || ($loop->first && ! $sent)) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-4 p-4 sm:p-5">
                            <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#fff] ring-1 ring-line">
                                @if ($product->primaryImage)<img src="{{ route('store.media.product', ['storeImage' => $product->primaryImage, 'w' => 160]) }}" alt="" class="size-full object-contain p-1">@else<x-icon name="image" class="size-6 text-slate-300" />@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $product->name }}</span>
                                <span class="block text-sm text-muted">Puan verin ve yorum yazın</span>
                            </span>
                            <x-icon name="chevron-down" class="size-5 shrink-0 text-muted transition group-open:rotate-180" />
                        </summary>
                        <form method="POST" action="{{ route('store.review.store', $product->slug) }}" enctype="multipart/form-data" class="space-y-4 border-t border-line p-4 sm:p-5"
                            x-data="starRating(@alpine($mine ? (int) old('rating', 0) : 0))">
                            @csrf
                            <input type="hidden" name="from" value="account">
                            <input type="hidden" name="product_form" value="{{ $product->id }}">
                            <div>
                                <p class="text-sm font-medium">Puanınız</p>
                                <input type="hidden" name="rating" :value="value || ''">
                                <div class="mt-1 flex gap-1" role="radiogroup" aria-label="Puan" @mouseleave="hover = 0">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <button type="button" role="radio" :aria-checked="value === {{ $i }}" @click="set({{ $i }})" @mouseenter="hover = {{ $i }}" aria-label="{{ $i }} yıldız"
                                            :class="lit({{ $i }}) ? 'text-puzzle-yellow' : 'text-line'" class="transition">
                                            <svg viewBox="0 0 24 24" class="size-8" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.55.95-4.75 4.6 1.12 6.55L12 17.6l-5.87 3.1 1.12-6.55L2.5 9.55l6.55-.95z"/></svg>
                                        </button>
                                    @endfor
                                </div>
                                @if ($mine)@error('rating')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror @endif
                            </div>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">Başlık (isteğe bağlı)</span>
                                <input name="title" maxlength="80" value="{{ $mine ? old('title') : '' }}" class="field-input" placeholder="ör. Çok kaliteli, tavsiye ederim">
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">Yorumunuz</span>
                                <textarea name="body" rows="4" maxlength="2000" required class="field-input" placeholder="Ürünü nasıl buldunuz? (en az 10 karakter)">{{ $mine ? old('body') : '' }}</textarea>
                                @if ($mine)@error('body')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror @endif
                            </label>
                            <div>
                                <label for="foto_{{ $product->id }}" class="mb-1.5 block text-sm font-medium">Fotoğraflar (isteğe bağlı)</label>
                                <input id="foto_{{ $product->id }}" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                                <p class="mt-1 text-xs text-muted">En fazla {{ StoreReview::MAX_PHOTOS }} fotoğraf (JPG, PNG; her biri en fazla 10 MB).</p>
                                @if ($mine)
                                    @error('photos')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                                    @if ($errors->has('photos.*'))<p class="mt-1 text-xs text-danger">{{ collect($errors->get('photos.*'))->flatten()->first() }}</p>@endif
                                @endif
                            </div>
                            <p class="text-xs text-muted">Yorumunuz adınız ve soyadınızın baş harfiyle ({{ \App\Domain\Store\Reviews\ReviewService::displayName(auth('customer')->user()) }}) onaydan sonra yayımlanır.</p>
                            <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-navy px-5 text-sm font-semibold text-white transition hover:bg-puzzle-orange">Değerlendirmeyi gönder</button>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Yorumlarım --}}
    <section class="mt-12" aria-labelledby="yorumlarim">
        <h2 id="yorumlarim" class="font-display text-xl font-extrabold">Yorumlarım <span class="text-base font-semibold text-muted tabular-nums">({{ $reviews->count() }})</span></h2>
        @if ($reviews->isEmpty())
            <p class="mt-4 text-sm text-muted">Henüz yorum yazmadınız.</p>
        @else
            <div class="mt-4 space-y-4">
                @foreach ($reviews as $review)
                    <article class="rounded-3xl border border-line bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if ($review->product)
                                    <a href="{{ route('store.product', $review->product->slug) }}#yorumlar" class="font-semibold hover:text-navy-ink">{{ $review->product->name }}</a>
                                @endif
                                <p class="mt-1 flex items-center gap-2 text-xs text-muted">@include('store.front._stars', ['rating' => $review->rating]) {{ $review->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</p>
                            </div>
                            <x-badge :tone="$review->statusTone()">{{ $review->statusLabel() }}</x-badge>
                        </div>
                        @if ($review->title)<p class="mt-3 font-semibold">{{ $review->title }}</p>@endif
                        <p class="mt-1 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $review->body }}</p>
                        @if ($review->photos->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($review->photos as $photo)
                                    <a href="{{ route('store.review-photo', $photo->id) }}" target="_blank" rel="noopener" class="block size-16 overflow-hidden rounded-lg ring-1 ring-line"><img src="{{ route('store.review-photo', $photo->id) }}" alt="Yorum fotoğrafı" loading="lazy" class="size-full object-cover"></a>
                                @endforeach
                            </div>
                        @endif
                        @if ($review->status === 'pending')<p class="mt-3 text-xs text-muted">Yorumunuz inceleniyor; onaylandığında ürün sayfasında yayımlanır.</p>@endif
                        @if ($review->reply)
                            <div class="mt-3 rounded-xl bg-surface px-4 py-3 text-sm">
                                <p class="text-xs font-bold text-navy-ink">{{ config('store.name') }} yanıtı</p>
                                <p class="mt-1 whitespace-pre-line text-slate-700">{{ $review->reply }}</p>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-store.account-shell>
