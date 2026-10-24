{{-- Alt bilgideki e-bülten kartı (bağlantı sütunlarının altında, ortada). Onay kutusu zorunludur; kayıt e-postayla onaylanır. --}}
@php $nlErrors = $errors->getBag('newsletter'); @endphp
<div id="e-bulten" class="mt-12 scroll-mt-24 rounded-2xl bg-gradient-to-br from-white/[0.07] to-white/[0.02] p-5 ring-1 ring-white/10 sm:p-6">
    <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] md:items-center">
        <div class="flex items-start gap-3.5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-400 text-[#0b1631] shadow-lg shadow-black/20"><x-icon name="mail" class="size-5" /></span>
            <div class="min-w-0">
                <p class="text-[11px] font-bold tracking-[0.2em] text-amber-400 uppercase">E-bülten</p>
                <p class="mt-1 leading-snug font-bold text-white">{{ $title }}</p>
                @if ($text)<p class="mt-1 text-xs leading-relaxed text-white/55">{{ $text }}</p>@endif
            </div>
        </div>
        @if (session('newsletter'))
            <p class="flex items-start gap-2 rounded-xl bg-white/10 px-4 py-3 text-sm font-medium text-white" role="status">
                <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-puzzle-green" /> {{ session('newsletter') }}
            </p>
        @else
            <form method="POST" action="{{ route('store.newsletter.subscribe') }}">
                @csrf
                <x-store.bot-trap />
                <div class="relative">
                    <label for="bulten-eposta" class="sr-only">E-posta adresiniz</label>
                    <input id="bulten-eposta" type="email" name="email" value="{{ $nlErrors->any() ? old('email') : '' }}" required autocomplete="email" placeholder="E-posta adresiniz"
                        class="h-12 w-full rounded-xl border-0 bg-white pr-[7rem] pl-4 text-sm text-ink placeholder:text-slate-400 focus:ring-2 focus:ring-amber-400">
                    <button class="absolute top-1.5 right-1.5 bottom-1.5 rounded-lg bg-puzzle-orange px-4 text-sm font-bold text-white transition hover:brightness-110">Abone ol</button>
                </div>
                <label class="mt-2.5 flex items-start gap-2 text-[11px] leading-snug text-white/50">
                    <input type="checkbox" name="consent" value="1" required class="mt-px size-3.5 shrink-0 rounded border-white/30 bg-white/10 text-puzzle-orange">
                    <span>{{ $consent }} <a href="{{ route('store.legal', 'kvkk') }}" class="text-white/75 underline underline-offset-2 hover:text-white">Aydınlatma Metni</a></span>
                </label>
                @foreach ($nlErrors->all() as $message)<p class="mt-1.5 text-xs font-medium text-rose-300">{{ $message }}</p>@endforeach
            </form>
        @endif
    </div>
</div>
