<?php

namespace App\Domain\Store\Reports;

use App\Models\Store\StoreOrder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Seçilen dönemdeki siparişlerin Excel listesi (akış hâlinde yazılır). Metin hücreleri formül olarak
 * yorumlanmasın diye =, +, -, @ ile başlıyorsa başına ' eklenir (CSV/Excel formül enjeksiyonu).
 */
class OrderExport
{
    private const COLUMNS = [
        'Sipariş no', 'Tarih', 'Durum', 'Müşteri', 'E-posta', 'Telefon', 'İl', 'İlçe', 'Ödeme', 'Teslimat',
        'Ürünler (₺)', 'İndirim (₺)', 'Teslimat (₺)', 'Kapıda ödeme bedeli (₺)', 'Toplam (₺)', 'İade edilen (₺)', 'Kupon / kampanya', 'Fatura no',
    ];

    public function write(string $path, string $from, string $to): int
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Siparişler');
        $head = (new Style)->withFontBold(true);
        $money = (new Style)->withFormat('#,##0.00');
        $writer->addRow(new Row(array_map(fn ($c) => Cell::fromValue($c, $head), self::COLUMNS)));

        $count = 0;
        StoreOrder::query()->with('invoice:id,order_id,invoice_no')
            ->whereBetween('created_at', StoreReports::bounds($from, $to))
            ->orderBy('id')
            ->chunk(500, function ($orders) use ($writer, $money, &$count) {
                foreach ($orders as $o) {
                    $writer->addRow(new Row([
                        Cell::fromValue(self::text($o->order_no)),
                        Cell::fromValue($o->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')),
                        Cell::fromValue($o->statusLabel()),
                        Cell::fromValue(self::text($o->shipping_name)),
                        Cell::fromValue(self::text($o->email)),
                        Cell::fromValue(self::text($o->phone)),
                        Cell::fromValue(self::text($o->shipping_province)),
                        Cell::fromValue(self::text($o->shipping_district)),
                        Cell::fromValue(StoreOrder::PAYMENT_METHODS[$o->payment_method] ?? $o->payment_method),
                        Cell::fromValue(StoreOrder::DELIVERY_METHODS[$o->delivery_method] ?? $o->delivery_method),
                        Cell::fromValue((float) $o->subtotal, $money),
                        Cell::fromValue((float) $o->discount_total, $money),
                        Cell::fromValue((float) $o->shipping_fee, $money),
                        Cell::fromValue((float) $o->cod_fee, $money),
                        Cell::fromValue((float) $o->total, $money),
                        Cell::fromValue((float) $o->refunded_total, $money),
                        Cell::fromValue(self::text($o->discount_name)),
                        Cell::fromValue(self::text($o->invoice?->invoice_no)),
                    ]));
                    $count++;
                }
            });
        $writer->close();

        return $count;
    }

    public static function text(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
