<?php

use App\Domain\Auth\Area;
use App\Domain\Platform\ErrorRecorder;
use App\Domain\Tenancy\MissingTenantContext;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureStoreOpen;
use App\Http\Middleware\EnsureStoreSuperAdmin;
use App\Http\Middleware\PlatformIpAllowlist;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Http\Middleware\RequireStoreTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetBusinessContext;
use App\Http\Middleware\SetDealerContext;
use App\Http\Middleware\StoreBotGuard;
use App\Http\Middleware\UseAreaSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Oturum başlamadan önce alanın çerezini seç.
        $middleware->web(prepend: [UseAreaSession::class]);
        $middleware->append(SecurityHeaders::class);

        // iyzico ödeme geri dönüşü başka siteden POST edilir; sonuç sunucudan iyzico'ya sorularak doğrulanır.
        $middleware->validateCsrfTokens(except: ['odeme/iyzico/donus', 'odeme/iyzico/bildirim']);
        // Çerez tercihi tarayıcıda (JS) yazılır; şifrelenmez.
        $middleware->encryptCookies(except: ['magaza_cerez', 'tema']);

        // İşletme bağlamı, rota model bağlamasından ÖNCE kurulur: başka işletmenin kaydı
        // global scope nedeniyle bulunamaz ve 404 döner.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetBusinessContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetDealerContext::class);

        $middleware->alias([
            'business.context' => SetBusinessContext::class,
            'dealer.context' => SetDealerContext::class,
            'platform.2fa' => RequirePlatformTwoFactor::class,
            'platform.ip' => PlatformIpAllowlist::class,
            'module' => EnsureModuleEnabled::class,
            'store.2fa' => RequireStoreTwoFactor::class,
            'store.super' => EnsureStoreSuperAdmin::class,
            'store.open' => EnsureStoreOpen::class,
            'store.bot' => StoreBotGuard::class,
        ]);

        // Oturumsuz kullanıcı, girmeye çalıştığı alanın kendi giriş sayfasına gider.
        $middleware->redirectGuestsTo(function (Request $request) {
            $area = Area::fromRequest($request) ?? Area::Dealer;

            return route($area->loginRoute());
        });

        // Oturumu olan kullanıcı giriş sayfasını açarsa kendi alanının ana sayfasına gider.
        $middleware->redirectUsersTo(function (Request $request) {
            $area = Area::fromRequest($request) ?? Area::Dealer;

            return route($area->homeRoute());
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // İşletme bağlamı eksikliği bir programlama hatasıdır; kullanıcıya ayrıntı gösterilmez.
        $exceptions->report(function (MissingTenantContext $e) {
            Log::critical($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        });

        // Raporlanan her hata (doğrulama, 404, yetki gibi beklenenler hariç) Platform → Hata Kayıtları'na toplanır.
        $exceptions->report(function (Throwable $e) {
            app(ErrorRecorder::class)->record($e);
        });
    })->create();
