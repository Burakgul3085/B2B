<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Domain\Store\Reports\StoreReports;
use App\Models\Store\StoreCustomer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Üye müşteriler: sipariş sayısı, toplam harcama, ortalama sepet ve son sipariş. */
class CustomersReport extends StoreReport
{
    public function key(): string
    {
        return 'musteriler';
    }

    public function title(): string
    {
        return 'Müşteriler';
    }

    public function description(): string
    {
        return 'Üye müşterilerin sipariş sayısı, toplam harcaması, ortalama sepeti, son siparişi ve izinleri.';
    }

    public function group(): string
    {
        return 'Müşteri';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function filters(): array
    {
        return ReportOptions::dates('none', 'Üyelik') + [
            'buyers' => ['label' => 'Sipariş', 'type' => 'select', 'options' => ['' => 'Tümü', 'yes' => 'Sipariş verenler', 'no' => 'Hiç sipariş vermeyenler', 'repeat' => 'Birden çok sipariş verenler']],
            'consent' => ['label' => 'Ticari ileti izni', 'type' => 'select', 'options' => ['' => 'Tümü', 'yes' => 'İzin verenler', 'no' => 'İzin vermeyenler'], 'advanced' => true],
            'q' => ['label' => 'Ara (ad, e-posta, telefon)', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Müşteri', 'type' => 'text'],
            'email' => ['label' => 'E-posta', 'type' => 'text'],
            'phone' => ['label' => 'Telefon', 'type' => 'text', 'default' => false],
            'created_at' => ['label' => 'Üyelik tarihi', 'type' => 'datetime'],
            'orders' => ['label' => 'Sipariş', 'type' => 'int', 'total' => true],
            'spent' => ['label' => 'Toplam harcama', 'type' => 'money', 'total' => true],
            'average' => ['label' => 'Ortalama sepet', 'type' => 'money'],
            'last_order_at' => ['label' => 'Son sipariş', 'type' => 'datetime'],
            'consent_label' => ['label' => 'Ticari ileti izni', 'type' => 'text', 'default' => false],
            'last_login_at' => ['label' => 'Son giriş', 'type' => 'datetime', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['spent', 'desc'];
    }

    public function query(array $f): Builder
    {
        $statuses = "'".implode("','", StoreReports::SALE_STATUSES)."'";
        $stats = "(SELECT customer_id, COUNT(*) as orders, SUM(total - refunded_total) as spent, MAX(created_at) as last_order_at FROM store_orders WHERE customer_id IS NOT NULL AND status IN ({$statuses}) GROUP BY customer_id) as s";

        $query = StoreCustomer::query()->from('store_customers as cu')
            ->leftJoin(DB::raw($stats), 's.customer_id', '=', 'cu.id')
            ->whereNull('cu.closed_at');
        if ($bounds = ReportOptions::bounds($f)) {
            $query->whereBetween('cu.created_at', $bounds);
        }

        return $query
            ->when(($f['buyers'] ?? null) === 'yes', fn ($q) => $q->where('s.orders', '>', 0))
            ->when(($f['buyers'] ?? null) === 'no', fn ($q) => $q->whereNull('s.orders'))
            ->when(($f['buyers'] ?? null) === 'repeat', fn ($q) => $q->where('s.orders', '>', 1))
            ->when(($f['consent'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('cu.marketing_consent_at'))
            ->when(($f['consent'] ?? null) === 'no', fn ($q) => $q->whereNull('cu.marketing_consent_at'))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw("CONCAT(cu.first_name, ' ', cu.last_name) like ?", [$like])->orWhere('cu.email', 'like', $like)
                    ->orWhere('cu.phone', 'like', '%'.preg_replace('/\D/', '', $term).'%'));
            })
            ->select(['cu.email', 'cu.phone', 'cu.created_at', 'cu.last_login_at', 's.last_order_at'])
            ->selectRaw("CONCAT(cu.first_name, ' ', cu.last_name) as name, COALESCE(s.orders, 0) as orders, COALESCE(s.spent, 0) as spent")
            ->selectRaw('s.spent / NULLIF(s.orders, 0) as average')
            ->selectRaw("CASE WHEN cu.marketing_consent_at IS NULL THEN 'Hayır' ELSE 'Evet' END as consent_label");
    }
}
