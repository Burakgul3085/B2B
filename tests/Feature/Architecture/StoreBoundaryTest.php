<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Mağaza ile B2B ayrı modüllerdir (docs/13-magaza-mimarisi.md §6): veri ve kod paylaşmazlar.
 *  - Mağaza kodu B2B modellerini ve B2B domain modüllerini kullanamaz.
 *  - B2B kodu mağaza modellerini ve mağaza domain kodunu kullanamaz.
 * Ortak altyapı serbesttir: kimlik (Auth), denetim kaydı (Audit), dosya, e-posta, Support; platform paneli
 * mağaza süper adminini yönettiği için her iki tarafı görebilir.
 */
class StoreBoundaryTest extends TestCase
{
    /** Mağaza kodunun kullanabileceği ortak domain modülleri. */
    private const SHARED_DOMAINS = ['Store', 'Auth', 'Audit', 'Files', 'Mail', 'Support'];

    /** Mağaza kodunun kullanabileceği ortak (B2B'ye ait olmayan) modeller. */
    private const SHARED_MODELS = ['AuditLog', 'AuthToken'];

    public function test_store_code_does_not_use_b2b_code(): void
    {
        $violations = [];
        foreach ($this->files(['app/Domain/Store', 'app/Http/Controllers/Store', 'app/Models/Store', 'resources/views/store']) as $path => $code) {
            preg_match_all('/App\\\\Models\\\\(\w+)/', $code, $models);
            foreach (array_unique($models[1]) as $model) {
                if ($model !== 'Store' && ! in_array($model, self::SHARED_MODELS, true)) {
                    $violations[] = "{$path}: App\\Models\\{$model}";
                }
            }

            preg_match_all('/App\\\\Domain\\\\(\w+)/', $code, $domains);
            foreach (array_unique($domains[1]) as $domain) {
                if (! in_array($domain, self::SHARED_DOMAINS, true)) {
                    $violations[] = "{$path}: App\\Domain\\{$domain}";
                }
            }

            if (preg_match('/App\\\\Http\\\\Controllers\\\\(Business|Dealer|PublicSite)\b/', $code, $m)) {
                $violations[] = "{$path}: App\\Http\\Controllers\\{$m[1]}";
            }
        }

        $this->assertSame([], $violations, "Mağaza kodu B2B koduna dokunuyor:\n".implode("\n", $violations));
    }

    public function test_b2b_code_does_not_use_store_code(): void
    {
        $b2bDomains = collect(File::directories(app_path('Domain')))
            ->map(fn ($dir) => basename($dir))
            ->reject(fn ($name) => in_array($name, self::SHARED_DOMAINS, true))
            ->map(fn ($name) => "app/Domain/{$name}")
            ->all();

        $paths = [...$b2bDomains, 'app/Http/Controllers/Business', 'app/Http/Controllers/Dealer', 'app/Http/Controllers/PublicSite',
            'resources/views/business', 'resources/views/dealer', 'resources/views/public'];
        // B2B modelleri: app/Models kökündeki dosyalar (app/Models/Store hariç)
        $models = collect(File::files(app_path('Models')))->mapWithKeys(fn ($f) => ['app/Models/'.$f->getFilename() => $f->getContents()])->all();

        $violations = [];
        foreach ($this->files($paths) + $models as $path => $code) {
            if (preg_match('/App\\\\(Models|Domain|Http\\\\Controllers)\\\\Store\b/', $code, $m)) {
                $violations[] = "{$path}: {$m[0]}";
            }
        }

        $this->assertSame([], $violations, "B2B kodu mağaza koduna dokunuyor:\n".implode("\n", $violations));
    }

    public function test_store_tables_have_no_foreign_keys_to_b2b_tables(): void
    {
        $violations = [];
        foreach ($this->files(['database/migrations']) as $path => $code) {
            if (! preg_match_all("/Schema::(?:create|table)\('(store_\w+)'.*?\n    \}\);/s", $code, $blocks)) {
                continue;
            }
            foreach ($blocks[0] as $block) {
                preg_match_all("/(?:constrained|on)\('(\w+)'\)/", $block, $refs);
                foreach ($refs[1] as $table) {
                    if (! str_starts_with($table, 'store_') && ! in_array($table, ['tr_provinces', 'tr_districts'], true)) {
                        $violations[] = "{$path}: {$table}";
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Mağaza tablosu B2B tablosuna bağlanıyor:\n".implode("\n", $violations));
    }

    /** @return array<string, string> göreli yol => içerik */
    private function files(array $dirs): array
    {
        $out = [];
        foreach ($dirs as $dir) {
            $abs = base_path($dir);
            if (! is_dir($abs)) {
                continue;
            }
            foreach (File::allFiles($abs) as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    $out[$dir.'/'.$file->getRelativePathname()] = $file->getContents();
                }
            }
        }

        return $out;
    }
}
