<?php

namespace App\Domain\Mail;

use App\Domain\Branding\PuzzleLogo;

/** E-postaya gömülen (cid) yapboz logosu; mağaza e-postaları da B2B e-postalarıyla aynı logoyu kullanır. */
final class EmailLogo
{
    /** Gösterim yüksekliğinin 2 katı çözünürlükte, saydam PNG verisi. */
    public static function puzzle(int $height): string
    {
        $image = PuzzleLogo::icon($height * 2);
        imagesavealpha($image, true);

        return PuzzleLogo::png($image);
    }
}
