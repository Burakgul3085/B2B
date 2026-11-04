{{-- Cari Hesaplar sekmeleri: bayi bakiyeleri ve gelen ödemeler (tahsilatlar) aynı bölümde. --}}
<nav class="mb-6 flex gap-1 border-b border-line" aria-label="Cari hesap bölümleri">
    @foreach (['business.accounts.index' => 'Bayiler', 'business.collections.index' => 'Gelen ödemeler'] as $route => $label)
        @php $active = request()->routeIs($route === 'business.accounts.index' ? 'business.accounts.*' : 'business.collections.*'); @endphp
        <a href="{{ route($route) }}" @class([
            '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition',
            'border-primary text-primary' => $active,
            'border-transparent text-muted hover:text-ink' => ! $active,
        ]) @if ($active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
