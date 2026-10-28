<?php

namespace App\Domain\Store\Invoicing\Providers;

use DomainException;

/** Sağlayıcıya ulaşılamadı veya yanıt okunamadı; işlemin yapılıp yapılmadığı bilinmiyor. */
class EInvoiceUncertain extends DomainException {}
