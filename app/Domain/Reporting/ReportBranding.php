<?php

namespace App\Domain\Reporting;

use App\Models\Business;

/** Dışa aktarılan dosyalarda (Excel, PDF) kullanılan kurumsal renk ve işletme bilgileri. */
final class ReportBranding
{
    /** İşletme rengi tanımsız veya üzerinde beyaz yazı okunamayacak kadar açıksa kullanılan lacivert. */
    public const DEFAULT_COLOR = '1E3A8A';

    public const NOTE = 'Bu rapor ticari takip amaçlıdır; resmî muhasebe kaydı yerine geçmez.';

    /** Başlık zemini: altı haneli onaltılık renk, # olmadan (ör. 1E3A8A). */
    public static function color(Business $business): string
    {
        $hex = strtoupper(ltrim((string) $business->accent_color, '#'));
        if (! preg_match('/^[0-9A-F]{6}$/', $hex)) {
            return self::DEFAULT_COLOR;
        }
        [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split($hex, 2));
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminance <= 0.45 ? $hex : self::DEFAULT_COLOR;
    }

    /** Antet altındaki iletişim satırları (boş olanlar atlanır). @return string[] */
    public static function contactLines(Business $business): array
    {
        $address = trim(implode(' ', array_filter([$business->address, trim(implode(' / ', array_filter([$business->district, $business->city])))])));
        $contact = implode('  ·  ', array_filter([$business->phone ? 'Tel: '.$business->phone : null, $business->email]));
        $tax = $business->tax_number ? trim(($business->tax_office ? $business->tax_office.' V.D.' : 'Vergi no').' '.$business->tax_number) : null;

        return array_values(array_filter([$address, $contact, $tax]));
    }

    public static function now(): string
    {
        return now()->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    }
}
