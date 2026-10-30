<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * Aşama 9 — rota taraması. Kimlik içeren her rota otomatik olarak denenir; yeni eklenen rotalar da
 * kendiliğinden taramaya girer. Beklenen: başka işletmenin veya başka bayinin kaydı 404, veri değişmez.
 */
class RouteAccessSweepTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    /** Kimlik olmayan parametreler (rapor anahtarı, biçim, belirteç, işletme adresi). */
    private const NON_MODEL = ['key', 'format', 'token', 'slug'];

    /** @return Route[] */
    private function routes(string $prefix): array
    {
        return array_values(array_filter(Router::getRoutes()->getRoutes(), fn (Route $r) => str_starts_with((string) $r->getName(), $prefix)));
    }

    private function method(Route $route): string
    {
        return collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
    }

    /** @param callable(string $param, int $index, string $routeName): ?int $resolve */
    private function url(Route $route, callable $resolve): ?string
    {
        $params = [];
        foreach ($route->parameterNames() as $i => $name) {
            if (in_array($name, self::NON_MODEL, true)) {
                return null;
            }
            $id = $resolve($name, $i, $route->getName());
            if ($id === null) {
                $this->fail("Tarama {$route->getName()} rotasının '{$name}' parametresi için kayıt bulamadı; BuildsWorld'e ekleyin.");
            }
            $params[$name] = $id;
        }

        return $params ? route($route->getName(), $params) : null;
    }

    private function sweep(string $prefix, callable $resolve, array $allowed = [404], array $skip = []): array
    {
        $problems = [];
        $tried = 0;
        foreach ($this->routes($prefix) as $route) {
            if (in_array($route->getName(), $skip, true) || ! ($url = $this->url($route, $resolve))) {
                continue;
            }
            $status = $this->call($this->method($route), $url, ['note' => 'x', 'reason' => 'x', 'body' => 'x', 'name' => 'x'])->getStatusCode();
            $tried++;
            if (! in_array($status, $allowed, true)) {
                $problems[] = "{$this->method($route)} {$route->getName()} → {$status}";
            }
        }
        $this->assertGreaterThan(5, $tried, 'Taranan rota sayısı beklenenden az.');

        return $problems;
    }

    public function test_business_cannot_reach_another_business_records_on_any_route(): void
    {
        $attacker = $this->makeBusiness();
        $victim = $this->makeBusiness();
        $mine = $this->buildWorld($attacker)['ids'];
        $theirs = $this->buildWorld($victim)['ids'];
        $before = $this->businessFingerprint($victim->id);

        $this->actingAs($attacker->user, 'business');
        // Tüm parametreler yabancı; ardından üst kayıt kendi, alt kayıt yabancı (iç içe rotalar).
        $problems = $this->sweep('business.', fn ($p, $i, $name) => $this->routeParam($theirs, $name, $p));
        $problems = [...$problems, ...$this->sweepNested('business.', $mine, $theirs)];

        $this->assertSame([], $problems, "Yabancı kayda 404 dışında yanıt veren rotalar:\n".implode("\n", $problems));
        $this->assertSame($before, $this->businessFingerprint($victim->id), 'Başka işletmenin verisi değişti.');

        // Kontrol: aynı sayfalar kendi kayıtlarıyla açılıyor (404'ler kapsamdan geliyor, rota hatasından değil).
        $opened = 0;
        foreach ($this->routes('business.') as $route) {
            if ($this->method($route) !== 'GET' || preg_match('/(attachment|images\.show|notifications\.open|\.media|\.logo)$/', $route->getName())
                || ! ($url = $this->url($route, fn ($p, $i, $name) => $this->routeParam($mine, $name, $p)))) {
                continue;
            }
            $this->get($url)->assertOk();
            $opened++;
        }
        $this->assertGreaterThanOrEqual(10, $opened);
    }

    public function test_nested_routes_reject_child_of_another_parent_in_same_business(): void
    {
        $business = $this->makeBusiness();
        $first = $this->buildWorld($business)['ids'];
        $second = $this->buildWorld($business)['ids'];

        $this->actingAs($business->user, 'business');
        $problems = $this->sweepNested('business.', $first, $second);

        $this->assertSame([], $problems, "Başka üst kayda ait alt kayıt kabul edildi:\n".implode("\n", $problems));
    }

    public function test_dealer_cannot_reach_other_dealers_or_other_businesses_records(): void
    {
        $business = $this->makeBusiness();
        $mine = $this->buildWorld($business);
        $sibling = $this->buildWorld($business)['ids'];
        $foreign = $this->buildWorld($this->makeBusiness())['ids'];
        $before = $this->businessFingerprint($business->id);

        $this->actingAs($mine['rel']->dealer->user, 'dealer');
        // Ürün ve görsel aynı toptancının ortak kataloğudur; onlar için yabancı işletmenin kaydı kullanılır.
        $shared = ['product', 'image'];
        $problems = [];
        $tried = 0;
        foreach ($this->routes('dealer.') as $route) {
            $url = $this->url($route, fn ($p) => in_array($p, $shared, true) ? $foreign[$p] : $this->routeParam($sibling, $route->getName(), $p));
            if (! $url) {
                continue;
            }
            $status = $this->call($this->method($route), $url, ['quantity' => '1', 'reason' => 'x', 'body' => 'x'])->getStatusCode();
            $tried++;
            if ($status !== 404) {
                $problems[] = "{$this->method($route)} {$route->getName()} → {$status}";
            }
        }

        $this->assertGreaterThanOrEqual(12, $tried);
        $this->assertSame([], $problems, "Bayi başka kayda 404 dışında yanıt aldı:\n".implode("\n", $problems));
        $after = $this->businessFingerprint($business->id);
        // Yalnızca istek sırasında güncellenen oturum/okuma kayıtları hariç hiçbir şey değişmemeli.
        $this->assertSame(array_diff_key($before, array_flip(['conversation_reads'])), array_diff_key($after, array_flip(['conversation_reads'])));
    }

    public function test_every_protected_route_requires_its_own_area_login(): void
    {
        $business = $this->makeBusiness();
        $world = $this->buildWorld($business);
        $ids = $world['ids'] + ['business' => $business->id];
        $public = ['login', 'login.store', 'login.code', 'login.code.verify', 'login.code.resend', 'password.request', 'password.email', 'password.reset', 'password.update',
            'payment.callback']; // iyzico geri dönüşü: oturumsuz gelir, sonucu iyzico'dan sorgular (DealerPaymentTest)

        $cases = [
            'platform.' => ['/platform/login', null],
            'business.' => ['/toptanci/login', [$world['rel']->dealer->user, 'dealer']],
            'dealer.' => ['/bayi/login', [$business->user, 'business']],
        ];
        $problems = [];
        $tried = 0;
        foreach ($cases as $prefix => [$login, $otherAreaUser]) {
            foreach ([null, $otherAreaUser] as $as) {
                $this->app['auth']->forgetGuards();
                $this->flushSession();
                if ($as) {
                    $this->actingAs(...$as);
                }
                foreach ($this->routes($prefix) as $route) {
                    if (in_array(substr($route->getName(), strlen($prefix)), $public, true)) {
                        continue;
                    }
                    $params = [];
                    foreach ($route->parameterNames() as $p) {
                        $params[$p] = match ($p) {
                            'key' => 'orders', 'format' => 'xlsx',
                            default => $this->routeParam($ids, $route->getName(), $p) ?? 1,
                        };
                    }
                    $response = $this->call($this->method($route), route($route->getName(), $params));
                    $tried++;
                    if (! $response->isRedirect() || ! str_ends_with(parse_url($response->headers->get('Location'), PHP_URL_PATH), $login)) {
                        $problems[] = ($as ? 'başka alan oturumu' : 'oturumsuz')." {$this->method($route)} {$route->getName()} → {$response->getStatusCode()}";
                    }
                }
            }
        }

        $this->assertGreaterThan(300, $tried); // ~190 korumalı rota × (oturumsuz + başka alan)
        $this->assertSame([], $problems, "Oturum istemeyen korumalı rotalar:\n".implode("\n", $problems));
    }

    /** İki parametreli rotalarda: üst kayıt $parents'tan, alt kayıt $children'dan. */
    private function sweepNested(string $prefix, array $parents, array $children): array
    {
        $problems = [];
        $tried = 0;
        foreach ($this->routes($prefix) as $route) {
            $names = $route->parameterNames();
            if (count($names) < 2 || array_intersect($names, self::NON_MODEL)) {
                continue;
            }
            $url = $this->url($route, fn ($p, $i) => $this->routeParam($i === 0 ? $parents : $children, $route->getName(), $p));
            $status = $this->call($this->method($route), $url, ['note' => 'x', 'price' => '1', 'counted_qty' => '1'])->getStatusCode();
            $tried++;
            if ($status !== 404) {
                $problems[] = "iç içe {$this->method($route)} {$route->getName()} → {$status}";
            }
        }
        $this->assertGreaterThanOrEqual(8, $tried);

        return $problems;
    }
}
