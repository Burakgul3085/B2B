{{-- Kargo Takip: sipariş numarası ve e-posta ile sipariş sayfasına yönlendirir --}}
<x-store.help-layout :key="$key" :c="$c" icon="package">
    <div class="max-w-xl rounded-3xl border border-line p-6 sm:p-8">
        <form method="POST" action="{{ route('store.help.track') }}" class="space-y-5">
            @csrf
            <x-store.bot-trap />
            <x-field name="order_no" label="Sipariş numarası" :value="old('order_no')" required placeholder="ör. YB240001" autocomplete="off" />
            <x-field name="email" type="email" label="E-posta adresi" :value="old('email', auth('customer')->user()?->email)" required />
            <x-button class="w-full justify-center" icon="search">Siparişimi bul</x-button>
        </form>
        <p class="mt-4 text-xs text-muted">Sipariş numaranız sipariş onay e-postasında yazar. Üyeyseniz siparişlerinizi <a href="{{ route('store.orders') }}" class="font-semibold text-navy-ink underline">Siparişlerim</a> sayfasında da görebilirsiniz.</p>
    </div>
</x-store.help-layout>
