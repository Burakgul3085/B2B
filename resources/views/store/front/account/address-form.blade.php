@php
    use App\Domain\Store\Customers\PhoneNumber;

    $editing = $address->exists;
    $provinceId = (int) old('province_id', $address->province_id);
    $districtId = (int) old('district_id', $address->district_id);
@endphp
<x-layouts.store :title="$editing ? 'Adresi düzenle' : 'Yeni adres'" :noindex="true">
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
        <a href="{{ route('store.account') }}#adresler" class="text-sm text-muted hover:text-primary">← Hesabım</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight">{{ $editing ? 'Adresi düzenle' : 'Yeni adres' }}</h1>
        @error('address')<p class="mt-4 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</p>@enderror

        {{-- İl seçilince ilçeler sunucudan yüklenir; ilçe-il uyumu sunucuda ayrıca denetlenir. --}}
        <form method="POST" action="{{ $editing ? route('store.addresses.update', $address->id) : route('store.addresses.store') }}" class="mt-6 space-y-5 rounded-2xl border border-line p-6"
            x-data="addressForm(@alpine($provinceId ?: ''), @alpine($districtId ?: ''))" x-init="load(true)">
            @csrf @if ($editing) @method('PUT') @endif
            <x-field name="title" label="Adres başlığı" :value="$address->title" required maxlength="40" placeholder="Ev, İş…" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="first_name" label="Ad" :value="$address->first_name" required maxlength="60" />
                <x-field name="last_name" label="Soyad" :value="$address->last_name" required maxlength="60" />
            </div>
            <x-field name="phone" label="Cep telefonu" type="tel" :value="PhoneNumber::format($address->phone)" required placeholder="05xx xxx xx xx" hint="Teslimatta size ulaşabilmemiz için." />
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="f_province" class="mb-1.5 block text-sm font-medium">İl<span class="text-danger"> *</span></label>
                    <select id="f_province" name="province_id" x-model="province" @change="load(false)" required class="field-input">
                        <option value="">Seçin</option>
                        @foreach ($provinces as $id => $name)
                            <option value="{{ $id }}" @selected($provinceId === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('province_id')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="f_district" class="mb-1.5 block text-sm font-medium">İlçe<span class="text-danger"> *</span></label>
                    <select id="f_district" name="district_id" x-model="district" required class="field-input" :disabled="! province || loading">
                        <option value="" x-text="loading ? 'Yükleniyor…' : (province ? 'Seçin' : 'Önce il seçin')">Önce il seçin</option>
                        <template x-for="d in districts" :key="d.id">
                            <option :value="d.id" x-text="d.name" :selected="d.id == district"></option>
                        </template>
                    </select>
                    @error('district_id')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
            <x-field name="neighborhood" label="Mahalle" :value="$address->neighborhood" maxlength="100" />
            <x-field name="address_line" label="Açık adres" :value="$address->address_line" textarea required maxlength="300" hint="Cadde/sokak, bina no, daire no." />
            <x-field name="postal_code" label="Posta kodu" :value="$address->postal_code" inputmode="numeric" maxlength="5" class="max-w-48" hint="İsteğe bağlı" />
            @unless ($editing && $address->is_default)
                <x-toggle name="is_default" label="Varsayılan adresim olsun" />
            @endunless
            <x-button>{{ $editing ? 'Kaydet' : 'Adresi kaydet' }}</x-button>
        </form>
    </div>
</x-layouts.store>
