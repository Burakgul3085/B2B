{{-- Sevk adresi formu: $action, $address (opsiyonel), $submit --}}
@php $a = $address ?? null; $prefix = $a ? 'a'.$a->id.'_' : 'new_'; @endphp
<form method="POST" action="{{ $action }}" class="grid gap-4 sm:grid-cols-2">
    @csrf
    @if ($a) @method('PUT') @endif
    <div><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}title">Adres başlığı *</label><input id="{{ $prefix }}title" name="title" value="{{ $a?->title }}" class="field-input" required maxlength="60" placeholder="Ör. Merkez Mağaza"></div>
    <div><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}recipient">Teslim alacak kişi *</label><input id="{{ $prefix }}recipient" name="recipient_name" value="{{ $a?->recipient_name }}" class="field-input" required maxlength="120"></div>
    <div><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}phone">Telefon *</label><input id="{{ $prefix }}phone" name="phone" type="tel" value="{{ $a?->phone }}" class="field-input" required maxlength="30"></div>
    <div class="grid grid-cols-2 gap-3">
        <div><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}city">İl *</label><input id="{{ $prefix }}city" name="city" value="{{ $a?->city }}" class="field-input" required maxlength="60"></div>
        <div><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}district">İlçe *</label><input id="{{ $prefix }}district" name="district" value="{{ $a?->district }}" class="field-input" required maxlength="60"></div>
    </div>
    <div class="sm:col-span-2"><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}line">Açık adres *</label><textarea id="{{ $prefix }}line" name="address_line" rows="2" class="field-input" required maxlength="500">{{ $a?->address_line }}</textarea></div>
    <div class="sm:col-span-2"><label class="mb-1.5 block text-sm font-medium" for="{{ $prefix }}note">Adres notu</label><input id="{{ $prefix }}note" name="note" value="{{ $a?->note }}" class="field-input" maxlength="255" placeholder="Ör. Mesai 09:00–18:00, arka kapı"></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="size-4 rounded" @checked($a?->is_default)> Varsayılan sevk adresi</label>
    <div class="sm:text-right"><x-button size="sm">{{ $submit ?? 'Kaydet' }}</x-button></div>
</form>
