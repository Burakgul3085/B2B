<?php

namespace App\Domain\Platform;

use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Yedekleme (Platform → Yedekler; her gece zamanlanmış görevle).
 * Tek zip: veritabanı dökümü (mysqldump, gzip'li) + yüklenen dosyalar (storage/app/private, yedekler hariç).
 * Veritabanı parolası komut satırına değil ortam değişkenine (MYSQL_PWD) verilir. Son N yedek saklanır.
 * Geri yükleme elle yapılır (docs/24-b2b-eksikler-raporu.md §2); yayın öncesi prova Aşama 10'dadır.
 */
class BackupService
{
    public const DIR = 'backups';

    private const NAME = '/^yedek-\d{8}-\d{6}(-\d{1,3})?\.zip$/';

    /** @return string oluşan yedeğin adı @throws DomainException */
    public function create(): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIR);
        // Aynı saniyede ikinci yedek (ör. gece görevi + elle) eskisinin üzerine yazmasın; başarısızlıkta yalnızca bu dosya silinir.
        $stamp = 'yedek-'.now('Europe/Istanbul')->format('Ymd-His');
        $name = $stamp.'.zip';
        for ($i = 2; $disk->exists(self::DIR.'/'.$name); $i++) {
            $name = "{$stamp}-{$i}.zip";
        }
        $tmp = storage_path('app/yedek-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($tmp);

        try {
            $sql = $tmp.'/veritabani.sql';
            $this->dump($sql);
            $gz = $sql.'.gz';
            $this->gzip($sql, $gz);

            $zip = new ZipArchive;
            if ($zip->open($disk->path(self::DIR.'/'.$name), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new DomainException('Yedek dosyası oluşturulamadı.');
            }
            $zip->addFile($gz, 'veritabani.sql.gz');
            $root = rtrim(str_replace('\\', '/', $disk->path('')), '/');
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                $relative = ltrim(substr($path, strlen($root)), '/');
                if ($file->isFile() && ! str_starts_with($relative, self::DIR.'/')) {
                    $zip->addFile($path, 'dosyalar/'.$relative);
                }
            }
            $zip->setArchiveComment('Yapboz B2B yedeği · '.now('Europe/Istanbul')->format('d.m.Y H:i'));
            $zip->close();
        } catch (Throwable $e) {
            $disk->delete(self::DIR.'/'.$name);
            throw $e instanceof DomainException ? $e : new DomainException('Yedek alınamadı: '.$e->getMessage());
        } finally {
            File::deleteDirectory($tmp);
        }

        $this->prune();

        return $name;
    }

    /** @return list<array{name: string, size: int, created_at: Carbon}> en yeni önce */
    public function list(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->map(fn ($p) => basename($p))->filter(fn ($n) => preg_match(self::NAME, $n))
            ->map(fn ($n) => ['name' => $n, 'size' => $disk->size(self::DIR.'/'.$n), 'created_at' => Carbon::createFromTimestamp($disk->lastModified(self::DIR.'/'.$n))])
            // Ad sırası = zaman sırası; aynı saniyedeki ek (-2, -3) sayısal sıralanır.
            ->sortByDesc(fn ($b) => preg_replace_callback('/^(yedek-\d{8}-\d{6})(?:-(\d+))?\.zip$/', fn ($m) => $m[1].sprintf('%03d', $m[2] ?? 1), $b['name']))
            ->values()->all();
    }

    /** Yedek dosyasının yolu (yalnızca geçerli adlar; dizin dışına çıkılamaz). */
    public function path(string $name): string
    {
        abort_unless(preg_match(self::NAME, $name) && Storage::disk('local')->exists(self::DIR.'/'.$name), 404);

        return self::DIR.'/'.$name;
    }

    public function delete(string $name): void
    {
        Storage::disk('local')->delete($this->path($name));
    }

    /** @throws DomainException */
    private function dump(string $target): void
    {
        $c = config('database.connections.'.config('database.default'));
        $result = Process::timeout(1800)->env(['MYSQL_PWD' => (string) $c['password']])->run([
            config('b2b.backup.mysqldump'), '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
            '--default-character-set=utf8mb4', '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            '--result-file='.$target, $c['database'],
        ]);
        if (! $result->successful() || ! is_file($target) || filesize($target) === 0) {
            throw new DomainException('Veritabanı dökümü alınamadı: '.mb_substr(trim($result->errorOutput()) ?: 'mysqldump çalışmadı', 0, 300));
        }
    }

    private function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');
        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 512));
        }
        fclose($in);
        gzclose($out);
    }

    /** Son N yedek dışındakileri siler. */
    private function prune(): void
    {
        foreach (array_slice($this->list(), max(1, (int) config('b2b.backup.keep'))) as $old) {
            Storage::disk('local')->delete(self::DIR.'/'.$old['name']);
        }
    }
}
