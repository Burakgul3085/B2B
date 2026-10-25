<?php

namespace App\Domain\Store\Storefront;

/**
 * Mağazanın telefon numaraları (Mağaza Ayarları → İletişim): ana telefon ve isteğe bağlı ikinci telefon, her biri
 * isteğe bağlı bir etiketle (ör. "Dükkân", "Cep"). Alt bilgi, İletişim ve Yardım sayfaları bu listeyi kullanır.
 */
final class StorePhones
{
    /** @return list<array{label: string, number: string, tel: string}> boş numaralar atılır */
    public static function list(array $settings): array
    {
        $phones = [];
        foreach ([['phone', 'phone_label', 'Telefon'], ['phone2', 'phone2_label', 'Telefon 2']] as [$key, $labelKey, $default]) {
            $number = trim((string) ($settings[$key] ?? ''));
            if ($number === '') {
                continue;
            }
            $phones[] = [
                'label' => trim((string) ($settings[$labelKey] ?? '')) ?: $default,
                'number' => $number,
                'tel' => 'tel:'.preg_replace('/[^\d+]/', '', $number),
            ];
        }
        // Tek numara varsa ve etiketi verilmemişse "Telefon 2" yerine "Telefon" yazılır.
        if (count($phones) === 1 && $phones[0]['label'] === 'Telefon 2') {
            $phones[0]['label'] = 'Telefon';
        }

        return $phones;
    }
}
