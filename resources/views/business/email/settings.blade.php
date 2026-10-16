@php use App\Domain\Mail\MailFailure; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="E-posta Ayarları">
    <x-page-header title="E-posta Ayarları" subtitle="Bayilerinize giden bildirimler kendi Google hesabınızdan gönderilir.">
        <x-button variant="secondary" :href="route('business.email.logs')">Gönderim kayıtları</x-button>
    </x-page-header>

    @error('email')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Gönderici hesap">
                @if ($setting)
                    <div @class([
                        'mb-5 flex items-start gap-3 rounded-lg px-4 py-3 text-sm',
                        'bg-success-soft text-green-900' => $setting->status === 'verified',
                        'bg-warning-soft text-amber-900' => $setting->status === 'unverified',
                        'bg-danger-soft text-red-900' => $setting->status === 'failed',
                    ])>
                        <x-icon :name="$setting->status === 'verified' ? 'check-circle' : 'alert'" class="mt-0.5 size-4 shrink-0" />
                        <div>
                            @if ($setting->status === 'verified')
                                <p class="font-semibold">E-posta yapılandırıldı ve doğrulandı</p>
                            @elseif ($setting->status === 'unverified')
                                <p class="font-semibold">Ayar kaydedildi, henüz test edilmedi</p>
                            @else
                                <p class="font-semibold">Son deneme başarısız — e-postalar gönderilmiyor</p>
                                <p>{{ MailFailure::MESSAGES[$setting->last_error_code] ?? MailFailure::MESSAGES['unknown'] }}</p>
                            @endif
                            @if ($setting->last_tested_at)<p class="text-xs opacity-80">Son test: {{ $setting->last_tested_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>@endif
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('business.email.update') }}" class="space-y-5">
                    @csrf @method('PUT')
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="from_email" label="Ana e-posta adresi (Google hesabı)" hint="Bütün e-postalar bu adresten gider; size gelen bildirimler (yeni sipariş, başvuru, mesaj) de bu adrese düşer." type="email" :value="$setting?->from_email" required autocomplete="off" />
                        <x-field name="from_name" label="Görünen ad (isteğe bağlı)" :value="$setting?->from_name" :placeholder="$currentBusiness->display_name" />
                    </div>
                    <div>
                        <label for="app_password" class="mb-1.5 block text-sm font-medium">
                            {{ $setting ? 'Yeni uygulama şifresi gir' : 'Google uygulama şifresi (16 hane)' }}@unless ($setting)<span class="text-danger"> *</span>@endunless
                        </label>
                        <input id="app_password" name="app_password" type="password" autocomplete="new-password" maxlength="24" class="field-input font-mono"
                            placeholder="{{ $setting ? 'Kayıtlı şifre değişmeyecekse boş bırakın' : 'abcd efgh ijkl mnop' }}" aria-invalid="{{ $errors->has('app_password') ? 'true' : 'false' }}">
                        @error('app_password')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                        <p class="mt-1.5 text-xs text-muted">Kayıtlı şifre güvenlik nedeniyle tekrar gösterilmez; şifreli olarak saklanır.</p>
                    </div>
                    <x-toggle name="payment_reminders" label="Haftalık vade hatırlatması gönder" :checked="$setting?->payment_reminders ?? false"
                        hint="Her pazartesi, vadesi geçmiş veya 7 gün içinde vadesi gelecek borcu olan bayilere." />
                    <x-button>Kaydet</x-button>
                </form>

                @if ($setting)
                    <form method="POST" action="{{ route('business.email.test') }}" class="mt-5 border-t border-line pt-5">
                        @csrf
                        <x-button variant="secondary" icon="refresh">Bağlantıyı test et</x-button>
                        <p class="mt-2 text-xs text-muted">Gmail'e bağlanıp kimlik doğrulanır; başarılıysa ana e-posta adresine ({{ $setting->from_email }}) test e-postası gönderilir.</p>
                    </form>
                @endif
            </x-card>

            <x-card title="E-posta şablonları" description="Konu ve metni değiştirebilirsiniz; logo, renk ve düzen otomatik uygulanır. Süslü parantezli alanlar ({bayi}, {siparis_no} …) otomatik doldurulur." :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($defaults as $key => $tpl)
                        @php $custom = \App\Models\EmailTemplate::where('key', $key)->first(); @endphp
                        <li class="px-5 py-3" x-data="{ open: false }">
                            <button type="button" class="flex w-full items-center justify-between text-left text-sm" @click="open = !open">
                                <span><span class="font-medium">{{ $tpl['label'] }}</span>@if ($custom) <x-badge tone="primary" class="ml-1">Özelleştirildi</x-badge>@endif</span>
                                <x-icon name="chevron-down" class="size-4 text-muted" />
                            </button>
                            <form x-cloak x-show="open" method="POST" action="{{ route('business.email.template') }}" class="mt-3 space-y-3">
                                @csrf @method('PUT')
                                <input type="hidden" name="key" value="{{ $key }}">
                                <input name="subject" value="{{ $custom?->subject ?? $tpl['subject'] }}" class="field-input" maxlength="200" required aria-label="Konu">
                                <textarea name="intro" rows="3" class="field-input" maxlength="2000" required aria-label="Metin">{{ $custom?->intro ?? $tpl['intro'] }}</textarea>
                                <div class="flex gap-2">
                                    <x-button size="sm" variant="secondary">Kaydet</x-button>
                                    @if ($custom)<x-button size="sm" variant="ghost" name="reset" value="1">Varsayılana dön</x-button>@endif
                                </div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Uygulama şifresi nasıl alınır?">
                <ol class="list-decimal space-y-2 pl-4 text-sm text-slate-700">
                    <li>Google hesabınızda <b>2 adımlı doğrulamayı</b> açın.</li>
                    <li>Google Hesabı → Güvenlik → <b>Uygulama şifreleri</b> bölümüne gidin.</li>
                    <li>Bir ad verin (ör. “Bayi sistemi”) ve oluşturun.</li>
                    <li>Gösterilen <b>16 harfli şifreyi</b> buraya girin.</li>
                </ol>
                <p class="mt-4 text-xs text-muted">Bazı Google Workspace hesaplarında yönetici uygulama şifrelerini kapatmış olabilir. Gmail'in günlük gönderim sınırları vardır (kişisel hesaplarda yaklaşık 500 alıcı/gün). Google politikası değişirse bu yöntem çalışmayabilir; test butonu durumu gösterir. Gönderen adres, şifrenin ait olduğu hesap olmalıdır.</p>
            </x-card>
            <x-card title="Son 30 gün">
                <dl class="space-y-2 text-sm">
                    @foreach (\App\Models\EmailLog::STATUSES as $k => $label)
                        <div class="flex justify-between"><dt class="text-muted">{{ $label }}</dt><dd class="font-medium tabular-nums">{{ $stats[$k] ?? 0 }}</dd></div>
                    @endforeach
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
