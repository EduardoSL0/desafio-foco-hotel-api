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
use Illuminate\Database\Eloquent\Model;
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
    }
}
