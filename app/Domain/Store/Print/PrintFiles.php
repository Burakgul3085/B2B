<?php

namespace App\Domain\Store\Print;

use App\Jobs\ProcessPrintFile;
use App\Models\Store\StoreCart;
use App\Models\Store\StorePrintFile;
use App\Models\Store\StorePrintJob;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fotokopi belgeleri: parçalı yükleme (tarayıcı 8 MB'lık parçalar gönderir; kopan yükleme kaldığı yerden sürer),
 * içerikten tür denetimi, arka planda PDF'e çevirme, sayfa sayımı ve önizleme, saklama süresi dolunca silme.
 * Dosyalar local diskte store/print/{token} altındadır; herkese açık adresi yoktur.
 */
class PrintFiles
{
    /** uzantı => kabul edilen içerik türleri (finfo) */
    public const TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => self::OLE, 'xls' => self::OLE, 'ppt' => self::OLE,
        'docx' => [...self::ZIP, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => [...self::ZIP, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => [...self::ZIP, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'odt' => [...self::ZIP, 'application/vnd.oasis.opendocument.text'],
        'ods' => [...self::ZIP, 'application/vnd.oasis.opendocument.spreadsheet'],
        'odp' => [...self::ZIP, 'application/vnd.oasis.opendocument.presentation'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    ];

    private const OLE = ['application/msword', 'application/vnd.ms-office', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
        'application/CDFV2', 'application/x-ole-storage', 'application/encrypted'];

    private const ZIP = ['application/zip', 'application/x-zip-compressed'];

    /** Bir sepette aynı anda en fazla bu kadar belge */
    public const MAX_FILES = 20;

    public function __construct(private PrintSettings $settings, private DocumentTools $tools) {}

    /** @throws DomainException */
    public function start(StoreCart $cart, string $name, int $size): StorePrintFile
    {
        if (! $this->settings->enabled()) {
            throw new DomainException('Fotokopi siparişi şu anda alınmıyor.');
        }
        $name = mb_substr(trim(str_replace(['/', '\\'], '-', $name)), 0, 200) ?: 'belge';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (! isset(self::TYPES[$ext])) {
            throw new DomainException('Bu dosya türü yüklenemez. PDF, Word, PowerPoint, Excel, JPG veya PNG yükleyin.');
        }
        if ($size < 1 || $size > $this->settings->maxBytes()) {
            throw new DomainException('Dosya en fazla '.$this->settings->get('max_mb').' MB olabilir.');
        }
        if (StorePrintFile::where('cart_id', $cart->id)->whereIn('status', ['uploading', 'processing', 'ready'])->count() >= self::MAX_FILES) {
            throw new DomainException('Aynı anda en fazla '.self::MAX_FILES.' belge yükleyebilirsiniz.');
        }

        $token = Str::random(32);

        return StorePrintFile::create([
            'token' => $token, 'cart_id' => $cart->id, 'original_name' => $name, 'extension' => $ext, 'size' => $size,
            'path' => "store/print/{$token}/original.{$ext}", 'status' => 'uploading',
            'expires_at' => now()->addDays((int) $this->settings->get('unordered_days')),
        ]);
    }

    /**
     * Bir parça ekler. Parça, sunucunun aldığı bayt sayısından başlamalıdır (yeniden denemede aynı parça iki kez eklenmez).
     *
     * @throws DomainException
     */
    public function appendChunk(StorePrintFile $file, int $offset, UploadedFile $chunk): StorePrintFile
    {
        if ($file->status !== 'uploading') {
            throw new DomainException('Bu dosyanın yüklemesi tamamlandı.');
        }
        if ($offset !== $file->received) {
            throw new DomainException('offset:'.$file->received); // tarayıcı bu konumdan devam eder
        }
        $bytes = (int) $chunk->getSize();
        if ($bytes < 1 || $file->received + $bytes > $file->size) {
            throw new DomainException('Dosya parçası beklenenden büyük.');
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($file->path));
        $out = fopen($disk->path($file->path), $offset === 0 ? 'wb' : 'ab');
        $in = fopen($chunk->getRealPath(), 'rb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $file->update(['received' => $file->received + $bytes]);

        if ($file->received === $file->size) {
            $this->finish($file);
        }

        return $file->fresh();
    }

    /** Yükleme bitti: içerik türü denetlenir, belge arka planda hazırlanır. */
    private function finish(StorePrintFile $file): void
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file(Storage::disk('local')->path($file->path));
        if (! in_array($mime, self::TYPES[$file->extension], true)) {
            $this->discard($file, 'Dosyanın içeriği uzantısıyla uyuşmuyor ('.$file->extension.'). Belgeyi yeniden kaydedip yükleyin.');

            return;
        }
        $file->update(['mime' => $mime, 'status' => 'processing']);
        ProcessPrintFile::dispatch($file->id);
    }

    /** Arka plan işi: sayfa sayısı, gerekirse PDF'e çevirme ve ilk sayfa önizlemesi. */
    public function process(StorePrintFile $file): void
    {
        if ($file->status !== 'processing') {
            return;
        }
        $disk = Storage::disk('local');
        $dir = dirname($file->path);
        try {
            if ($file->isImage()) {
                $file->update(['pages' => 1, 'preview_path' => $file->path, 'status' => 'ready']);

                return;
            }
            $pdf = $file->path;
            if ($file->extension !== 'pdf') {
                $converted = $this->tools->toPdf($disk->path($file->path), $disk->path($dir));
                $pdf = $dir.'/belge.pdf';
                rename($converted, $disk->path($pdf));
            }
            $pages = $this->tools->pageCount($disk->path($pdf));
            $preview = $this->tools->preview($disk->path($pdf), $disk->path($dir.'/onizleme'));
            $file->update([
                'pdf_path' => $file->extension !== 'pdf' ? $pdf : null, 'pages' => $pages,
                'preview_path' => $preview ? $dir.'/onizleme.png' : null, 'status' => 'ready', 'error' => null,
            ]);
        } catch (Throwable $e) {
            report($e);
            $file->update(['status' => 'failed', 'error' => $e instanceof \RuntimeException ? mb_substr($e->getMessage(), 0, 300) : 'Belge okunamadı; dosya bozuk olabilir. PDF olarak kaydedip yeniden yükleyin.']);
        }
    }

    /** Müşteri belgeyi kaldırdı (sepete eklenmiş işi yoksa kayıt da silinir). */
    public function delete(StorePrintFile $file): void
    {
        Storage::disk('local')->deleteDirectory(dirname($file->path));
        if (StorePrintJob::where('file_id', $file->id)->exists()) {
            $file->update(['status' => 'deleted']);
        } else {
            $file->delete();
        }
    }

    /** Siparişe girdi: dosya sipariş saklama süresi boyunca tutulur. */
    public function keepForOrder(StorePrintFile $file): void
    {
        $file->update(['expires_at' => now()->addDays((int) $this->settings->get('ordered_days'))]);
    }

    /** Süresi dolan belgeleri siler (zamanlanmış görev, günlük). @return int silinen */
    public function prune(): int
    {
        $count = 0;
        StorePrintFile::where('expires_at', '<', now())->where('status', '!=', 'deleted')->orderBy('id')->each(function (StorePrintFile $file) use (&$count) {
            $this->delete($file);
            $count++;
        });

        return $count;
    }

    private function discard(StorePrintFile $file, string $error): void
    {
        Storage::disk('local')->deleteDirectory(dirname($file->path));
        $file->update(['status' => 'failed', 'error' => $error]);
    }
}
