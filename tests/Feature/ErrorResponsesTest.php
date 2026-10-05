<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Respostas de erro padronizadas em português, sem detalhes internos. */
class ErrorResponsesTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_in_portuguese(): void
    {
        $this->postJson(self::API.'/reserves', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.check_in.0', 'O campo check-in é obrigatório.')
            ->assertJsonPath('errors.room_id.0', 'O campo quarto é obrigatório.');
    }

    public function test_unauthenticated_message(): void
    {
        $this->getJson(self::API.'/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Não autenticado. Faça login em POST /api/v1/auth/login e envie o token no cabeçalho Authorization: Bearer <token>.');
    }

    public function test_forbidden_message(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($hotel)->create());

        $this->postJson(self::API.'/rooms', ['hotel_id' => $hotel->id, 'name' => 'X'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem permissão para esta ação.')
            ->assertJsonMissingPath('exception');
    }

    public function test_method_not_allowed_message(): void
    {
        $this->deleteJson(self::API.'/hotels')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Método HTTP não permitido para esta rota.');
    }

    public function test_too_many_requests_message(): void
    {
        $room = Room::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::API.'/reserves/lookup', ['code' => 'ZZZZZZZZ', 'last_name' => 'X']);
        }

        $response = $this->postJson(self::API.'/reserves/lookup', ['code' => 'ZZZZZZZZ', 'last_name' => 'X'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonMissingPath('exception');

        $this->assertStringStartsWith('Muitas requisições. Tente novamente em', $response->json('message'));
    }

    /** abort() nos controllers devolvia o stack trace completo com APP_DEBUG ligado (ambiente do avaliador). */
    public function test_http_errors_from_abort_have_only_the_message_even_in_debug(): void
    {
        config(['app.debug' => true]);
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($hotel)->create());

        $this->getJson(self::API."/hotels/{$hotel->id}/report")
            ->assertForbidden()
            ->assertExactJson(['message' => 'Apenas administradores e gerentes do hotel acessam o relatório.']);

        $this->getJson(self::API.'/users')->assertForbidden()->assertExactJson(['message' => 'Sem permissão para listar usuários.']);
    }

    public function test_unexpected_errors_never_leak_internal_details_even_with_debug_on(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->get('api/v1/__falha', fn () => throw new \RuntimeException('SQLSTATE segredo interno /var/www/html'));

        $response = $this->getJson('/api/v1/__falha')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Erro interno ao processar a requisição. Se persistir, informe o X-Request-Id ao suporte.')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertHeader('X-Request-Id');

        $this->assertStringNotContainsString('segredo', $response->getContent());
    }

    public function test_database_unavailable_returns_503(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->get('api/v1/__sem_banco', fn () => throw new QueryException(
            'mysql', 'select 1', [], new \PDOException('SQLSTATE[HY000] [2002] Connection refused'),
        ));

        $this->getJson('/api/v1/__sem_banco')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Serviço temporariamente indisponível. Tente novamente em instantes.');
    }

    public function test_huge_coupon_max_uses_is_a_validation_error_not_a_database_error(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/coupons', ['code' => 'GRANDE', 'type' => 'percent', 'value' => 10, 'max_uses' => PHP_INT_MAX])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_uses');
    }
}
