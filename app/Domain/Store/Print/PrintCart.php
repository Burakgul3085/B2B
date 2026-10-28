<?php

namespace App\Domain\Store\Print;

use App\Domain\Store\Cart\PrintLine;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StorePrintFile;
use App\Models\Store\StorePrintJob;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Baskı işinin fiyatı ve sepete eklenmesi. Fiyat her zaman sunucuda, belgenin sunucuda sayılan sayfa sayısıyla
 * hesaplanır; sepette her gösterimde güncel ayarlarla yenilenir.
 */
class PrintCart
{
    public function __construct(private PrintSettings $settings, private PrintPricing $pricing) {}

    /**
     * @return array{options: PrintOptions, quote: array}
     *
     * @throws ValidationException|DomainException
     */
    public function quote(StorePrintFile $file, array $input): array
    {
        if (! $this->settings->enabled()) {
            throw new DomainException('Fotokopi siparişi şu anda alınmıyor.');
        }
        if (! $file->isReady()) {
            throw new DomainException('Belge henüz hazır değil.');
        }
        $options = PrintOptions::fromInput($input, $file->pages, $this->settings);

        return ['options' => $options, 'quote' => $this->pricing->quote($file->pages, $options)];
    }

    /** @throws ValidationException|DomainException */
    public function add(StoreCart $cart, StorePrintFile $file, array $input): StorePrintJob
    {
        if ($file->cart_id !== $cart->id) {
            throw new DomainException('Belge bulunamadı.');
        }
        ['options' => $options, 'quote' => $quote] = $this->quote($file, $input);

        return DB::transaction(function () use ($cart, $file, $options, $quote) {
            $job = StorePrintJob::create([
                'file_id' => $file->id, 'cart_id' => $cart->id, 'options' => $options->toArray(),
                'pages' => $quote['pages'], 'sides' => $quote['sides'], 'sheets' => $quote['sheets'], 'copies' => $quote['copies'],
                'price' => $quote['total'], 'breakdown' => $quote,
            ]);
            StoreCartItem::create(['cart_id' => $cart->id, 'variant_id' => null, 'print_job_id' => $job->id, 'quantity' => 1]);

            return $job;
        });
    }

    /**
     * Sepetteki baskı satırları, güncel fiyatla.
     *
     * @param  Collection<int, StoreCartItem>  $items
     * @return list<PrintLine>
     */
    public function lines(Collection $items): array
    {
        $enabled = $this->settings->enabled();
        $lines = [];
        foreach ($items as $item) {
            $job = $item->printJob;
            $issue = (! $enabled || ! $job->file->isReady()) ? 'unavailable' : null;
            if (! $issue) {
                try {
                    $quote = $this->pricing->quote($job->file->pages, $job->printOptions());
                    if (bccomp($quote['total'], (string) $job->price, 2) !== 0) {
                        $job->update(['price' => $quote['total'], 'breakdown' => $quote, 'sides' => $quote['sides'], 'sheets' => $quote['sheets']]);
                    }
                } catch (DomainException) {
                    $issue = 'unavailable'; // ciltleme sınırı değişti
                }
            }
            $lines[] = new PrintLine($item, $job, $issue);
        }

        return $lines;
    }
}
