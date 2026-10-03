<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    protected const API = '/api/v1';

    /**
     * Trava de segurança: os testes usam RefreshDatabase, que recria as tabelas.
     * Se as variáveis do ambiente (ex.: container Docker) apontarem para o MySQL de
     * desenvolvimento, aborta antes de apagar dados reais.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException("Testes abortados: banco '{$default}:{$database}' não é o SQLite em memória (verifique phpunit.xml).");
        }

        // Os canais "api" e "import" gravam em arquivo; nos testes eles são descartados
        // para não misturar requisições de teste com o log real da aplicação.
        foreach (['api', 'import'] as $channel) {
            $app['config']->set("logging.channels.{$channel}", ['driver' => 'monolog', 'handler' => NullHandler::class]);
        }

        return $app;
    }
}
