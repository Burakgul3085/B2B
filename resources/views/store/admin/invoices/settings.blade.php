<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Fatura Ayarları">
    <x-page-header title="Fatura Ayarları" subtitle="e-Fatura / e-Arşiv sağlayıcısı (Uyumsoft) ve fatura varsayılanları. Fatura otomatik kesilmez; Faturalar ekranından siz gönderirsiniz. Bu sayfayı yalnızca süper admin görür.">
        <x-button variant="secondary" :href="route('store_admin.invoices.index')">Faturalar</x-button>
    </x-page-header>

    @error('test')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <form method="POST" action="{{ route('store_admin.invoice-settings.update') }}" class="space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Sağlayıcı" description="Deneme: faturalar hiçbir yere gönderilmez, üzerinde DENEME yazan PDF üretilir (akışı denemek için). Uyumsoft: gerçek e-Fatura / e-Arşiv.">
                <div class="space-y-5">
                    <x-field name="invoice_driver" label="Sağlayıcı" :options="['deneme' => 'Deneme (gerçek fatura kesilmez)', 'uyumsoft' => 'Uyumsoft']" :value="$s['invoice_driver']" />
                    <x-field name="uyumsoft_mode" label="Uyumsoft ortamı" :options="['test' => 'Test ortamı', 'live' => 'Canlı (gerçek fatura)']" :value="$s['uyumsoft_mode']"
                        :hint="'Test: '.$urls['test'].' · Canlı: '.$urls['live']" />
                    <x-field name="uyumsoft_username" label="Uyumsoft kullanıcı adı" :value="$s['uyumsoft_username']" maxlength="100" autocomplete="off" />
                    <x-field name="uyumsoft_password" label="Uyumsoft şifresi" type="password" maxlength="200" autocomplete="new-password"
                        :hint="$hasPassword ? 'Kayıtlı. Değiştirmek için yenisini yazın; boş bırakılırsa korunur.' : 'Uyumsoft\'un verdiği web servis şifresi.'" />
                </div>
            </x-card>

            <x-card title="Fatura bilgileri" description="Satıcı unvanı, adresi ve vergi dairesi Mağaza Ayarları'ndan alınır.">
                <div class="space-y-5">
                    <x-field name="invoice_sender_id" label="Gönderici VKN / TCKN" :value="$s['invoice_sender_id']" maxlength="11" inputmode="numeric"
                        :hint="'Uyumsoft hesabınızın kayıtlı olduğu kimlik numarası (şahıs firmasında genellikle sahibin TCKN\'si). Boşsa Mağaza Ayarları\'ndaki vergi numarası kullanılır'.($s['company_tax_number'] ? ' ('.$s['company_tax_number'].')' : '').'.'" />
                    <x-field name="invoice_shipping_vat" label="Kargo / kapıda ödeme bedeli KDV oranı" :options="['20' => '%20', '10' => '%10', '1' => '%1', '0' => '%0']" :value="$s['invoice_shipping_vat']"
                        hint="Taslakta kargo ve kapıda ödeme bedeli ayrı hizmet kalemi olarak gelir; oranı taslakta da değiştirebilirsiniz." />
                    <x-toggle name="invoice_notify" label="Kesilen faturayı müşteriye e-postayla bildir (varsayılan)" :checked="$s['invoice_notify'] === '1'"
                        hint="Her taslakta ayrıca değiştirilebilir. Müşteri faturayı sipariş sayfasından indirir." />
                </div>
            </x-card>
        </div>

        <x-button>Kaydet</x-button>
    </form>

    <x-card title="Bağlantıyı dene" description="Kayıtlı ayarlarla sağlayıcıya bağlanılıp bağlanılamadığını gösterir; fatura kesilmez." class="mt-6">
        <form method="POST" action="{{ route('store_admin.invoice-settings.test') }}">@csrf<x-button variant="secondary" icon="refresh">Bağlantıyı dene</x-button></form>
    </x-card>
</x-layouts.panel>
