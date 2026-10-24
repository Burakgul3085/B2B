{{-- Alt bilgideki küçük e-bülten formu (marka sütununda). Onay kutusu zorunludur; kayıt e-postayla onaylanır. --}}
@php $nlErrors = $errors->getBag('newsletter'); @endphp
<div id="e-bulten" class="mt-8 scroll-mt-24">
    <p class="flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-amber-400 uppercase"><x-icon name="mail" class="size-4" /> E-bülten</p>
    <p class="mt-2 text-sm font-semibold text-white">{{ $title }}</p>
    @if ($text)<p class="mt-0.5 text-xs leading-relaxed text-white/55">{{ $text }}</p>@endif
    @if (session('newsletter'))
        <p class="mt-3 flex items-start gap-2 rounded-xl bg-white/10 px-3 py-2.5 text-xs font-medium text-white ring-1 ring-white/15" role="status">
            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-puzzle-green" /> {{ session('newsletter') }}
        </p>
    @else
        <form method="POST" action="{{ route('store.newsletter.subscribe') }}" class="mt-3">
            @csrf
            <x-store.bot-trap />
            <div class="flex overflow-hidden rounded-xl bg-white ring-1 ring-white/15 focus-within:ring-2 focus-within:ring-amber-400">
                <label for="bulten-eposta" class="sr-only">E-posta adresiniz</label>
                <input id="bulten-eposta" type="email" name="email" value="{{ $nlErrors->any() ? old('email') : '' }}" required autocomplete="email" placeholder="E-posta adresiniz"
                    class="h-10 min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-ink placeholder:text-slate-400 focus:ring-0">
                <button class="h-10 shrink-0 bg-puzzle-orange px-4 text-sm font-bold text-white transition hover:brightness-110">Abone ol</button>
            </div>
            <label class="mt-2 flex items-start gap-2 text-[11px] leading-relaxed text-white/55">
                <input type="checkbox" name="consent" value="1" required class="mt-0.5 size-3.5 shrink-0 rounded border-white/30 bg-white/10 text-puzzle-orange">
                <span>{{ $consent }} <a href="{{ route('store.legal', 'kvkk') }}" class="font-semibold text-white/80 underline">Aydınlatma Metni</a></span>
            </label>
            @foreach ($nlErrors->all() as $message)<p class="mt-1.5 text-xs font-medium text-rose-300">{{ $message }}</p>@endforeach
        </form>
    @endif
</div>
