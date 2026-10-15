@php $b = $business ?? null; @endphp
<div class="grid gap-5 sm:grid-cols-2">
    <x-field name="legal_name" label="Ticari unvan" :value="$b?->legal_name" required class="sm:col-span-2" />
    <x-field name="display_name" label="Görünen ad" :value="$b?->display_name" required hint="Panelde ve e-postalarda görünen kısa ad (ör. Akgün Kırtasiye)." />
    <x-field name="email" label="İşletme e-postası" type="email" :value="$b?->email" />
    <x-field name="tax_office" label="Vergi dairesi" :value="$b?->tax_office" />
    <x-field name="tax_number" label="Vergi / TC kimlik no" :value="$b?->tax_number" inputmode="numeric" maxlength="11" />
    <x-field name="phone" label="Telefon" type="tel" :value="$b?->phone" />
    <x-field name="city" label="İl" :value="$b?->city" />
    <x-field name="district" label="İlçe" :value="$b?->district" />
    <x-field name="address" label="Adres" :value="$b?->address" textarea class="sm:col-span-2" />
</div>
