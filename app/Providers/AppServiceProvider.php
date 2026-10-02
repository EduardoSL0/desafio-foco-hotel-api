<?php

namespace App\Providers;

use App\Services\Import\Importers\HotelXmlImporter;
use App\Services\Import\Importers\ReserveXmlImporter;
use App\Services\Import\Importers\RoomXmlImporter;
use App\Services\Import\XmlImportService;
use App\Services\Import\XmlLoader;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\Rules\CouponRule;
use App\Services\Pricing\Rules\PromotionRule;
use App\Services\Pricing\Rules\ServiceFeeRule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A ordem das regras define a ordem de aplicação: descontos antes das taxas.
        $this->app->bind(PriceCalculator::class, fn () => new PriceCalculator([
            new PromotionRule,
            new CouponRule,
            new ServiceFeeRule,
        ]));

        // A ordem dos importadores respeita as dependências entre entidades.
        $this->app->bind(XmlImportService::class, fn ($app) => new XmlImportService(
            $app->make(XmlLoader::class),
            [
                new HotelXmlImporter,
                new RoomXmlImporter,
                new ReserveXmlImporter,
            ],
        ));
    }

    public function boot(): void
    {
        // Em desenvolvimento, falha cedo ao tentar preencher atributos não permitidos (mass assignment).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->configureRateLimiting();
    }

    /**
     * Limitadores nomeados: cada grupo de rotas tem o PRÓPRIO contador.
     *
     * Com "throttle:10,1" o Laravel usa um único contador por IP para todas as rotas,
     * e um hóspede que navegasse pelo site (busca, cotação) acabaria bloqueado no
     * login ou na consulta da reserva. Aqui cada limitador é contado separadamente.
     */
    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user('sanctum')?->getAuthIdentifier() ?? $request->ip();

        // Contra força bruta de senha: por IP e por e-mail tentado.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.mb_strtolower((string) $request->input('email'))),
        ]);

        // Contra adivinhação de localizadores.
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(30)->by($byUserOrIp($request)));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('staff', fn (Request $request) => Limit::perMinute(240)->by($byUserOrIp($request)));
    }
}
