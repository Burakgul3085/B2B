<x-layouts.panel :area="$area" title="Profil ve Güvenlik">
    <x-page-header title="Profil ve Güvenlik" subtitle="Giriş bilgilerinizi ve parolanızı yönetin." />

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($area === \App\Domain\Auth\Area::Dealer)
            @php($dealer = $user->dealer)
            <x-card title="Bayi logosu" description="Logonuz sayfanın üst kısmında, adınızın yanında görünür." class="lg:col-span-2">
                @error('logo')<div role="alert" class="mb-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-line bg-white p-3 shadow-xs">
                        @if ($dealer->logo_path)
                            <img src="{{ route('dealer.logo') }}?v={{ $dealer->updated_at?->timestamp }}" alt="{{ $dealer->legal_name }} logosu" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="flex size-full items-center justify-center rounded-xl bg-amber-400 text-4xl font-bold text-slate-900">{{ mb_substr($dealer->legal_name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-3">
                        <form method="POST" action="{{ route('dealer.logo.update') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                            @csrf
                            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required aria-label="Logo dosyası"
                                class="min-w-0 flex-1 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                            <x-button icon="upload">{{ $dealer->logo_path ? 'Logoyu değiştir' : 'Logo yükle' }}</x-button>
                        </form>
                        <p class="text-xs text-muted">JPG, PNG veya WEBP; en fazla 2 MB. Kare ya da şeffaf arka planlı logolar en iyi görünür (ör. 400×400).</p>
                        @if ($dealer->logo_path)
                            <form method="POST" action="{{ route('dealer.logo.destroy') }}" data-confirm="Logo kaldırılsın mı?">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Logoyu kaldır</button></form>
                        @endif
                    </div>
                </div>
            </x-card>
        @endif

        <x-card title="Profil">
            <form method="POST" action="{{ route($area->value.'.account.profile') }}" class="space-y-5">
                @csrf @method('PUT')
                <x-field name="name" label="Ad soyad" :value="$user->name" required />
                <x-button>Kaydet</x-button>
            </form>
        </x-card>

        <x-card title="Giriş e-postası" description="Yeni adres hemen geçerli olur; bundan sonra bu adresle giriş yapar, e-postaları bu adreste alırsınız.">
            <dl class="mb-5 text-sm">
                <dt class="text-muted">Mevcut giriş e-postası</dt>
                <dd class="mt-0.5 break-all font-medium">{{ $user->email }}</dd>
            </dl>
            <form method="POST" action="{{ route($area->value.'.account.email') }}" class="space-y-5">
                @csrf
                <x-field name="email" label="Yeni e-posta adresi" type="email" required autocomplete="off" data-1p-ignore data-lpignore="true" />
                <x-field name="email_password" label="Mevcut şifre" type="password" required autocomplete="new-password" hint="Güvenliğiniz için şifrenizle onaylayın." />
                <x-button variant="secondary">E-postayı değiştir</x-button>
            </form>
        </x-card>

        <x-card title="Parola değiştir" description="Değişiklikten sonra diğer cihazlardaki oturumlarınız kapanır.">
            <form method="POST" action="{{ route($area->value.'.account.password') }}" class="space-y-5">
                @csrf @method('PUT')
                <x-field name="current_password" label="Mevcut parola" type="password" required autocomplete="current-password" />
                <x-field name="password" label="Yeni parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
                <x-button>Parolayı değiştir</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
