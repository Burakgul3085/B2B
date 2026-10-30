{{--
    Ürün sayfası: Değerlendirmeler ve Soru & Cevap (sekmeli). Yalnızca onaylı yorumlar ve onlardan hesaplanan puan; yorum
    formu yalnızca ürünü teslim almış üyeye açılır. Sorular cevaplanınca cevabıyla birlikte yayımlanır; soranın e-postası gösterilmez.
    Fotoğraflar tek galeride (photoGallery) büyütülür: üstteki müşteri fotoğrafları şeridi + listedeki yorumların fotoğrafları.
--}}
@php
    use App\Http\Controllers\Store\Front\ProductController;
    use App\Models\Store\StoreReview;

    $productUrl = route('store.product', $product->slug);
    $reviewUrl = fn (array $q) => $productUrl.'?'.http_build_query(array_filter($q + ['yorum_sirala' => $reviewFilters['sort'] === 'yeni' ? null : $reviewFilters['sort'], 'yildiz' => $reviewFilters['star']])).'#yorumlar';

    // Galeri: şeritteki ve listedeki tüm fotoğraflar, fotoğraf kimliği → sıra.
    $gallery = [];
    $galleryIndex = [];
    $addPhoto = function ($photo, $review) use (&$gallery, &$galleryIndex) {
        if (! isset($galleryIndex[$photo->id])) {
            $galleryIndex[$photo->id] = count($gallery);
            $gallery[] = ['src' => route('store.review-photo', $photo->id), 'caption' => $review->author_name.' · '.str_repeat('★', $review->rating)];
        }
    };
    foreach ($reviewPhotos as $photo) { $addPhoto($photo, $photo->review); }
    foreach ($reviews as $review) { foreach ($review->photos as $photo) { $addPhoto($photo, $review); } }

    $questionFields = ['first_name', 'last_name', 'email', 'question', 'consent'];
    $openQuestions = session('question_sent') || collect($questionFields)->contains(fn ($f) => $errors->has($f));
    $me = auth('customer')->user();
    $initials = fn (string $name) => mb_strtoupper(collect(preg_split('/\s+/u', trim($name)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''));
    $tones = ['bg-puzzle-orange-soft text-puzzle-orange', 'bg-puzzle-blue-soft text-puzzle-blue', 'bg-puzzle-green-soft text-puzzle-green', 'bg-puzzle-yellow-soft text-puzzle-yellow'];
@endphp
<section id="yorumlar" class="mt-16 scroll-mt-32" x-data="feedbackTabs(@alpine($openQuestions ? 'sorular' : 'yorumlar'))" aria-label="Müşteri değerlendirmeleri ve sorular">
    <span id="sorular" class="block scroll-mt-32" aria-hidden="true"></span>

    {{-- Başlık ve sekmeler --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-bold tracking-[0.18em] text-puzzle-yellow uppercase">Müşteri deneyimleri</p>
            <h2 class="mt-1 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Değerlendirmeler ve sorular</h2>
        </div>
        <div class="inline-flex rounded-2xl bg-surface p-1 ring-1 ring-line" role="tablist">
            <button type="button" role="tab" @click="select('yorumlar')" :aria-selected="is('yorumlar')"
                :class="is('yorumlar') ? 'bg-white text-ink shadow-sm ring-1 ring-line' : 'text-muted hover:text-ink'"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition">
                <x-icon name="star" class="size-4" /> Değerlendirmeler <span class="rounded-full bg-navy/5 px-2 py-0.5 text-xs tabular-nums">{{ $reviewSummary['count'] }}</span>
            </button>
            <button type="button" role="tab" @click="select('sorular')" :aria-selected="is('sorular')"
                :class="is('sorular') ? 'bg-white text-ink shadow-sm ring-1 ring-line' : 'text-muted hover:text-ink'"
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition">
                <x-icon name="message-circle" class="size-4" /> Soru & Cevap <span class="rounded-full bg-navy/5 px-2 py-0.5 text-xs tabular-nums">{{ $questionCount }}</span>
            </button>
        </div>
    </div>

    {{-- ===================== Değerlendirmeler ===================== --}}
    <div x-show="is('yorumlar')" @if ($openQuestions) x-cloak @endif role="tabpanel" class="mt-8"
        x-data="photoGallery(@alpine($gallery))" @keydown.window.escape="close()" @keydown.window.arrow-right="next()" @keydown.window.arrow-left="prev()">
        <div class="grid gap-8 lg:grid-cols-[20rem_minmax(0,1fr)]">
            {{-- Özet --}}
            <aside class="space-y-4 lg:sticky lg:top-32 lg:self-start">
                <div class="overflow-hidden rounded-3xl border border-line bg-white">
                    <div class="bg-[#0b1631] px-6 py-6 text-white">
                        @if ($reviewSummary['count'])
                            <div class="flex items-end gap-3">
                                <span class="font-display text-5xl leading-none font-extrabold tabular-nums">{{ number_format($reviewSummary['average'], 1, ',', '') }}</span>
                                <span class="pb-1 text-sm text-white/60">/ 5</span>
                            </div>
                            <div class="mt-3">@include('store.front._stars', ['rating' => $reviewSummary['average'], 'size' => 'size-5'])</div>
                            <p class="mt-2 text-sm text-white/70">{{ $reviewSummary['count'] }} doğrulanmış değerlendirme</p>
                        @else
                            <p class="font-display text-xl font-extrabold">Henüz değerlendirme yok</p>
                            <p class="mt-1 text-sm text-white/70">Bu ürünü satın alıp teslim aldıysanız ilk değerlendirmeyi siz yapın.</p>
                        @endif
                    </div>
                    @if ($reviewSummary['count'])
                        <div class="space-y-4 p-6">
                            <p class="flex items-center gap-2 rounded-2xl bg-puzzle-green-soft px-3.5 py-2.5 text-sm font-semibold text-puzzle-green">
                                <x-icon name="check-circle" class="size-4 shrink-0" /> Tavsiye oranı %{{ $reviewSummary['recommend'] }} <span class="font-normal opacity-80">(4–5 yıldız)</span>
                            </p>
                            <ul class="space-y-1.5">
                                @foreach ($reviewSummary['bars'] as $star => $count)
                                    @php $pct = round($count / $reviewSummary['count'] * 100); $active = $reviewFilters['star'] === $star; @endphp
                                    <li>
                                        <a href="{{ $count ? $reviewUrl(['yildiz' => $active ? null : $star]) : '#yorumlar' }}"
                                            @class(['group flex items-center gap-3 rounded-lg px-1.5 py-1 text-xs transition', 'bg-puzzle-yellow-soft' => $active, 'hover:bg-surface' => $count && ! $active, 'pointer-events-none opacity-50' => ! $count])
                                            @if ($count) aria-label="{{ $star }} yıldızlı {{ $count }} değerlendirmeyi göster" @endif>
                                            <span class="flex w-8 items-center gap-0.5 font-semibold tabular-nums">{{ $star }} <x-icon name="star" class="size-3 text-puzzle-yellow" /></span>
                                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full bg-puzzle-yellow" style="width: {{ $pct }}%"></span></span>
                                            <span class="w-8 text-right text-muted tabular-nums">{{ $count }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Yorum yazma --}}
                @if (session('review_sent'))
                    <p class="flex gap-2 rounded-2xl bg-success-soft px-4 py-3 text-sm text-success" role="status"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" /> Teşekkürler! Değerlendirmeniz incelendikten sonra yayımlanacak.</p>
                @elseif ($reviewEligibility === 'allowed')
                    <details class="group rounded-3xl border border-line bg-white" @if ($errors->has('rating') || $errors->has('body') || $errors->has('photos')) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5">
                            <span><span class="block font-semibold">Değerlendirme yazın</span><span class="block text-xs text-muted">Puan verin, isterseniz fotoğraf ekleyin</span></span>
                            <span class="flex size-9 items-center justify-center rounded-xl bg-navy text-white transition group-open:rotate-45"><x-icon name="plus" class="size-4" /></span>
                        </summary>
                        <form method="POST" action="{{ route('store.review.store', $product->slug) }}" enctype="multipart/form-data" class="space-y-4 border-t border-line p-5" x-data="starRating(@alpine((int) old('rating', 0)))">
                            @csrf
                            <div>
                                <input type="hidden" name="rating" :value="value || ''">
                                <div class="flex gap-1" role="radiogroup" aria-label="Puan" @mouseleave="hover = 0">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <button type="button" role="radio" :aria-checked="value === {{ $i }}" @click="set({{ $i }})" @mouseenter="hover = {{ $i }}" aria-label="{{ $i }} yıldız"
                                            :class="lit({{ $i }}) ? 'text-puzzle-yellow' : 'text-line'" class="transition hover:scale-110">
                                            <svg viewBox="0 0 24 24" class="size-8" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.55.95-4.75 4.6 1.12 6.55L12 17.6l-5.87 3.1 1.12-6.55L2.5 9.55l6.55-.95z"/></svg>
                                        </button>
                                    @endfor
                                </div>
                                @error('rating')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <x-field name="title" label="Başlık (isteğe bağlı)" :value="old('title')" maxlength="80" />
                            <x-field name="body" label="Değerlendirmeniz" textarea :value="old('body')" required maxlength="2000" />
                            <div>
                                <label for="yorum_foto" class="mb-1.5 block text-sm font-medium">Fotoğraflar (isteğe bağlı)</label>
                                <input id="yorum_foto" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                                <p class="mt-1 text-xs text-muted">En fazla {{ StoreReview::MAX_PHOTOS }} fotoğraf (JPG, PNG).</p>
                                @error('photos')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                                @if ($errors->has('photos.*'))<p class="mt-1 text-xs text-danger">{{ collect($errors->get('photos.*'))->flatten()->first() }}</p>@endif
                            </div>
                            <p class="text-xs text-muted">{{ \App\Domain\Store\Reviews\ReviewService::displayName($me) }} adıyla, onaydan sonra yayımlanır.</p>
                            <x-button class="w-full">Gönder</x-button>
                        </form>
                    </details>
                @else
                    <div class="flex gap-3 rounded-3xl border border-dashed border-line p-5 text-sm text-muted">
                        <x-icon name="award" class="mt-0.5 size-5 shrink-0 text-navy-ink" />
                        <p>
                            @if ($reviewEligibility === 'guest') Değerlendirme yazmak için <a href="{{ route('store.login') }}" class="font-semibold text-navy-ink underline">giriş yapın</a>. Yalnızca ürünü satın alıp teslim alan müşterilerimiz değerlendirebilir; bu yüzden her yorum doğrulanmış bir alışverişe aittir.
                            @elseif ($reviewEligibility === 'reviewed') Bu ürün için değerlendirmenizi aldık, teşekkürler. <a href="{{ route('store.reviews') }}" class="font-semibold text-navy-ink underline">Değerlendirmelerim</a>
                            @else Yalnızca bu ürünü satın alıp teslim alan müşterilerimiz değerlendirebilir; bu yüzden her yorum doğrulanmış bir alışverişe aittir. @endif
                        </p>
                    </div>
                @endif
            </aside>

            <div class="min-w-0">
                {{-- Müşteri fotoğrafları --}}
                @if ($reviewPhotos->isNotEmpty())
                    <div class="mb-6">
                        <p class="text-sm font-semibold">Müşteri fotoğrafları <span class="font-normal text-muted">({{ $reviewPhotos->count() }})</span></p>
                        <div class="mt-3 flex gap-2.5 overflow-x-auto pb-2">
                            @foreach ($reviewPhotos as $photo)
                                <button type="button" @click="open({{ $galleryIndex[$photo->id] }})" class="group relative size-24 shrink-0 overflow-hidden rounded-2xl ring-1 ring-line transition hover:ring-2 hover:ring-navy sm:size-28" aria-label="Fotoğrafı büyüt">
                                    <img src="{{ route('store.review-photo', $photo->id) }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Sıralama / filtre --}}
                @if ($reviewSummary['count'])
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                        <p class="text-sm text-muted">
                            {{ $reviewFilters['total'] }} değerlendirme
                            @if ($reviewFilters['star'])
                                · <span class="font-semibold text-ink">{{ $reviewFilters['star'] }} yıldız</span>
                                <a href="{{ $reviewUrl(['yildiz' => null]) }}" class="ml-1 font-semibold text-navy-ink underline">Filtreyi kaldır</a>
                            @endif
                        </p>
                        <nav class="flex flex-wrap gap-1.5" aria-label="Sıralama">
                            @foreach (ProductController::REVIEW_SORTS as $key => $label)
                                <a href="{{ $reviewUrl(['yorum_sirala' => $key === 'yeni' ? null : $key]) }}" @class(['rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition',
                                    'bg-navy text-white ring-navy' => $reviewFilters['sort'] === $key, 'bg-white text-ink/70 ring-line hover:text-ink hover:ring-navy/40' => $reviewFilters['sort'] !== $key])>
                                    @if ($key === 'fotografli')<x-icon name="image" class="-mt-0.5 mr-0.5 inline size-3.5" />@endif{{ $label }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endif

                {{-- Yorumlar --}}
                <div class="divide-y divide-line">
                    @forelse ($reviews as $review)
                        <article class="py-6">
                            <div class="flex items-start gap-4">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl font-display text-sm font-extrabold {{ $tones[$review->id % 4] }}">{{ $initials($review->author_name) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="font-semibold">{{ $review->author_name }}</span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-puzzle-green-soft px-2 py-0.5 text-[11px] font-semibold text-puzzle-green"><x-icon name="check-circle" class="size-3" /> Doğrulanmış alışveriş</span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-muted">
                                        @include('store.front._stars', ['rating' => $review->rating, 'size' => 'size-4'])
                                        <time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->timezone('Europe/Istanbul')->translatedFormat('j F Y') }}</time>
                                    </div>
                                    @if ($review->title)<h3 class="mt-3 font-display text-base font-extrabold">{{ $review->title }}</h3>@endif
                                    <p class="mt-1.5 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $review->body }}</p>
                                    @if ($review->photos->isNotEmpty())
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach ($review->photos as $photo)
                                                <button type="button" @click="open({{ $galleryIndex[$photo->id] }})" class="group size-20 overflow-hidden rounded-xl ring-1 ring-line transition hover:ring-2 hover:ring-navy" aria-label="{{ $review->author_name }} fotoğrafı {{ $loop->iteration }}">
                                                    <img src="{{ route('store.review-photo', $photo->id) }}" alt="{{ $review->author_name }} fotoğrafı {{ $loop->iteration }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($review->reply)
                                        <div class="mt-4 flex gap-3 rounded-2xl border-l-4 border-navy bg-surface px-4 py-3">
                                            <span class="mt-0.5 shrink-0"><x-puzzle-logo :animated="false" class="size-6" /></span>
                                            <div class="min-w-0 text-sm">
                                                <p class="text-xs font-bold text-navy-ink">{{ config('store.name') }} yanıtı</p>
                                                <p class="mt-1 whitespace-pre-line text-slate-700">{{ $review->reply }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="my-6 flex flex-col items-center rounded-3xl border border-dashed border-line px-6 py-12 text-center">
                            <span class="flex size-14 items-center justify-center rounded-2xl bg-puzzle-yellow-soft text-puzzle-yellow"><x-icon name="star" class="size-7" /></span>
                            <p class="mt-3 font-semibold">{{ $reviewFilters['star'] || $reviewFilters['sort'] === 'fotografli' ? 'Bu seçime uyan değerlendirme yok' : 'Bu ürün için henüz değerlendirme yok' }}</p>
                            <p class="mt-1 text-sm text-muted">Değerlendirmeler yalnızca ürünü teslim alan müşterilerimizden gelir.</p>
                        </div>
                    @endforelse
                </div>

                @if ($reviewFilters['total'] > $reviews->count())
                    <div class="mt-2 text-center">
                        <a href="{{ $reviewUrl(['yorum_sayisi' => $reviewFilters['limit'] + $reviewFilters['page']]) }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line bg-white px-5 text-sm font-semibold transition hover:border-navy hover:bg-navy hover:text-white">
                            Daha fazla değerlendirme göster <span class="text-xs opacity-70">({{ $reviewFilters['total'] - $reviews->count() }})</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
        @include('store.front._photo-lightbox')
    </div>

    {{-- ===================== Soru & Cevap ===================== --}}
    <div x-show="is('sorular')" @unless ($openQuestions) x-cloak @endunless role="tabpanel" class="mt-8">
        <div class="grid gap-8 lg:grid-cols-[20rem_minmax(0,1fr)]">
            {{-- Telefonda önce cevaplanan sorular, sonra form (gönderim sonrası / hatada form önde) --}}
            <aside @class(['lg:sticky lg:top-32 lg:order-none lg:self-start', 'order-last' => ! $openQuestions])>
                <div class="overflow-hidden rounded-3xl border border-line bg-white">
                    <div class="bg-[#0b1631] px-6 py-5 text-white">
                        <p class="flex items-center gap-2 font-display text-lg font-extrabold"><x-icon name="message-circle" class="size-5 text-puzzle-yellow" /> Satıcıya sorun</p>
                        <p class="mt-1 text-sm text-white/70">Üye olmanız gerekmez. Cevabımız e-postanıza gelir ve sorunuzla birlikte bu sayfada yayımlanır.</p>
                    </div>
                    @if (session('question_sent'))
                        <div class="flex gap-3 p-6 text-sm" role="status">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-puzzle-green-soft text-puzzle-green"><x-icon name="check-circle" class="size-5" /></span>
                            <p><span class="block font-semibold text-ink">Sorunuz bize ulaştı</span><span class="text-muted">En kısa sürede cevaplayıp e-postanıza göndereceğiz. Cevaplanan soru bu sayfada yayımlanır.</span></p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('store.question.store', $product->slug) }}" class="space-y-4 p-6">
                            @csrf
                            <x-store.bot-trap />
                            <div class="grid grid-cols-2 gap-3">
                                <x-field name="first_name" label="Ad" :value="old('first_name', $me?->first_name)" required maxlength="60" autocomplete="given-name" />
                                <x-field name="last_name" label="Soyad" :value="old('last_name', $me?->last_name)" required maxlength="60" autocomplete="family-name" />
                            </div>
                            <x-field name="email" label="E-posta" type="email" :value="old('email', $me?->email)" required maxlength="190" autocomplete="email" hint="Sitede gösterilmez; yalnızca cevabı göndermek için." />
                            <x-field name="question" label="Sorunuz" textarea :value="old('question')" required maxlength="1000" placeholder="Ürünle ilgili merak ettiğinizi yazın" />
                            <label class="flex gap-2.5 text-xs leading-relaxed text-muted">
                                <input type="checkbox" name="consent" value="1" @checked(old('consent')) class="mt-0.5 size-4 shrink-0 rounded text-navy" required>
                                <span>Cevabın e-postama gönderilmesini ve sorumun cevabıyla birlikte adım (ör. Ayşe K.) ile yayımlanmasını kabul ediyorum. <a href="{{ route('store.legal', 'kvkk') }}" target="_blank" class="underline">KVKK Aydınlatma Metni</a></span>
                            </label>
                            @error('consent')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                            <x-button class="w-full" icon="message-circle">Soruyu gönder</x-button>
                        </form>
                    @endif
                </div>
            </aside>

            <div class="min-w-0">
                @forelse ($questions as $q)
                    <article @class(['py-6', 'border-t border-line' => ! $loop->first, 'pt-0' => $loop->first])>
                        <div class="flex gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-puzzle-blue-soft font-display text-sm font-extrabold text-puzzle-blue">S</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-relaxed font-semibold whitespace-pre-line text-ink">{{ $q->question }}</p>
                                <p class="mt-1 text-xs text-muted">{{ $q->displayName() }} · {{ $q->created_at->timezone('Europe/Istanbul')->translatedFormat('j F Y') }}</p>
                                <div class="mt-4 flex gap-3 rounded-2xl border-l-4 border-navy bg-surface px-4 py-3">
                                    <span class="mt-0.5 shrink-0"><x-puzzle-logo :animated="false" class="size-6" /></span>
                                    <div class="min-w-0 text-sm">
                                        <p class="text-xs font-bold text-navy-ink">{{ config('store.name') }} · <span class="font-medium text-muted">{{ $q->answered_at?->timezone('Europe/Istanbul')->translatedFormat('j F Y') }}</span></p>
                                        <p class="mt-1 leading-relaxed whitespace-pre-line text-slate-700">{{ $q->answer }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="flex flex-col items-center rounded-3xl border border-dashed border-line px-6 py-12 text-center">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-puzzle-blue-soft text-puzzle-blue"><x-icon name="message-circle" class="size-7" /></span>
                        <p class="mt-3 font-semibold">Bu ürün hakkında henüz soru yok</p>
                        <p class="mt-1 text-sm text-muted">Merak ettiğinizi sorun; cevabımızı e-postanıza gönderelim.</p>
                    </div>
                @endforelse

                @if ($questionCount > $questions->count())
                    <div class="mt-2 text-center">
                        <a href="{{ $productUrl.'?soru_sayisi='.($questionLimit + 10).'#sorular' }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-line bg-white px-5 text-sm font-semibold transition hover:border-navy hover:bg-navy hover:text-white">
                            Daha fazla soru göster <span class="text-xs opacity-70">({{ $questionCount - $questions->count() }})</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
