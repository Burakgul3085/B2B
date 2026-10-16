<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * Aşama 9 — negatif girdi taraması. Her yazma rotasına, kodda doğrulanan bütün alan adlarıyla bozuk
 * değerler (iç içe dizi, çok uzun metin, aşırı/negatif sayı, boş, kontrol karakteri) gönderilir.
 * Beklenen: hiçbir istek 500 vermez; hatalar doğrulama mesajı veya 4xx olarak döner.
 */
class InputFuzzTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Yüzlerce istek aynı PHP sürecinde çalışır ve test altyapısı her istekte bellek biriktirir
        // (üretimde her istek ayrı süreçtir). Yalnızca bu tarama için sınır yükseltilir.
        ini_set('memory_limit', '1024M');
    }

    /** Uygulama kodunda doğrulanan alan adları (kaynak koddan çıkarılır; yeni alanlar kendiliğinden eklenir). */
    private function fieldNames(): array
    {
        $names = [];
        foreach ([...File::allFiles(app_path('Http/Controllers')), ...File::allFiles(app_path('Domain'))] as $file) {
            preg_match_all("/'([a-z_]+)(?:\\.\\*)?(?:\\.[a-z_]+)?' => (?:\\[|'(?:required|nullable|sometimes))/", $file->getContents(), $m);
            array_push($names, ...$m[1]);
        }

        return array_values(array_unique($names));
    }

    private function variants(): array
    {
        return [
            'iç içe dizi' => ['a' => ['b' => ['c']]],
            // Alan başına ~6 KB: sınırları aşar; toplam istek, sunucunun gövde sınırının (post_max_size) altında kalır.
            'çok uzun metin' => str_repeat('Ş', 3000),
            'aşırı sayı' => '-99999999999999999999999.999',
            'boş' => '',
            'kontrol karakteri' => "x\0\x1b[31m'\"; DROP TABLE orders; --",
        ];
    }

    /** @param callable(Route): ?string $url */
    private function fuzz(array $routes, callable $url): array
    {
        $fields = $this->fieldNames();
        $problems = [];
        foreach ($routes as $route) {
            $target = $url($route);
            if ($target === null) {
                continue;
            }
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            foreach ($this->variants() as $label => $value) {
                $response = $this->call($method, $target, array_fill_keys($fields, $value));
                if ($response->getStatusCode() >= 500) {
                    $problems[] = "{$method} {$route->getName()} [{$label}] → {$response->getStatusCode()}: ".($response->exception?->getMessage() ?? '');
                }
            }
        }

        return $problems;
    }

    /** @return Route[] */
    private function writeRoutes(string $prefix): array
    {
        return array_values(array_filter(Router::getRoutes()->getRoutes(), fn (Route $r) => str_starts_with((string) $r->getName(), $prefix)
            && ! str_ends_with($r->getName(), 'logout')
            && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])));
    }

    private function resolver(array $ids): callable
    {
        return function (Route $route) use ($ids) {
            $params = [];
            foreach ($route->parameterNames() as $p) {
                $params[$p] = match ($p) {
                    'token' => str_repeat('a', 64),
                    'slug' => 'yok',
                    default => $this->routeParam($ids, $route->getName(), $p) ?? $ids[$p] ?? null,
                };
                if ($params[$p] === null) {
                    return null;
                }
            }

            return route($route->getName(), $params);
        };
    }

    public function test_business_write_routes_never_fail_with_server_error(): void
    {
        $business = $this->makeBusiness();
        $ids = $this->buildWorld($business)['ids'];

        $this->actingAs($business->user, 'business');
        $problems = $this->fuzz($this->writeRoutes('business.'), $this->resolver($ids));

        $this->assertSame([], $problems, "Bozuk girdide sunucu hatası:\n".implode("\n", $problems));
    }

    public function test_dealer_and_platform_write_routes_never_fail_with_server_error(): void
    {
        $business = $this->makeBusiness();
        $world = $this->buildWorld($business);
        $ids = $world['ids'] + ['business' => $business->id];

        $this->actingAs($world['rel']->dealer->user, 'dealer');
        $problems = $this->fuzz($this->writeRoutes('dealer.'), $this->resolver($ids));

        $this->actingAsPlatformAdmin();
        $problems = [...$problems, ...$this->fuzz($this->writeRoutes('platform.'), $this->resolver($ids))];

        $this->assertSame([], $problems, "Bozuk girdide sunucu hatası:\n".implode("\n", $problems));
    }

    public function test_list_pages_survive_malformed_query_strings(): void
    {
        $business = $this->makeBusiness();
        $world = $this->buildWorld($business);
        $query = ['q' => ['x'], 'status' => ['a' => 'b'], 'sort' => ['x'], 'dir' => ['x'], 'page' => ['x'], 'from' => ['x'], 'to' => 'yok',
            'period' => ['x'], 'dealer' => ['x'], 'category' => ['x'], 'level' => ['x'], 'warehouse' => ['x'], 'cols' => 'x'];

        $problems = [];
        foreach ([[$business->user, 'business', 'business.'], [$world['rel']->dealer->user, 'dealer', 'dealer.']] as [$user, $guard, $prefix]) {
            $this->actingAs($user, $guard);
            foreach (Router::getRoutes()->getRoutes() as $route) {
                if (! str_starts_with((string) $route->getName(), $prefix) || ! in_array('GET', $route->methods(), true)
                    || array_diff($route->parameterNames(), ['key'])) {
                    continue;
                }
                $params = $route->parameterNames() ? ['key' => $guard === 'dealer' ? 'my-orders' : 'orders'] : [];
                $response = $this->get(route($route->getName(), $params + $query));
                if ($response->getStatusCode() >= 500) {
                    $problems[] = "GET {$route->getName()} → {$response->getStatusCode()}: ".($response->exception?->getMessage() ?? '');
                }
            }
        }

        $this->assertSame([], $problems, "Bozuk sorgu dizgesinde sunucu hatası:\n".implode("\n", $problems));
    }

    public function test_public_and_auth_forms_never_fail_with_server_error(): void
    {
        $business = $this->makeBusiness();
        $ids = ['slug' => $business->slug];

        $routes = array_filter($this->writeRoutes(''), fn (Route $r) => preg_match('/(^public\.|\.login\.store$|\.password\.|\.activation\.store$)/', $r->getName()));
        $problems = $this->fuzz($routes, function (Route $route) use ($business) {
            $params = [];
            foreach ($route->parameterNames() as $p) {
                $params[$p] = $p === 'slug' ? $business->slug : 'x';
            }

            return route($route->getName(), $params);
        });

        $this->assertSame([], $problems, "Bozuk girdide sunucu hatası:\n".implode("\n", $problems));
    }
}
