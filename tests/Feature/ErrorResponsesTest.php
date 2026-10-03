<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
