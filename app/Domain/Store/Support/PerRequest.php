<?php

namespace App\Domain\Store\Support;

use Closure;

/**
 * İstek başına önbellek: aynı istekte tekrar eden sorgular bir kez çalışır. once()'tan farkı, kuyruk işçisi ve
 * testler gibi uzun ömürlü süreçlerde değerin bir sonraki isteğe taşınmamasıdır (istek nesnesine bağlıdır).
 */
final class PerRequest
{
    public static function remember(string $key, Closure $callback): mixed
    {
        $bag = request()->attributes;
        if (! $bag->has('per_request.'.$key)) {
            $bag->set('per_request.'.$key, $callback());
        }

        return $bag->get('per_request.'.$key);
    }
}
