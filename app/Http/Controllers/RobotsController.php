<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/** B2B alanları ve mağaza yönetimi arama motorlarına kapalıdır. (Mağazanın kendi robots.txt'si: Store\Front\SeoController.) */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
