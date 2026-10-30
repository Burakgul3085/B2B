<?php

namespace App\Domain\Store\Print;

use Illuminate\Validation\ValidationException;

/**
 * Bir baskı işinin seçenekleri: renk, tek yüz / önlü arkalı, bir yüze kaç sayfa (1, 2, 4, 8), kağıt boyutu, sayfa aralığı,
 * kopya, ciltleme ve not. Tarayıcıdan gelen değerler burada doğrulanır; fiyat yalnızca PrintPricing ile hesaplanır.
 */
final class PrintOptions
{
    public const COLORS = ['bw' => 'Siyah-beyaz', 'color' => 'Renkli'];

    public const LAYOUTS = [1 => 'Bir yüze 1 sayfa', 2 => 'Bir yüze 2 sayfa', 4 => 'Bir yüze 4 sayfa', 8 => 'Bir yüze 8 sayfa'];

    public const BINDINGS = ['none' => 'Ciltsiz', 'staple' => 'Zımbalı', 'spiral' => 'Spiral cilt'];

    public const SIZES = ['A4' => 'A4', 'A3' => 'A3'];

    public function __construct(
        public readonly string $color = 'bw',
        public readonly bool $duplex = false,
        public readonly int $layout = 1,
        public readonly string $size = 'A4',
        public readonly ?string $range = null,
        public readonly int $copies = 1,
        public readonly string $binding = 'none',
        public readonly ?string $note = null,
    ) {}

    /**
     * Formdan gelen seçenekleri doğrular.
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input, int $totalPages, PrintSettings $settings): self
    {
        $errors = [];
        $color = (string) ($input['color'] ?? 'bw');
        $layout = (int) ($input['layout'] ?? 1);
        $size = (string) ($input['size'] ?? 'A4');
        $binding = (string) ($input['binding'] ?? 'none');
        $copies = filter_var($input['copies'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $settings->maxCopies()]]);
        $range = trim((string) ($input['range'] ?? '')) ?: null;
        $note = trim((string) ($input['note'] ?? '')) ?: null;

        if (! isset(self::COLORS[$color])) {
            $errors['color'] = 'Renk seçin.';
        }
        if (! isset(self::LAYOUTS[$layout])) {
            $errors['layout'] = 'Sayfa düzeni seçin.';
        }
        if (! isset(self::SIZES[$size]) || ($size === 'A3' && ! $settings->a3Enabled())) {
            $errors['size'] = 'Bu kağıt boyutu kullanılamıyor.';
        }
        if (! isset(self::BINDINGS[$binding])) {
            $errors['binding'] = 'Ciltleme seçin.';
        }
        if ($copies === false) {
            $errors['copies'] = 'Kopya adedi 1 ile '.$settings->maxCopies().' arasında olmalıdır.';
        }
        if ($range !== null && self::parseRange($range, $totalPages) === null) {
            $errors['range'] = "Sayfa aralığını 1-{$totalPages} arasında yazın (ör. 1-10, 15, 20-25).";
        }
        if ($note !== null && mb_strlen($note) > 300) {
            $errors['note'] = 'Not en fazla 300 karakter olabilir.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return new self($color, filter_var($input['duplex'] ?? false, FILTER_VALIDATE_BOOL), $layout, $size, $range, (int) $copies, $binding, $note);
    }

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['color'] ?? 'bw'), (bool) ($data['duplex'] ?? false), (int) ($data['layout'] ?? 1), (string) ($data['size'] ?? 'A4'),
            $data['range'] ?? null, (int) ($data['copies'] ?? 1), (string) ($data['binding'] ?? 'none'), $data['note'] ?? null);
    }

    /** Aynı seçenekler, önlü arkalı (fiyat ipucu için). */
    public function withDuplex(): self
    {
        return new self($this->color, true, $this->layout, $this->size, $this->range, $this->copies, $this->binding, $this->note);
    }

    public function toArray(): array
    {
        return ['color' => $this->color, 'duplex' => $this->duplex, 'layout' => $this->layout, 'size' => $this->size,
            'range' => $this->range, 'copies' => $this->copies, 'binding' => $this->binding, 'note' => $this->note];
    }

    /** Basılacak sayfa sayısı (aralık verildiyse aralıktaki sayfalar). */
    public function selectedPages(int $totalPages): int
    {
        return $this->range === null ? $totalPages : count(self::parseRange($this->range, $totalPages) ?? []);
    }

    /** "1-5, 8, 10-12" → sayfa numaraları; geçersizse null. */
    public static function parseRange(string $range, int $totalPages): ?array
    {
        $pages = [];
        foreach (preg_split('/\s*[,;]\s*/', trim($range)) as $part) {
            if (! preg_match('/^(\d+)(?:\s*-\s*(\d+))?$/', $part, $m)) {
                return null;
            }
            $from = (int) $m[1];
            $to = isset($m[2]) ? (int) $m[2] : $from;
            if ($from < 1 || $to < $from || $to > $totalPages) {
                return null;
            }
            for ($p = $from; $p <= $to; $p++) {
                $pages[$p] = true;
            }
        }

        return $pages ? array_keys($pages) : null;
    }

    /** $sheets: kopya başı yaprak; verilirse toplam yaprak özete eklenir. */
    public function summary(int $pages, ?int $sheets = null): string
    {
        return implode(' · ', array_filter([
            $pages.' sayfa'.($this->range ? " ({$this->range})" : ''),
            $this->size !== 'A4' ? $this->size : null,
            self::COLORS[$this->color] ?? $this->color,
            $this->duplex ? 'Önlü arkalı' : 'Tek yüz',
            $this->layout > 1 ? "Bir yüze {$this->layout} sayfa" : null,
            $this->binding !== 'none' ? self::BINDINGS[$this->binding] : null,
            $this->copies.' kopya',
            $sheets !== null ? 'toplam '.($sheets * $this->copies).' yaprak' : null,
        ]));
    }
}
