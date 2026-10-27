@php $open = $settings['is_open'] === '1'; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Mağaza Ayarları">
    <x-page-header title="Mağaza Ayarları" subtitle="İletişim bilgileri, duyuru şeridi, ana sayfa SEO'su ve mağazanın yayın durumu." />

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('store_admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6 xl:col-span-2">
            @csrf @method('PUT')
            <x-card title="İletişim" description="Mağazanın alt bilgisinde ve iletişim alanlarında görünür.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="phone" label="Telefon" :value="$settings['phone']" maxlength="30" placeholder="0222 000 00 00" />
                    <x-field name="phone_label" label="Telefon etiketi (isteğe bağlı)" :value="$settings['phone_label']" maxlength="30" placeholder="Dükkân" hint="Numaranın yanında görünür; boşsa “Telefon” yazar." />
                    <x-field name="phone2" label="İkinci telefon (isteğe bağlı)" :value="$settings['phone2']" maxlength="30" placeholder="0532 000 00 00" />
                    <x-field name="phone2_label" label="İkinci telefon etiketi (isteğe bağlı)" :value="$settings['phone2_label']" maxlength="30" placeholder="Cep" hint="Boşsa “Telefon 2” yazar." />
                    <x-field name="whatsapp" label="WhatsApp numarası" :value="$settings['whatsapp']" maxlength="30" placeholder="90 5xx xxx xx xx" />
                    <x-field name="email" label="E-posta" type="email" :value="$settings['email']" />
                    <x-field name="address" label="Adres" :value="$settings['address']" maxlength="500" />
                </div>
            </x-card>
            <x-card title="Sosyal medya" description="Doldurduğunuz hesapların simgeleri alt bilgide görünür; boş bırakılan görünmez.">
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach (\App\Domain\Store\Content\SocialLinks::NETWORKS as $key => [$label, $example])
                        <x-field :name="$key" :label="$label" :value="$settings[$key]" :placeholder="$example" />
                    @endforeach
                </div>
            </x-card>
            @php [$smtpHost, $smtpPort, , $smtpProvider] = \App\Domain\Store\Mail\StoreMailer::detect($settings['mail_username']); @endphp
            <x-card title="E-posta gönderimi" description="Sipariş, bildirim, stok ve e-bülten e-postaları bu hesapla gider. Yalnızca e-posta adresinizi ve uygulama şifrenizi yazın; sunucu ayarları adresinizden otomatik bulunur.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="mail_username" label="E-posta adresi" type="email" :value="$settings['mail_username']" autocomplete="off" placeholder="ornek@gmail.com" />
                    <div>
                        <x-field name="mail_password" type="password" label="Uygulama şifresi" autocomplete="new-password" placeholder="abcd efgh ijkl mnop"
                            :hint="$settings['mail_password'] ? 'Kayıtlı. Değiştirmek için yeni şifreyi yazın; boş bırakırsanız korunur.' : 'Gmail: Google Hesabı → Güvenlik → 2 Adımlı Doğrulama → Uygulama şifreleri (16 hane).'" />
                        @if ($settings['mail_password'])<label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_mail_password" value="1" class="size-4 rounded border-line"> Kayıtlı şifreyi sil</label>@endif
                    </div>
                    <x-field name="mail_from_name" label="Gönderen adı (isteğe bağlı)" :value="$settings['mail_from_name']" :placeholder="config('store.name')" class="sm:col-span-2" />
                </div>
                @if ($settings['mail_username'])
                    <p class="mt-4 flex items-center gap-2 rounded-lg bg-surface px-3 py-2 text-sm">
                        <x-icon name="check-circle" class="size-4 text-success" />
                        @if ($settings['mail_host'])Elle girilen sunucu: <span class="font-semibold">{{ $settings['mail_host'] }}:{{ $settings['mail_port'] }}</span>
                        @else Algılanan: <span class="font-semibold">{{ $smtpProvider }}</span> <span class="text-muted">({{ $smtpHost }}:{{ $smtpPort }})</span>@endif
                    </p>
                @endif
                <details class="mt-4 rounded-lg border border-line px-4 py-3" @if ($settings['mail_host'] || $errors->hasAny(['mail_host', 'mail_port', 'mail_from_address'])) open @endif>
                    <summary class="cursor-pointer text-sm font-medium">Gelişmiş ayarlar (genellikle gerekmez)</summary>
                    <p class="mt-2 text-xs text-muted">Yalnızca adresiniz algılanamıyorsa veya farklı bir e-posta sunucusu kullanıyorsanız doldurun. Boş bırakılırsa otomatik ayar kullanılır.</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <x-field name="mail_host" label="SMTP sunucusu" :value="$settings['mail_host']" :placeholder="$smtpHost" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="mail_port" label="Port" :value="$settings['mail_host'] ? $settings['mail_port'] : null" inputmode="numeric" :placeholder="(string) $smtpPort" />
                            <x-field name="mail_encryption" label="Şifreleme" :options="\App\Domain\Store\Mail\StoreMailer::ENCRYPTIONS" :value="$settings['mail_encryption']" />
                        </div>
                        <x-field name="mail_from_address" label="Gönderen adresi (farklıysa)" type="email" :value="$settings['mail_from_address']" placeholder="E-posta adresiyle aynı" class="sm:col-span-2" />
                    </div>
                </details>
                @if (\App\Domain\Store\Mail\StoreMailer::hasOwnAccount())
                    <button form="deneme-eposta" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm font-medium hover:bg-slate-50"><x-icon name="mail" class="size-4" /> Kendime deneme e-postası gönder</button>
                @endif
            </x-card>
            <x-card title="Satıcı bilgileri" description="Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi ve iletişim sayfasında kullanılır; iyzico başvurusunda da istenir.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="company_title" label="Ticari unvan" :value="$settings['company_title']" maxlength="200" class="sm:col-span-2" placeholder="ör. Yapboz Kırtasiye Ltd. Şti." />
                    <x-field name="company_tax_office" label="Vergi dairesi" :value="$settings['company_tax_office']" maxlength="80" />
                    <x-field name="company_tax_number" label="Vergi numarası" :value="$settings['company_tax_number']" inputmode="numeric" maxlength="11" />
                    <x-field name="company_esnaf_no" label="Esnaf ve Sanatkârlar sicil no" :value="$settings['company_esnaf_no']" maxlength="30"
                        hint="Şahıs işletmesi için. Girilirse sözleşmelerde ve iletişim sayfasında MERSİS yerine gösterilir." />
                    <x-field name="company_mersis" label="MERSİS numarası" :value="$settings['company_mersis']" inputmode="numeric" maxlength="16" hint="Şirketler (Ltd., A.Ş.) için; şahıs işletmesiyse boş bırakın." />
                </div>
            </x-card>
            <x-card title="Ödeme logo bandı" description="iyzico, sitenin alt bilgisinde “iyzico ile Öde” ve kart logolarının bulunmasını ister. Görseli iyzico üye işyeri panelinden indirip yükleyin.">
                @if ($settings['payment_logos_path'])
                    <img src="{{ route('store.media.payment-logos') }}" alt="Ödeme logoları" class="mb-4 h-8 w-auto">
                    <label class="mb-4 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_payment_logos" value="1" class="size-4 rounded border-line"> Görseli kaldır</label>
                @endif
                <x-field name="payment_logos" label="{{ $settings['payment_logos_path'] ? 'Yeni görsel' : 'Görsel' }}" type="file" accept="image/png,image/jpeg,image/webp" hint="PNG, JPG veya WebP; en fazla 2 MB." />
            </x-card>
            <x-card title="Yönetici bildirimleri" description="Yeni sipariş, iade talebi ve kritik stok için e-posta. Sistem e-posta hesabıyla gönderilir.">
                <div class="space-y-4">
                    <x-field name="notify_emails" label="Bildirim adresleri" :value="$settings['notify_emails']" maxlength="500" placeholder="siparis@ornek.com, depo@ornek.com"
                        hint="Virgülle ayırın. Boş bırakılırsa etkin tüm mağaza yöneticilerine gönderilir." />
                    <x-toggle name="notify_orders" label="Yeni sipariş" :checked="$settings['notify_orders'] === '1'" hint="Kartla ödemesi alınan veya kapıda ödemeli oluşturulan her sipariş." />
                    <x-toggle name="notify_returns" label="Yeni iade talebi" :checked="$settings['notify_returns'] === '1'" />
                    <x-toggle name="notify_low_stock" label="Kritik stok" :checked="$settings['notify_low_stock'] === '1'" hint="Satışla stok, ürünün kritik stok seviyesine indiğinde." />
                    <x-toggle name="notify_messages" label="İletişim formu mesajı" :checked="$settings['notify_messages'] === '1'" hint="İletişim sayfasındaki formdan yeni mesaj geldiğinde." />
                </div>
            </x-card>
            <x-card title="Görünüm ve arama motorları">
                <div class="space-y-5">
                    <x-field name="announcement" label="Duyuru şeridi" :value="$settings['announcement']" maxlength="200" hint="Tüm sayfaların en üstünde görünür (ör. kampanya duyurusu). Boşsa gösterilmez." />
                    <p class="text-sm text-muted">Alt bilgideki tanıtım metni ve düzeni: <a href="{{ route('store_admin.footer.edit') }}" class="font-medium text-primary hover:underline">Vitrin → Alt Bilgi</a>.</p>
                    <x-field name="meta_title" label="Ana sayfa SEO başlığı" :value="$settings['meta_title']" maxlength="160" :hint="'Boşsa: '.config('store.name')" />
                    <x-field name="meta_description" label="Ana sayfa SEO açıklaması" :value="$settings['meta_description']" maxlength="320" hint="Google sonuçlarında görünen açıklama (150–160 karakter ideal)." />
                    <x-field name="new_product_days" label="Yeni ürün süresi (gün)" type="number" min="1" max="365" inputmode="numeric" :value="$settings['new_product_days']"
                        hint="Son bu kadar günde yayınlanan ürünler “Yeni gelenler” sayfasında listelenir ve kartlarında “Yeni” rozeti görünür." />
                </div>
            </x-card>
            <x-button>Kaydet</x-button>
        </form>

        <div class="space-y-6">
            <x-card title="Yayın durumu">
                <p class="flex items-center gap-2 text-sm">
                    @if ($open)<x-badge tone="success">Mağaza açık</x-badge> Ziyaretçiler alışveriş sayfalarını görüyor.
                    @else<x-badge tone="warning">Mağaza kapalı</x-badge> Ziyaretçiler "yakında" sayfasını görüyor.@endif
                </p>
                <form method="POST" action="{{ route('store_admin.settings.open') }}" class="mt-4"
                    data-confirm="{{ $open ? 'Mağaza ziyaretçilere kapatılsın mı?' : 'Mağaza ziyaretçilere açılsın mı? Ürünler Google ve ziyaretçiler tarafından görülebilir olacak.' }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="open" value="{{ $open ? 0 : 1 }}">
                    <x-button :variant="$open ? 'secondary' : 'primary'" size="sm">{{ $open ? 'Mağazayı kapat' : 'Mağazayı aç' }}</x-button>
                </form>
            </x-card>
            <x-card title="Önizleme" description="Kapalı mağazayı ziyaretçi gibi gezin. Bağlantı 15 dakika geçerlidir; açtığınız tarayıcıda 2 saat önizleme yapabilirsiniz.">
                <x-button variant="secondary" icon="eye" :href="$previewUrl" target="_blank" rel="noopener">Mağazayı önizle</x-button>
            </x-card>
        </div>
    </div>
    <form id="deneme-eposta" method="POST" action="{{ route('store_admin.settings.test-mail') }}">@csrf</form>
</x-layouts.panel>
