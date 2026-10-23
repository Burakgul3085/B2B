<?php

namespace App\Domain\Store\Inventory;

use App\Domain\Mail\SystemMailer;
use App\Domain\Support\Money;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockAlert;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Stoğa gelince haber ver": tükenen seçenek için e-posta bırakılır; zamanlanmış görev (store:stock-alerts)
 * satılabilir stok oluşunca tek e-posta gönderir ve kaydı siler. Gönderilemeyen eski kayıtlar 180 günde silinir.
 */
class StockAlerts
{
    public const RETENTION_DAYS = 180;

    public function __construct(private StoreStockService $stock, private SystemMailer $mailer) {}

    /** @throws ValidationException */
    public function subscribe(array $input, ?StoreCustomer $customer): StoreProductVariant
    {
        $data = Validator::make($input + ['email' => $customer?->email], [
            'variant_id' => ['required', 'integer'],
            'email' => ['required', 'email:rfc', 'max:190'],
        ], [], ['email' => 'e-posta'])->validate();

        $variant = StoreProductVariant::where('id', $data['variant_id'])->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_published', true))->first();
        if (! $variant) {
            throw ValidationException::withMessages(['email' => 'Bu ürün artık satışta değil.']);
        }
        if (($this->stock->available([$variant->id])[$variant->id] ?? 0) > 0) {
            throw ValidationException::withMessages(['email' => 'Bu ürün şu an stokta; hemen sipariş verebilirsiniz.']);
        }

        StoreStockAlert::updateOrCreate(
            ['variant_id' => $variant->id, 'email' => mb_strtolower(trim($data['email']))],
            ['customer_id' => $customer?->id],
        );

        return $variant;
    }

    /** Stoğa giren seçeneklerin bekleyen bildirimlerini gönderir. Gönderilen sayısını döndürür. */
    public function sendDue(): int
    {
        StoreStockAlert::where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();

        $variantIds = StoreStockAlert::distinct()->pluck('variant_id')->all();
        if ($variantIds === []) {
            return 0;
        }
        $available = $this->stock->available($variantIds);
        $ready = array_keys(array_filter($available, fn ($qty) => $qty > 0));

        $sent = 0;
        $variants = StoreProductVariant::with('product:id,name,slug,is_published')->whereIn('id', $ready)->where('is_active', true)->get();
        foreach ($variants as $variant) {
            if (! $variant->product?->is_published) {
                continue;
            }
            $name = $variant->product->name.($variant->option_value ? " ({$variant->option_value})" : '');
            foreach (StoreStockAlert::where('variant_id', $variant->id)->get() as $alert) {
                $ok = $this->mailer->send($alert->email, "Stoğa geldi: {$name}", [
                    'brand' => config('store.name'),
                    'title' => 'Beklediğiniz ürün stokta',
                    'intro' => 'Haber vermemizi istediğiniz ürün yeniden stoğa girdi. Stoklar sınırlı olabilir; dilerseniz hemen sipariş verebilirsiniz.',
                    'rows' => [['Ürün', $name], ['Fiyat', Money::format($variant->price)]],
                    'action_url' => route('store.product', $variant->product->slug),
                    'action_label' => 'Ürüne git',
                    'outro' => 'Bu e-posta yalnızca isteğiniz üzerine bir kez gönderildi; adresiniz bu bildirimden sonra silindi.',
                ]);
                if ($ok) {
                    $alert->delete();
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
