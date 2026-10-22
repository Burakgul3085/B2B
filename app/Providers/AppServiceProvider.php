<?php

namespace App\Providers;

use App\Domain\Mail\GmailTransport;
use App\Domain\Mail\MailTransport;
use App\Domain\Tenancy\TenantContext;
use App\Models\Announcement;
use App\Models\DealerApplication;
use App\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->bind(MailTransport::class, GmailTransport::class);
    }

    public function boot(): void
    {
        // Tanımsız alanlara yazma ve tembel yükleme (N+1) geliştirmede hata olarak yakalanır.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Doğrulamadan önce normalleştirilen alanlar için güvenli okuma: dizi veya nesne gelirse varsayılan döner
        // (ör. code[]=x gönderilmesi "Array to string" hatasıyla 500 vermez, doğrulama hatasına dönüşür).
        Request::macro('scalar', function (string $key, string $default = ''): string {
            $value = $this->input($key);

            return is_scalar($value) ? (string) $value : $default;
        });

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Alpine ifadelerine sunucu verisi: @alpine($dizi) JSON'u HTML varlıklarıyla kaçırılmış nesne/dizi sabiti
        // olarak basar. @js() dizileri JSON.parse(...) ile sardığı için Alpine'in CSP sürümünde çalışmaz.
        Blade::directive('alpine', fn (string $expression) => "<?php echo e(json_encode({$expression}, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); ?>");

        // Polimorfik ilişkilerde sınıf adı yerine sabit takma ad saklanır.
        Relation::enforceMorphMap([
            'announcement' => Announcement::class,
            'dealer_application' => DealerApplication::class,
            'message' => Message::class,
        ]);
    }
}
