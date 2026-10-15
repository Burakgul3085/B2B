@props(['summary' => true])
@if (session('status'))
    <div role="status" x-data="{ open: true }" x-show="open"
        class="mb-5 flex items-start gap-3 rounded-lg border border-green-200 bg-success-soft px-4 py-3 text-sm text-green-900">
        <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" />
        <p class="flex-1">{{ session('status') }}</p>
        <button type="button" @click="open = false" class="text-green-900/60 hover:text-green-900" aria-label="Kapat"><x-icon name="x" class="size-4" /></button>
    </div>
@endif
@if ($errors->any() && $summary)
    <div role="alert" class="mb-5 flex items-start gap-3 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">
        <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
        <p>Lütfen işaretli alanları kontrol edin.</p>
    </div>
@endif
