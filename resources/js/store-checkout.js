/**
 * Mağaza ödeme sayfası: teslimat ve ödeme seçeneklerine göre tutar önizlemesi.
 * Yalnızca görüntü içindir; sipariş tutarı ve kapıda ödeme uygunluğu sunucuda yeniden hesaplanır.
 * Başlangıç verisi (kurallar) sayfadaki x-data="checkout(...)" ile gelir.
 */
export function checkout(settings) {
    return {
        ...settings,
        submitting: false,
        // Gönderilince düğme kilitlenir (çift tıklama); geri tuşuyla sayfaya dönülürse yeniden açılır.
        lock() {
            this.submitting = true;
        },
        init() {
            window.addEventListener('pageshow', () => { this.submitting = false; });
            if (this.province) this.loadDistricts(true);
            this.$watch('isLocal', (v) => { if (!v && this.delivery === 'local') this.delivery = 'cargo'; });
            this.$watch('codAllowed', (v) => { if (!v && this.payment === 'cod') this.payment = this.cardAvailable ? 'card' : (this.transferAvailable ? 'transfer' : ''); });
        },
        async loadDistricts(keep) {
            if (!keep) this.district = '';
            this.districts = [];
            if (!this.province) return;
            const res = await fetch('/ilceler/' + this.province, { headers: { Accept: 'application/json' } });
            this.districts = res.ok ? await res.json() : [];
        },
        get location() {
            if (this.addressMode === 'saved') {
                const a = this.saved.find((x) => x.id === this.addressId);
                return a ? { province: a.province, district: a.district } : { province: 0, district: 0 };
            }
            return { province: Number(this.province), district: Number(this.district) };
        },
        get isLocal() {
            return this.localEnabled && this.location.province === this.localProvince && this.localDistricts.includes(this.location.district);
        },
        // Kargo firması seçimi: ücretsiz kargo sınırı aşılmadıysa ve ücretli firma varsa müşteri seçer.
        get needsCarrier() { return this.delivery === 'cargo' && !this.cargoFree && this.carriers.length > 0; },
        get chosenCarrier() { return this.carriers.find((c) => c.id === Number(this.carrierId)) || null; },
        get cargoPrice() {
            if (this.cargoFree || this.shippingWaived) return 0;
            if (this.carriers.length) return this.chosenCarrier ? this.chosenCarrier.fee : 0;
            return this.cargoFee;
        },
        get cargoLabel() {
            if (this.cargoFree) return 'Ücretsiz';
            if (this.carriers.length) return this.chosenCarrier ? this.chosenCarrier.name : 'Firma seçin';
            return this.positive(this.cargoFee) ? this.money(this.cargoFee) : 'Ücretsiz';
        },
        carrierPrice(c) { return this.shippingWaived || !this.positive(c.fee) ? 'Ücretsiz' : this.money(c.fee); },
        isCarrier(c) { return Number(this.carrierId) === c.id; },
        get shippingFee() { return this.delivery === 'local' ? this.localFee : this.cargoPrice; },
        get codTotal() { return Number(this.subtotal) + Number(this.shippingFee) + Number(this.codFee); },
        get codAllowed() {
            return this.codEnabled && this.delivery === 'local' && this.isLocal && (!this.codMax || this.codTotal <= Number(this.codMax));
        },
        get codReason() {
            if (!this.codEnabled) return 'Şu anda kullanılamıyor';
            if (!this.isLocal || this.delivery !== 'local') return 'Eskişehir içi mağaza teslimatında';
            if (this.codMax && this.codTotal > Number(this.codMax)) return this.money(this.codMax) + ' ve altındaki siparişlerde';
            return Number(this.codFee) > 0 ? 'Nakit veya kart · ' + this.money(this.codFee) + ' hizmet bedeli' : 'Teslimatta nakit veya kartla';
        },
        get total() { return Number(this.subtotal) + Number(this.shippingFee) + (this.payment === 'cod' ? Number(this.codFee) : 0); },
        get submitLabel() {
            if (this.submitting) return this.payment === 'card' ? 'Ödeme sayfasına yönlendiriliyor…' : 'Gönderiliyor…';
            return this.payment === 'card' ? 'Ödemeye geç' : 'Siparişi onayla';
        },
        get submitHint() {
            if (this.payment === 'cod') return 'Telefonunuza gelen kodla siparişi doğrulayacaksınız.';
            if (this.payment === 'transfer') return 'Hesap bilgileri ve son ödeme zamanı sipariş sayfasında ve e-postada gösterilir.';
            return 'Kart bilgileriniz iyzico güvenli ödeme sayfasında alınır; mağazamızda saklanmaz.';
        },
        positive(v) { return Number(v) > 0; },
        money(v) { return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(Number(v)); },
    };
}
