<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Önek verilmeyen "throttle:x,y" aynı kullanıcı / IP için tüm rotalarda tek sayaç kullanır: sipariş sayfasını gezmek
 * iade gönderimini, giriş denemeleri başvuru formunu kilitler. Her sınırlı rota kendi önekini (rota adı) taşımalıdır.
 */
class ThrottleKeysTest extends TestCase
{
    public function test_every_throttled_route_has_its_own_key(): void
    {
        $shared = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'throttle:') && count(explode(',', substr($middleware, 9))) < 3) {
                    $shared[] = $route->getName() ?? $route->uri();
                }
            }
        }

        $this->assertSame([], $shared, 'Önek verilmemiş throttle: '.implode(', ', $shared));
    }
}
