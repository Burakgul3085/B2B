/**
 * Mağaza ödeme sayfası: teslimat ve ödeme seçeneklerine göre tutar önizlemesi.
 * Yalnızca görüntü içindir; sipariş tutarı, teslimat ve ödeme uygunluğu sunucuda yeniden hesaplanır (DeliveryRules, CodPolicy).
 * Başlangıç verisi (kurallar) sayfadaki x-data="checkout(...)" ile gelir.
 *
 * Teslimat: kargo (tüm Türkiye), mağazadan teslim al (Eskişehir, ücretsiz), eve teslim (Eskişehir'in seçili ilçeleri,
 * en düşük tutar ve ücretsiz sınırla). Ödeme: kart her yerde; havale yalnızca kargoda; teslimde ödeme yalnızca
 * mağazadan teslim al ("Mağazada öde") ve eve teslimde ("Kapıda öde").
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
            this.$watch('deliveryChoices', () => this.fixDelivery());
            this.$watch('paymentChoices', () => this.fixPayment());
            this.fixDelivery();
            this.fixPayment();
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
        get isEskisehir() { return this.location.province === this.localProvince; },
        // Eve teslim bölgesi: Eskişehir'in seçili ilçeleri.
        get isLocal() {
            return this.localEnabled && this.isEskisehir && this.localDistricts.includes(this.location.district);
        },
        get homeMinOk() { return !this.localMin || Number(this.subtotal) >= Number(this.localMin); },
        get canHome() { return this.isLocal && this.homeMinOk; },
        get canPickup() { return this.pickupEnabled && this.isEskisehir; },
        get showCargo() { return !(this.hideCargo && this.canHome); },
        // Seçilebilen teslimat ve ödeme yöntemleri (değişince geçersiz seçim düzeltilir).
        get deliveryChoices() {
            return [this.showCargo ? 'cargo' : '', this.canPickup ? 'pickup' : '', this.canHome ? 'local' : ''].filter(Boolean).join(',');
        },
        get paymentChoices() {
            return [this.cardAvailable ? 'card' : '', this.codAllowed ? 'cod' : '', this.transferAllowed ? 'transfer' : ''].filter(Boolean).join(',');
        },
        fixDelivery() {
            const choices = this.deliveryChoices.split(',').filter(Boolean);
            if (!choices.includes(this.delivery)) this.delivery = choices[0] || 'cargo';
        },
        fixPayment() {
            const choices = this.paymentChoices.split(',').filter(Boolean);
            if (!choices.includes(this.payment)) this.payment = choices[0] || '';
        },
        isDelivery(method) { return this.delivery === method; },
        isPayment(method) { return this.payment === method; },
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
        get pickupLabel() { return this.canPickup ? 'Ücretsiz' : 'Yalnızca Eskişehir adreslerinde'; },
        get homeLabel() {
            if (!this.localEnabled) return 'Şu anda kullanılamıyor';
            if (!this.isEskisehir) return 'Yalnızca Eskişehir içi';
            if (!this.isLocal) return 'Bu ilçeye eve teslim yok';
            if (!this.homeMinOk) return this.money(this.localMin) + ' ve üzeri siparişlerde';
            return this.positive(this.localFee) ? this.money(this.localFee) : 'Ücretsiz';
        },
        get homeHint() {
            if (this.canHome && this.positive(this.localFee) && this.localFreeThreshold) return this.money(this.localFreeThreshold) + ' ve üzeri ücretsiz';
            return 'Kendi aracımızla adresinize getiririz';
        },
        get shippingFee() {
            if (this.delivery === 'pickup') return 0;
            return this.delivery === 'local' ? this.localFee : this.cargoPrice;
        },
        get codTotal() { return Number(this.subtotal) + Number(this.shippingFee) + Number(this.codFee); },
        get codAllowed() {
            const eligible = (this.delivery === 'pickup' && this.canPickup) || (this.delivery === 'local' && this.canHome);
            return this.codEnabled && eligible && (!this.codMax || this.codTotal <= Number(this.codMax));
        },
        // Teslimde ödeme yalnızca Eskişehir'de (mağazadan teslim al / eve teslim) gösterilir; kargoda hiç görünmez.
        get showCod() { return this.codEnabled && (this.delivery === 'pickup' || this.delivery === 'local'); },
        get codTitle() { return this.delivery === 'pickup' ? 'Mağazada öde (nakit veya POS)' : 'Kapıda öde (nakit veya POS)'; },
        get codReason() {
            if (this.codMax && this.codTotal > Number(this.codMax)) return this.money(this.codMax) + ' ve altındaki siparişlerde';
            const where = this.delivery === 'pickup' ? 'Teslim alırken mağazada' : 'Teslimatta kapıda';
            return Number(this.codFee) > 0 ? where + ' · ' + this.money(this.codFee) + ' hizmet bedeli' : where + ' nakit veya kartla';
        },
        get codFeeLabel() { return this.delivery === 'pickup' ? 'Mağazada ödeme bedeli' : 'Kapıda ödeme bedeli'; },
        get transferAllowed() { return this.transferAvailable && this.delivery === 'cargo'; },
        get cardTitle() { return this.delivery === 'cargo' ? 'Kredi / banka kartı' : 'Şimdi internetten kartla öde'; },
        get cardReason() {
            if (!this.cardAvailable) return 'Şu anda kullanılamıyor';
            return this.delivery === 'pickup' ? 'iyzico ile şimdi ödeyin, mağazadan yalnızca teslim alın' : 'iyzico güvenli ödeme sayfasında; taksit seçenekleriyle';
        },
        get total() { return Number(this.subtotal) + Number(this.shippingFee) + (this.payment === 'cod' ? Number(this.codFee) : 0); },
        get submitLabel() {
            if (this.submitting) return this.payment === 'card' ? 'Ödeme sayfasına yönlendiriliyor…' : 'Gönderiliyor…';
            return this.payment === 'card' ? 'Ödemeye geç' : 'Siparişi onayla';
        },
        get submitHint() {
            if (this.payment === 'cod') {
                return this.delivery === 'pickup'
                    ? 'Siparişiniz hazır olunca e-postayla haber veririz; ödemeyi mağazada nakit veya kartla (POS) yaparsınız.'
                    : 'Ödemeyi teslimatta kapıda nakit veya kartla (POS) yaparsınız.';
            }
            if (this.payment === 'transfer') return 'Hesap bilgileri ve son ödeme zamanı sipariş sayfasında ve e-postada gösterilir.';
            return 'Kart bilgileriniz iyzico güvenli ödeme sayfasında alınır; mağazamızda saklanmaz.';
        },
        positive(v) { return Number(v) > 0; },
        money(v) { return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(Number(v)); },
    };
}
