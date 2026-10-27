<?php

namespace App\Domain\Store\Reports;

use App\Domain\Mail\EmailLogo;
use App\Models\Store\StoreSetting;

/** Mağaza raporlarının (Excel, PDF) kurumsal görünümü: yapboz renkleri, satıcı bilgileri ve logo. */
final class StoreReportBranding
{
    /** Başlık zemini (lacivert) ve vurgu (turuncu); # olmadan. */
    public const NAVY = '0E1C3D';

    public const ORANGE = 'EA580C';

    /** Yapboz logosunun dört rengi (PDF şeridi). */
    public const PUZZLE = ['EA580C', '16A34A', '0284C7', 'CA8A04'];

    public const NOTE = 'Bu rapor ticari takip amaçlıdır; resmî muhasebe kaydı yerine geçmez. Tutarlar KDV dahildir.';

    public static function company(): string
    {
        return StoreSetting::get('company_title') ?: config('store.name');
    }

    /** Antet altındaki satıcı bilgileri (boş olanlar atlanır). @return string[] */
    public static function contactLines(): array
    {
        $s = StoreSetting::values();
        $contact = implode('  ·  ', array_filter([$s['phone'] ? 'Tel: '.$s['phone'] : null, $s['email'], config('store.domain')]));
        $tax = $s['company_tax_number'] ? trim(($s['company_tax_office'] ? $s['company_tax_office'].' V.D.' : 'Vergi no').' '.$s['company_tax_number']) : null;
        $registry = $s['company_mersis'] ? 'MERSİS: '.$s['company_mersis'] : ($s['company_esnaf_no'] ? 'Esnaf ve Sanatkârlar Sicil No: '.$s['company_esnaf_no'] : null);

        return array_values(array_filter([$s['address'], $contact, implode('  ·  ', array_filter([$tax, $registry]))]));
    }

    public static function now(): string
    {
        return now()->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    }

    /** PDF'e gömülen yapboz logosu. */
    public static function logoDataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode(EmailLogo::puzzle(40));
    }
}
