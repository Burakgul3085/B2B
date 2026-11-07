<?php

namespace App\Domain\Invoicing\Providers;

use App\Models\Invoice;

/**
 * B2B e-Fatura / e-Arşiv sağlayıcısı (entegratör). Sürücüler: deneme (hiçbir yere göndermez) ve uyumsoft.
 * Mağazanın sağlayıcılarından ayrıdır; ayarları toptancının Fatura Ayarları'ndan okur. Hatalar:
 *   InvoiceRejected  → sağlayıcı faturayı reddetti; fatura kesilmedi, taslak düzeltilip yeniden gönderilebilir.
 *   InvoiceUncertain → bağlantı koptu / yanıt okunamadı; fatura kesilmiş olabilir, önce sağlayıcıdan sorgulanmalı.
 */
interface InvoiceProvider
{
    public function key(): string;

    public function isConfigured(): bool;

    /**
     * Alıcı e-Fatura mükellefi mi? Mükellefse e-Fatura, değilse e-Arşiv kesilir.
     *
     * @return array{registered: bool, alias: ?string, title: ?string}
     *
     * @throws InvoiceUncertain|InvoiceRejected
     */
    public function lookup(string $taxNumber): array;

    /**
     * Faturayı keser. $documentType: e_arsiv | e_fatura.
     *
     * @return array{invoice_no: string, ettn: string, pdf: string}
     *
     * @throws InvoiceRejected|InvoiceUncertain
     */
    public function send(Invoice $invoice, string $documentType, ?string $alias): array;

    /**
     * Sonucu belirsiz kalan gönderimi ETTN ile sorgular. Bulunamazsa null (fatura kesilmemiş).
     *
     * @return array{invoice_no: string, ettn: string, pdf: string}|null
     *
     * @throws InvoiceUncertain
     */
    public function find(Invoice $invoice): ?array;

    /**
     * Kesilmiş faturanın sağlayıcıdaki son durumu. state: ok | pending | error | cancelled | other. Bulunamazsa null.
     *
     * @return array{state: string, status: string, message: ?string}|null
     *
     * @throws InvoiceUncertain|InvoiceRejected
     */
    public function status(Invoice $invoice): ?array;

    /** e-Arşiv faturasını iptal eder. @throws InvoiceRejected|InvoiceUncertain */
    public function cancel(Invoice $invoice, string $reason): void;

    /** "Bağlantıyı dene". @throws InvoiceRejected|InvoiceUncertain */
    public function test(): string;
}
