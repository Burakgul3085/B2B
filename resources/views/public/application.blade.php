<x-layouts.public :business="$business" title="Bayilik başvurusu">
    <h1 class="text-2xl font-semibold tracking-tight">Bayilik başvurusu</h1>
    <p class="mt-2 text-sm text-muted">{{ $business->display_name }} bayisi olmak için formu doldurun. Başvurunuz değerlendirildikten sonra size e-posta ile dönüş yapılır; onaylanırsa hesabınızı etkinleştirme bağlantısı gönderilir.</p>

    <x-flash />

    <form method="POST" action="{{ route('public.application.store', $business->slug) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        {{-- Bot tuzağı: kullanıcılara görünmez --}}
        <div class="hidden" aria-hidden="true"><label>Web sitesi <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <x-card title="İşletme">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="legal_name" label="İşletme unvanı" required class="sm:col-span-2" />
                <x-field name="business_type" label="İşletme türü" :options="['' => 'Seçin'] + $types" />
                <x-field name="activity_area" label="Faaliyet alanı" placeholder="Ör. okul kırtasiyesi, ofis tedariki" />
                <x-field name="tax_office" label="Vergi dairesi" />
                <x-field name="tax_number" label="Vergi / TC kimlik no" inputmode="numeric" maxlength="11" />
            </div>
        </x-card>

        <x-card title="İletişim ve adres">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="contact_name" label="Yetkili adı soyadı" required autocomplete="name" />
                <x-field name="phone" label="Telefon" type="tel" required autocomplete="tel" />
                <x-field name="email" label="E-posta" type="email" required autocomplete="email" class="sm:col-span-2" hint="Hesabınız onaylanırsa giriş adresiniz bu olur." />
                <x-field name="city" label="İl" required />
                <x-field name="district" label="İlçe" required />
                <x-field name="address" label="Açık adres" textarea required class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Ek bilgiler">
            <x-field name="note" label="Açıklama" textarea placeholder="Eklemek istedikleriniz (isteğe bağlı)" />
            <div class="mt-5">
                <label for="documents" class="mb-1.5 block text-sm font-medium">Belgeler (isteğe bağlı)</label>
                <input id="documents" type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                <p class="mt-1.5 text-xs text-muted">Ör. vergi levhası. PDF, JPG veya PNG; en fazla 3 dosya, her biri 5 MB.</p>
                @error('documents')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                @error('documents.*')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        </x-card>

        <x-card title="Kişisel verilerin korunması">
            <details class="text-sm text-slate-700">
                <summary class="cursor-pointer font-medium text-primary">Aydınlatma metnini oku</summary>
                <div class="mt-3 space-y-2 text-xs leading-relaxed">
                    <p>Veri sorumlusu {{ $business->legal_name }} olarak; bu formda paylaştığınız kimlik, iletişim ve işletme bilgilerini yalnızca bayilik başvurunuzu değerlendirmek, sizinle iletişime geçmek ve başvurunuz onaylanırsa bayi hesabınızı açmak amacıyla işleriz.</p>
                    <p>Bilgileriniz bu amaç dışında kullanılmaz ve üçüncü kişilerle paylaşılmaz; hizmetin teknik altyapısını sağlayan yazılım hizmet sağlayıcısı yalnızca barındırma amacıyla erişebilir. Başvurunuzun değerlendirilmesi için gerekli olan bilgiler, sözleşmenin kurulması için gerekli olması hukuki sebebine dayanır.</p>
                    <p>6698 sayılı Kanun'un 11. maddesindeki haklarınızı kullanmak için {{ $business->email ?: 'işletmenin iletişim adresi' }} üzerinden başvurabilirsiniz.</p>
                    <p class="text-muted">Bu metin şablondur; işletme tarafından hukuki danışmanlıkla gözden geçirilmelidir. Sürüm: {{ \App\Domain\Dealers\DealerOnboarding::CONSENT_VERSION }}</p>
                </div>
            </details>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <input type="checkbox" name="consent" value="1" class="mt-0.5 size-4 rounded" @checked(old('consent'))>
                <span>Aydınlatma metnini okudum; başvurum kapsamında verilerimin işlenmesini anladım.</span>
            </label>
            @error('consent')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
        </x-card>

        <x-button class="w-full sm:w-auto">Başvuruyu gönder</x-button>
    </form>
</x-layouts.public>
