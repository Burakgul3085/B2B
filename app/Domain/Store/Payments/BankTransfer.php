<?php

namespace App\Domain\Store\Payments;

use App\Domain\Support\Payments\BankAccounts;
use App\Domain\Support\Payments\Iban;
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
        return BankAccounts::present(self::parse((string) StoreSetting::get('transfer_accounts'))['accounts']);
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
            $iban = count($parts) === 3 ? Iban::normalize($parts[2]) : null;
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
     * Yönetim formundaki hesap satırları (holder, iban, bank); kurallar BankAccounts::fromRows içindedir.
     *
     * @return array{accounts: list<array{bank: string, holder: string, iban: string}>, errors: array<string, string>}
     */
    public static function fromRows(array $rows): array
    {
        return BankAccounts::fromRows($rows, 'transfer_accounts', self::MAX_ACCOUNTS);
    }

    /** TR00 0000 0000 ... biçiminde gösterim. */
    public static function formatIban(string $iban): string
    {
        return Iban::format($iban);
    }
}
