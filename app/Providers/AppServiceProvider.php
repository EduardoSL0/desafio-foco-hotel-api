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
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        // Em produção, migrate:fresh, db:wipe e similares são bloqueados (mesmo com --force).
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // GET /up também confere o banco: com o MySQL fora do ar a verificação de saúde falha.
        Event::listen(DiagnosingHealth::class, fn () => DB::connection()->getPdo());

        $this->configureRateLimiting();

        // Parâmetros de rota numéricos: "1'" ou "1 OR 1=1" retornam 404 em vez de serem lidos como 1.
        Route::patterns(array_fill_keys(['hotel', 'room', 'reserve', 'user', 'promotion', 'coupon', 'payment'], '[0-9]+'));
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
        $limit = fn (string $name) => (int) config("hotel.rate_limits.{$name}");

        // Contra força bruta de senha: por IP e por e-mail tentado.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute($limit('login'))->by('ip:'.$request->ip()),
            Limit::perMinute($limit('login_per_email'))->by('email:'.(is_string($email = $request->input('email')) ? mb_strtolower($email) : '')),
        ]);

        // Contra adivinhação de localizadores.
        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute($limit('lookup'))->by($request->ip()));

        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute($limit('booking'))->by($byUserOrIp($request)));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute($limit('public'))->by($request->ip()));
        RateLimiter::for('staff', fn (Request $request) => Limit::perMinute($limit('staff'))->by($byUserOrIp($request)));
    }
}
