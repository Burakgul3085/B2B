<?php

namespace App\Providers;

use App\Domain\Mail\GmailTransport;
use App\Domain\Mail\MailTransport;
use App\Domain\Tenancy\TenantContext;
use App\Models\DealerApplication;
use App\Models\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Polimorfik ilişkilerde sınıf adı yerine sabit takma ad saklanır.
        Relation::enforceMorphMap([
            'dealer_application' => DealerApplication::class,
            'message' => Message::class,
        ]);
    }
}
