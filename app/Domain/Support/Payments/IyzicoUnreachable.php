<?php

namespace App\Domain\Support\Payments;

use DomainException;

/**
 * iyzico'ya ulaşılamadı veya yanıt okunamadı: işlemin iyzico'da yapılıp yapılmadığı BİLİNMİYOR. Ödeme sorgusunda
 * yeniden denenir; iadede "sonucu belirsiz" sayılır ve iyzico panelinden kontrol edilmeden tekrar denenmez.
 */
class IyzicoUnreachable extends DomainException {}
