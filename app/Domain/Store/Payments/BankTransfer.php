<?php

namespace App\Domain\Store\Payments;

use App\Domain\Store\Returns\ReturnService;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;
use Carbon\CarbonInterface;

/**
 * Havale / EFT ile ödeme (isteğe bağlı, varsayılan kapalı). Ödeme sağlayıcısı yoktur:
 *  - Sipariş "havale bekleniyor" durumunda oluşur, stok hemen satışa düşer (başkasına satılmaz).
 *  - Müşteri, sipariş numarasını açıklamaya yazarak gösterilen hesaplardan birine ödeme yapar.
 *  - Yönetici hesabına geçen ödemeyi görünce "Havale alındı" ile onaylar; sipariş hazırlanmaya geçer.
 *  - Süresinde (transfer_days) ödenmeyen sipariş kendiliğinden iptal edilir, stok geri döner.
 */
class BankTransfer
{
    public const MAX_ACCOUNTS = 5;

    public static function available(): bool
    {
        return StoreSetting::flag('transfer_enabled') && self::accounts() !== [];
    }

    /**
     * Kayıtlı hesaplar; IBAN'dan tanınan bankanın adı ve logosuyla (tanınmayanda logo null).
     *
     * @return list<array{bank: string, holder: string, iban: string, logo: ?string}>
     */
    public static function accounts(): array
    {
        return array_map(function ($a) {
            $known = TurkishBanks::fromIban($a['iban']);

            return ['bank' => $known['name'] ?? $a['bank'], 'holder' => $a['holder'], 'iban' => $a['iban'], 'logo' => $known['logo'] ?? null];
        }, self::parse((string) StoreSetting::get('transfer_accounts'))['accounts']);
    }

    public static function days(): int
    {
        return max(1, min(7, (int) StoreSetting::get('transfer_days')));
    }

    /** Ödemenin en geç yapılacağı an. */
    public static function deadline(StoreOrder $order): CarbonInterface
    {
        return $order->created_at->copy()->addDays(self::days());
    }

    /**
     * "Banka | Hesap sahibi | IBAN" satırlarını ayrıştırır.
     *
     * @return array{accounts: list<array{bank: string, holder: string, iban: string}>, errors: list<string>}
     */
    public static function parse(string $text): array
    {
        $accounts = [];
        $errors = [];
        foreach (preg_split('/\R/', $text) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $iban = count($parts) === 3 ? ReturnService::normalizeIban($parts[2]) : null;
            if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || ! $iban) {
                $errors[] = ($i + 1).'. satır "Banka | Hesap sahibi | IBAN" biçiminde olmalı ve geçerli bir TR IBAN içermeli.';

                continue;
            }
            $accounts[] = ['bank' => mb_substr($parts[0], 0, 60), 'holder' => mb_substr($parts[1], 0, 120), 'iban' => $iban];
        }
        if (count($accounts) > self::MAX_ACCOUNTS) {
            $errors[] = 'En fazla '.self::MAX_ACCOUNTS.' hesap girilebilir.';
        }

        return ['accounts' => array_slice($accounts, 0, self::MAX_ACCOUNTS), 'errors' => $errors];
    }

    /**
     * Yönetim formundaki hesap satırları (holder, iban, bank). Banka IBAN'dan tanınırsa adı oradan alınır;
     * tanınmayan bankada ad zorunludur. Tamamen boş satırlar atlanır. Hatalar satır alanlarına göre döner.
     *
     * @return array{accounts: list<array{bank: string, holder: string, iban: string}>, errors: array<string, string>}
     */
    public static function fromRows(array $rows): array
    {
        $accounts = [];
        $errors = [];
        foreach (array_values($rows) as $i => $row) {
            $row = is_array($row) ? $row : [];
            $holder = trim((string) ($row['holder'] ?? ''));
            $rawIban = trim((string) ($row['iban'] ?? ''));
            $bank = trim((string) ($row['bank'] ?? ''));
            if ($holder === '' && $rawIban === '' && $bank === '') {
                continue;
            }
            $key = "transfer_accounts.{$i}";
            $iban = ReturnService::normalizeIban($rawIban);
            if (! $iban) {
                $errors["{$key}.iban"] = 'Geçerli bir TR IBAN girin (TR ile başlayan 26 karakter).';
            }
            if ($holder === '') {
                $errors["{$key}.holder"] = 'Hesap sahibini yazın.';
            }
            $known = $iban ? TurkishBanks::fromIban($iban) : null;
            if ($iban && ! $known && $bank === '') {
                $errors["{$key}.bank"] = "Bu IBAN'ın bankası tanınmadı; banka adını yazın.";
            }
            if ($iban && $holder !== '' && ($known || $bank !== '')) {
                $accounts[] = ['bank' => mb_substr(str_replace('|', ' ', $known['name'] ?? $bank), 0, 60), 'holder' => mb_substr(str_replace('|', ' ', $holder), 0, 120), 'iban' => $iban];
            }
        }
        if (count($accounts) > self::MAX_ACCOUNTS) {
            $errors['transfer_accounts'] = 'En fazla '.self::MAX_ACCOUNTS.' hesap girilebilir.';
        }

        return ['accounts' => $accounts, 'errors' => $errors];
    }

    /** TR00 0000 0000 ... biçiminde gösterim. */
    public static function formatIban(string $iban): string
    {
        return trim(chunk_split($iban, 4, ' '));
    }
}
