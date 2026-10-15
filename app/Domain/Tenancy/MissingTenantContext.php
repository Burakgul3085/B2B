<?php

namespace App\Domain\Tenancy;

use LogicException;

/**
 * İşletmeye ait bir tablo, işletme bağlamı kurulmadan sorgulandı.
 * Sessizce tüm işletmelerin verisini döndürmek yerine işlem durdurulur.
 */
class MissingTenantContext extends LogicException
{
    public function __construct()
    {
        parent::__construct('İşletme bağlamı kurulmadan işletmeye ait veri sorgulanamaz.');
    }
}
