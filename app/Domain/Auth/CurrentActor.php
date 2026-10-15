<?php

namespace App\Domain\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** İşlemi yapan kullanıcı (alan + ID). Konsol ve kuyrukta boş döner. */
class CurrentActor
{
    public function __construct(private Request $request) {}

    /** @return array{type: ?string, id: ?int} */
    public function get(): array
    {
        $area = Area::fromRequest($this->request);

        if ($area && Auth::guard($area->guard())->check()) {
            return ['type' => $area->value, 'id' => (int) Auth::guard($area->guard())->id()];
        }

        return ['type' => null, 'id' => null];
    }
}
