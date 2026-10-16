@if (session('activation_url'))
    <div class="mb-6 rounded-xl border border-amber-200 bg-warning-soft p-4 text-sm text-amber-900" x-data="{ copied: false }">
        <p class="font-semibold">Bayinin hesabını etkinleştirmesi için bağlantı</p>
        <p class="mt-1">E-posta Ayarları'nda Gmail hesabınız tanımlı olmadığı için bağlantı e-postayla gönderilemedi. Bu tek kullanımlık bağlantıyı bayiye güvenli bir kanaldan iletin. Bağlantı {{ config('b2b.activation_ttl_hours') }} saat geçerlidir ve <b>yalnızca bir kez gösterilir</b>. Bayi kendi parolasını kendisi belirler.</p>
        <div class="mt-3 flex gap-2">
            <input readonly value="{{ session('activation_url') }}" class="field-input font-mono text-xs" x-ref="link" aria-label="Aktivasyon bağlantısı">
            <x-button type="button" variant="secondary" @click="navigator.clipboard.writeText($refs.link.value); copied = true" x-text="copied ? 'Kopyalandı' : 'Kopyala'">Kopyala</x-button>
        </div>
    </div>
@endif
