<?php

namespace App\Domain\Store\Print;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Sunucudaki belge araçları: LibreOffice (Word, PowerPoint, Excel → PDF), poppler (pdfinfo: sayfa sayısı,
 * pdftoppm: ilk sayfa önizlemesi). Komut yolları config/store.php → print. pdfinfo yoksa sayfa sayısı PDF'in
 * içinden okunur (yedek yol). Testlerde bu sınıf sahtesiyle değiştirilir.
 */
class DocumentTools
{
    /** @throws RuntimeException */
    public function toPdf(string $source, string $outDir): string
    {
        $profile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lo-'.bin2hex(random_bytes(4));
        $process = new Process([config('store.print.soffice'), '-env:UserInstallation=file:///'.ltrim(str_replace('\\', '/', $profile), '/'),
            '--headless', '--norestore', '--convert-to', 'pdf', '--outdir', $outDir, $source]);
        $process->setTimeout((int) config('store.print.convert_timeout'));
        try {
            $process->run();
        } finally {
            File::deleteDirectory($profile);
        }

        $pdf = $outDir.DIRECTORY_SEPARATOR.pathinfo($source, PATHINFO_FILENAME).'.pdf';
        if (! $process->isSuccessful() || ! is_file($pdf)) {
            throw new RuntimeException('Belge PDF\'e çevrilemedi.');
        }

        return $pdf;
    }

    /** @throws RuntimeException şifreli veya bozuk PDF */
    public function pageCount(string $pdf): int
    {
        $process = new Process([config('store.print.pdfinfo'), $pdf]);
        $process->setTimeout(60);
        try {
            $process->run();
            if ($process->isSuccessful() && preg_match('/^Pages:\s+(\d+)/m', $process->getOutput(), $m)) {
                return (int) $m[1];
            }
            if (preg_match('/Incorrect password|encrypted/i', $process->getErrorOutput())) {
                throw new RuntimeException('Belge şifreli; şifresiz bir kopya yükleyin.');
            }
        } catch (ExceptionInterface) {
            // pdfinfo yok: aşağıdaki yedek yol
        }

        return $this->pageCountFromContent($pdf);
    }

    /** pdfinfo olmadan: sayfa nesnelerini sayar (/Type /Page, /Pages hariç). */
    public function pageCountFromContent(string $pdf): int
    {
        $content = (string) file_get_contents($pdf);
        if (str_contains($content, '/Encrypt')) {
            throw new RuntimeException('Belge şifreli; şifresiz bir kopya yükleyin.');
        }
        $count = preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $content);
        if ($count < 1) {
            throw new RuntimeException('Belgenin sayfaları okunamadı; dosya bozuk olabilir.');
        }

        return $count;
    }

    /** İlk sayfanın küçük PNG önizlemesi; araç yoksa null. */
    public function preview(string $pdf, string $outBase): ?string
    {
        $process = new Process([config('store.print.pdftoppm'), '-png', '-r', '45', '-f', '1', '-l', '1', '-singlefile', $pdf, $outBase]);
        $process->setTimeout(60);
        try {
            $process->run();
        } catch (ExceptionInterface) {
            return null;
        }

        return $process->isSuccessful() && is_file($outBase.'.png') ? $outBase.'.png' : null;
    }
}
