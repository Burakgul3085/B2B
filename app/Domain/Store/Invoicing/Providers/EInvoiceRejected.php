<?php

namespace App\Domain\Store\Invoicing\Providers;

use DomainException;

/** Sağlayıcı faturayı reddetti (ör. eksik alan, geçersiz VKN); fatura kesilmedi. */
class EInvoiceRejected extends DomainException {}
