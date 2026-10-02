<?php

namespace Tests\Feature;

use App\Mail\ReserveConfirmation;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Recursos voltados ao hóspede e proteções de inventário para o hoteleiro. */
class GuestExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_gets_a_non_sequential_locator_code(): void
    {
        $room = Room::factory()->create(['inventory' => 2]);

        $first = $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated()->json('data.code');
        $second = $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated()->json('data.code');

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_guest_looks_up_reserve_with_code_and_last_name(): void
    {
        $room = Room::factory()->create();
        $code = $this->postJson(self::API.'/reserves', $this->payload($room))->json('data.code');

        $this->postJson(self::API.'/reserves/lookup', ['code' => strtolower($code), 'last_name' => ' souza '])
            ->assertOk()
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.amounts.total', 300.0);
    }

    public function test_lookup_does_not_reveal_whether_code_exists(): void
    {
        $room = Room::factory()->create();
        $code = $this->postJson(self::API.'/reserves', $this->payload($room))->json('data.code');

        $wrongName = $this->postJson(self::API.'/reserves/lookup', ['code' => $code, 'last_name' => 'Outro'])->assertNotFound();
        $wrongCode = $this->postJson(self::API.'/reserves/lookup', ['code' => 'ZZZZZZZZ', 'last_name' => 'Souza'])->assertNotFound();

        $this->assertSame($wrongName->json('message'), $wrongCode->json('message'));
    }

    public function test_lookup_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::API.'/reserves/lookup', ['code' => 'ZZZZZZZZ', 'last_name' => 'X'])->assertNotFound();
        }

        $this->postJson(self::API.'/reserves/lookup', ['code' => 'ZZZZZZZZ', 'last_name' => 'X'])->assertStatus(429);
    }

    /** Regressão: com "throttle:N,1" todas as rotas dividiam um único contador por IP. */
    public function test_browsing_the_site_does_not_block_lookup_or_login(): void
    {
        $room = Room::factory()->create(['inventory' => 5]);

        for ($i = 0; $i < 25; $i++) {
            $this->getJson(self::API.'/hotels')->assertOk();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
        }

        $code = Reserve::query()->value('code');

        $this->postJson(self::API.'/reserves/lookup', ['code' => $code, 'last_name' => 'Souza'])->assertOk();
        $this->postJson(self::API.'/auth/login', ['email' => 'ninguem@foco.test', 'password' => 'x'])->assertUnauthorized();
    }

    public function test_login_is_limited_per_email_against_brute_force(): void
    {
        User::factory()->create(['email' => 'alvo@foco.test', 'password' => 'correta123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::API.'/auth/login', ['email' => 'alvo@foco.test', 'password' => "errada{$i}"])->assertUnauthorized();
        }

        $this->postJson(self::API.'/auth/login', ['email' => 'alvo@foco.test', 'password' => 'correta123'])->assertStatus(429);
    }

    public function test_sends_confirmation_email_to_guest(): void
    {
        Mail::fake();
        $room = Room::factory()->create();

        $code = $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated()->json('data.code');

        Mail::assertSent(ReserveConfirmation::class, function (ReserveConfirmation $mail) use ($code) {
            return $mail->hasTo('maria@example.com')
                && $mail->reserve->code === $code
                && str_contains($mail->render(), $code);
        });
    }

    public function test_no_email_when_guest_has_none_or_reserve_fails(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['inventory' => 1]);
        $payload = $this->payload($room);
        unset($payload['guests'][0]['email']);

        $this->postJson(self::API.'/reserves', $payload)->assertCreated();
        // Quarto lotado: reserva recusada, nenhum e-mail de confirmação.
        $this->postJson(self::API.'/reserves', $this->payload($room))->assertStatus(409);

        Mail::assertNothingSent();
    }

    public function test_cannot_reduce_inventory_below_future_bookings(): void
    {
        $room = Room::factory()->create(['inventory' => 3]);
        Reserve::factory()->forRoom($room)->count(2)->between(now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString())->create();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->patchJson(self::API."/rooms/{$room->id}", ['inventory' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('inventory');

        $this->patchJson(self::API."/rooms/{$room->id}", ['inventory' => 2])
            ->assertOk()
            ->assertJsonPath('data.inventory', 2);
    }

    private function payload(Room $room): array
    {
        return [
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
            'guests' => [
                ['name' => 'Maria', 'last_name' => 'Souza', 'phone' => '5571999990000', 'email' => 'maria@example.com'],
            ],
        ];
    }
}
