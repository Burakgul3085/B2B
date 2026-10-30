@php use App\Models\Store\StoreProductQuestion; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Ürün Soruları">
    <x-page-header title="Ürün Soruları" subtitle="Ziyaretçilerin ürün sayfasından sorduğu sorular. Cevapladığınızda soru ve cevap ürün sayfasında birlikte yayımlanır, cevap soranın e-postasına gönderilir. Cevapsız soru vitrinde görünmez; soranın e-postası hiçbir yerde gösterilmez." />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach (StoreProductQuestion::STATUSES as $key => $label)
                @php $count = $counts[$key] ?? 0; $active = $status === $key; @endphp
                <a href="{{ route('store_admin.questions.index', ['status' => $key]) }}" @class([
                    'rounded-full px-3.5 py-1.5 font-medium ring-1',
                    'bg-primary text-white ring-primary' => $active,
                    'bg-white text-ink ring-line hover:ring-primary' => ! $active,
                ])>{{ $label }}@if ($count) <span class="ml-1 tabular-nums opacity-80">{{ $count }}</span>@endif</a>
            @endforeach
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Soru, ürün veya e-posta ara" class="field-input w-64 py-1.5" aria-label="Ara">
            <x-button variant="secondary" size="sm" icon="search">Ara</x-button>
        </form>
    </div>

    @if ($questions->isEmpty())
        <x-card><x-empty-state icon="message-circle" title="Soru yok" :text="$status === 'pending' ? 'Cevap bekleyen soru bulunmuyor.' : 'Bu durumda soru bulunmuyor.'" /></x-card>
    @else
        <div class="space-y-4">
            @foreach ($questions as $question)
                <x-card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            @if ($question->product)
                                <a href="{{ route('store.product', $question->product->slug) }}#sorular" target="_blank" rel="noopener" class="font-semibold hover:text-primary">{{ $question->product->name }}</a>
                            @endif
                            <p class="mt-1 text-sm text-muted">
                                <span class="font-medium text-ink">{{ $question->first_name }} {{ $question->last_name }}</span>
                                · <a href="mailto:{{ $question->email }}" class="text-primary hover:underline">{{ $question->email }}</a>
                                · {{ $question->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                @if ($question->customer_id)<x-badge tone="info" class="ml-1">Üye</x-badge>@endif
                            </p>
                        </div>
                        <x-badge :tone="$question->statusTone()">{{ $question->statusLabel() }}</x-badge>
                    </div>

                    <div class="mt-4 rounded-xl bg-surface p-4">
                        <p class="text-xs font-bold tracking-wider text-muted uppercase">Soru</p>
                        <p class="mt-1 text-sm whitespace-pre-line text-ink">{{ $question->question }}</p>
                    </div>

                    <form method="POST" action="{{ route('store_admin.questions.answer', $question) }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <details class="text-sm" @if ($errors->has('question') && old('question_id') == $question->id) open @endif>
                            <summary class="cursor-pointer text-muted">Soru metnini düzelt (yazım hatası vb.)</summary>
                            <textarea name="question" rows="2" maxlength="1000" class="field-input mt-2">{{ $question->question }}</textarea>
                        </details>
                        <input type="hidden" name="question_id" value="{{ $question->id }}">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium">{{ $question->answer ? 'Cevabınız (yayında)' : 'Cevabınız' }}</span>
                            <textarea name="answer" rows="3" maxlength="2000" required class="field-input" placeholder="Müşteriye cevabınızı yazın">{{ old('question_id') == $question->id ? old('answer') : $question->answer }}</textarea>
                            @if (old('question_id') == $question->id)@error('answer')<span class="mt-1 block text-xs text-danger">{{ $message }}</span>@enderror @endif
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button size="sm" icon="check-circle">{{ $question->answered_at ? 'Cevabı güncelle' : 'Cevapla ve yayımla' }}</x-button>
                            @if (! $question->answered_at)<span class="text-xs text-muted">Cevap ürün sayfasında yayımlanır ve {{ $question->email }} adresine gönderilir.</span>@endif
                            @if ($question->answer_mailed_at)<span class="text-xs text-success">E-posta {{ $question->answer_mailed_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} tarihinde gönderildi.</span>@endif
                        </div>
                    </form>

                    <div class="mt-3 flex flex-wrap gap-4 border-t border-line pt-3 text-xs">
                        <form method="POST" action="{{ route('store_admin.questions.visibility', $question) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="hidden" value="{{ $question->status === 'hidden' ? '0' : '1' }}">
                            <button class="text-muted hover:text-ink hover:underline">{{ $question->status === 'hidden' ? 'Yeniden göster' : 'Gizle' }}</button>
                        </form>
                        <form method="POST" action="{{ route('store_admin.questions.destroy', $question) }}" data-confirm="Soru kalıcı olarak silinsin mi?">
                            @csrf @method('DELETE')
                            <button class="text-danger hover:underline">Sil</button>
                        </form>
                    </div>
                </x-card>
            @endforeach
        </div>
        @if ($questions->hasPages())<div class="mt-4">{{ $questions->links() }}</div>@endif
    @endif
</x-layouts.panel>
