<?php

namespace App\Domain\Support\Payments;

/** Havale / EFT hesap listesi: yönetim formundaki satırların doğrulanması ve gösterim için zenginleştirilmesi (mağaza ve B2B). */
final class BankAccounts
{
    /**
     * Formdaki hesap satırları (holder, iban, bank). Banka IBAN'dan tanınırsa adı oradan alınır; tanınmayan bankada ad
     * zorunludur. Tamamen boş satırlar atlanır. Hatalar "{$field}.{satır}.{alan}" anahtarlarıyla döner.
     *
     * @return array{accounts: list<array{bank: string, holder: string, iban: string}>, errors: array<string, string>}
     */
    public static function fromRows(array $rows, string $field, int $max): array
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
            $key = "{$field}.{$i}";
            $iban = Iban::normalize($rawIban);
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
        if (count($accounts) > $max) {
            $errors[$field] = "En fazla {$max} hesap girilebilir.";
        }

        return ['accounts' => $accounts, 'errors' => $errors];
    }

    /**
     * Kayıtlı hesaplar, IBAN'dan tanınan bankanın adı ve logosuyla (tanınmayanda logo null).
     *
     * @param  list<array{bank: string, holder: string, iban: string}>  $accounts
     * @return list<array{bank: string, holder: string, iban: string, logo: ?string}>
     */
    public static function present(array $accounts): array
    {
        return array_map(function ($a) {
            $known = TurkishBanks::fromIban($a['iban']);

            return ['bank' => $known['name'] ?? $a['bank'], 'holder' => $a['holder'], 'iban' => $a['iban'], 'logo' => $known['logo'] ?? null];
        }, $accounts);
    }
}
