<?php

namespace App\Domain\Store\Invoicing\Providers;

use App\Models\Store\StoreEInvoice;

/**
 * e-Fatura / e-Arşiv sağlayıcısı (entegratör). Sürücüler: deneme (geliştirme ve deneme, hiçbir yere göndermez) ve
 * uyumsoft. Hatalar:
 *   EInvoiceRejected  → sağlayıcı faturayı reddetti; fatura kesilmedi, taslak düzeltilip yeniden gönderilebilir.
 *   EInvoiceUncertain → bağlantı koptu / yanıt okunamadı; fatura kesilmiş olabilir, önce sağlayıcıdan sorgulanmalı.
 */
interface EInvoiceProvider
{
    public function key(): string;

    public function isConfigured(): bool;

    /**
     * Alıcı e-Fatura mükellefi mi (GİB kayıtlı kullanıcı listesi)? Mükellefse e-Fatura, değilse e-Arşiv kesilir.
     *
     * @return array{registered: bool, alias: ?string, title: ?string}
     *
     * @throws EInvoiceUncertain
     */
    public function lookup(string $taxNumber): array;

    /**
     * Faturayı keser. $documentType: e_arsiv | e_fatura.
     *
     * @return array{invoice_no: string, ettn: string, pdf: string} pdf: dosya içeriği
     *
     * @throws EInvoiceRejected|EInvoiceUncertain
     */
    public function send(StoreEInvoice $invoice, string $documentType, ?string $alias): array;

    /**
     * Sonucu belirsiz kalan gönderimi yerel belge kimliğiyle sorgular. Bulunamazsa null (fatura kesilmemiş).
     *
     * @return array{invoice_no: string, ettn: string, pdf: string}|null
     *
     * @throws EInvoiceUncertain
     */
    public function find(StoreEInvoice $invoice): ?array;

    /** e-Arşiv faturasını iptal eder (e-Faturada iptal sağlayıcıdan yapılamaz). @throws EInvoiceRejected|EInvoiceUncertain */
    public function cancel(StoreEInvoice $invoice, string $reason): void;

    /** Ayar ekranındaki "Bağlantıyı dene": kullanıcı adı ve şifreyle oturum açılabiliyor mu. @return string sonuç metni @throws EInvoiceRejected|EInvoiceUncertain */
    public function test(): string;
}
