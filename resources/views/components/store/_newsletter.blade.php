{{-- Alt bilgideki e-bülten şeridi (Pazarlama → E-bülten Aboneleri → Ayarlar). Onay kutusu zorunludur; kayıt e-postayla onaylanır. --}}
@php $nlErrors = $errors->getBag('newsletter'); @endphp
<div id="e-bulten" class="scroll-mt-24 border-b border-white/10">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#13265a] to-[#0e1c3d] p-6 ring-1 ring-white/10 sm:p-8 lg:flex lg:items-center lg:justify-between lg:gap-10">
            <div class="pointer-events-none absolute -top-6 -right-6 grid rotate-12 grid-cols-2 gap-2 opacity-20" aria-hidden="true">
                <span class="size-12 rounded-xl bg-puzzle-orange"></span><span class="size-12 rounded-xl bg-puzzle-green"></span>
                <span class="size-12 rounded-xl bg-puzzle-blue"></span><span class="size-12 rounded-xl bg-puzzle-yellow"></span>
            </div>
            <div class="relative flex items-start gap-4 lg:max-w-md">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-400 text-[#0b1631]"><x-icon name="mail" class="size-6" /></span>
                <div>
                    <p class="font-display text-xl font-extrabold text-white sm:text-2xl">{{ $title }}</p>
                    @if ($text)<p class="mt-1 text-sm leading-relaxed text-white/65">{{ $text }}</p>@endif
                </div>
            </div>
            <div class="relative mt-6 w-full lg:mt-0 lg:max-w-lg">
                @if (session('newsletter'))
                    <p class="flex items-start gap-2.5 rounded-2xl bg-white/10 px-4 py-3.5 text-sm font-medium text-white ring-1 ring-white/15" role="status">
                        <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-puzzle-green" /> {{ session('newsletter') }}
                    </p>
                @else
                    <form method="POST" action="{{ route('store.newsletter.subscribe') }}">
                        @csrf
                        <x-store.bot-trap />
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label for="bulten-eposta" class="sr-only">E-posta adresiniz</label>
                            <input id="bulten-eposta" type="email" name="email" value="{{ old('email') && $nlErrors->any() ? old('email') : '' }}" required autocomplete="email" placeholder="E-posta adresinizi yazın"
                                class="h-12 w-full min-w-0 rounded-xl border-0 bg-white px-4 sm:flex-1 text-[15px] text-ink placeholder:text-slate-400 focus:ring-2 focus:ring-amber-400">
                            <button class="h-12 shrink-0 rounded-xl bg-puzzle-orange px-6 text-[15px] font-bold text-white shadow-lg shadow-black/20 transition hover:brightness-110">Abone ol</button>
                        </div>
                        <label class="mt-3 flex items-start gap-2.5 text-xs leading-relaxed text-white/70">
                            <input type="checkbox" name="consent" value="1" required class="mt-0.5 size-4 shrink-0 rounded border-white/30 bg-white/10 text-puzzle-orange">
                            <span>{{ $consent }} <a href="{{ route('store.legal', 'kvkk') }}" class="font-semibold text-white underline">Aydınlatma Metni</a></span>
                        </label>
                        @foreach ($nlErrors->all() as $message)<p class="mt-2 text-sm font-medium text-rose-300">{{ $message }}</p>@endforeach
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
