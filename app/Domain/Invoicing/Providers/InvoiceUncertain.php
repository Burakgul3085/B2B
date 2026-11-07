<?php

namespace App\Domain\Invoicing\Providers;

use DomainException;

/** Sağlayıcıya ulaşılamadı veya yanıt okunamadı; işlemin yapılıp yapılmadığı bilinmiyor. */
class InvoiceUncertain extends DomainException {}
