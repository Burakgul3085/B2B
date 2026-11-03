<?php

namespace App\Jobs;

use App\Domain\Files\ImageVariants;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Yüklenen ürün görselinin küçük boyutlarını (320/640/1000) kuyrukta hazırlar (mağaza ve B2B ortak); böylece ürünü
 * ilk açan ziyaretçi görsel küçültülmesini beklemez. Görsel bu arada silinmişse iş sessizce biter.
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(public string $path) {}

    public function handle(ImageVariants $variants): void
    {
        $variants->prepare($this->path);
    }
}
