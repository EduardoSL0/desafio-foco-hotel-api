<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Dados pessoais dos hóspedes não podem vazar pelas rotas públicas. */
class GuestPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_does_not_expose_contacts_of_other_guests_or_payments(): void
    {
        $room = Room::factory()->create(['capacity' => 2]);
        $code = $this->postJson(self::API.'/reserves', $this->payload($room, [
            ['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5571999990001', 'email' => 'ana@example.com'],
            ['name' => 'Bruno', 'last_name' => 'Lima', 'phone' => '5571999990002', 'email' => 'bruno@example.com'],
        ]))->assertCreated()->json('data.code');

        $response = $this->postJson(self::API.'/reserves/lookup', ['code' => $code, 'last_name' => 'Souza'])
            ->assertOk()
            ->assertJsonPath('data.guests.1.name', 'Bruno')
            ->assertJsonMissingPath('data.guests.1.email')
            ->assertJsonMissingPath('data.guests.1.phone')
            ->assertJsonMissingPath('data.payments');

        $this->assertStringNotContainsString('bruno@example.com', $response->getContent());
        $this->assertStringNotContainsString('5571999990002', $response->getContent());
    }

    public function test_public_booking_never_reveals_the_email_already_registered_for_a_guest(): void
    {
        $room = Room::factory()->create(['inventory' => 2]);
        $victim = ['name' => 'Vitória', 'last_name' => 'Silva', 'phone' => '5571988887777'];

        $this->postJson(self::API.'/reserves', $this->payload($room, [[...$victim, 'email' => 'vitoria@example.com']]))->assertCreated();

        // Sem login, informando nome e telefone da mesma pessoa e outro e-mail.
        $response = $this->postJson(self::API.'/reserves', $this->payload($room, [[...$victim, 'email' => 'outra@example.com']]))
            ->assertCreated()
            ->assertJsonMissingPath('data.guests.0.email');

        $this->assertStringNotContainsString('vitoria@example.com', $response->getContent());
        // O e-mail já cadastrado também não é sobrescrito.
        $this->assertSame('vitoria@example.com', Guest::query()->where('phone', '5571988887777')->value('email'));
    }

    public function test_hotel_staff_still_gets_the_full_reserve_on_booking(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());

        $this->postJson(self::API.'/reserves', $this->payload($room, [
            ['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5571999990001', 'email' => 'ana@example.com'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.guests.0.email', 'ana@example.com')
            ->assertJsonPath('data.expires_at', null);
    }

    public function test_email_is_filled_when_the_existing_guest_had_none(): void
    {
        $room = Room::factory()->create(['inventory' => 2]);
        $guest = ['name' => 'Caio', 'last_name' => 'Reis', 'phone' => '5571977776666'];

        $this->postJson(self::API.'/reserves', $this->payload($room, [$guest]))->assertCreated();
        $this->postJson(self::API.'/reserves', $this->payload($room, [[...$guest, 'email' => 'caio@example.com']]))->assertCreated();

        $this->assertSame('caio@example.com', Guest::query()->where('phone', '5571977776666')->value('email'));
    }

    public function test_lookup_matches_last_name_with_accents_regardless_of_case(): void
    {
        $room = Room::factory()->create();
        $code = $this->postJson(self::API.'/reserves', $this->payload($room, [
            ['name' => 'João', 'last_name' => 'Conceição', 'phone' => '5571999990003'],
        ]))->json('data.code');

        $this->postJson(self::API.'/reserves/lookup', ['code' => $code, 'last_name' => 'CONCEIÇÃO'])->assertOk();
    }

    public function test_anonymize_command_removes_personal_data_but_keeps_the_reserve(): void
    {
        $room = Room::factory()->create();
        $this->postJson(self::API.'/reserves', $this->payload($room, [
            ['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5571999990001', 'email' => 'ana@example.com'],
        ]))->assertCreated();
        $guest = Guest::query()->firstOrFail();

        $this->artisan('guests:anonymize', ['ids' => [$guest->id]])->assertSuccessful();

        $guest->refresh();
        $this->assertSame('Hóspede', $guest->name);
        $this->assertSame('', $guest->phone);
        $this->assertNull($guest->email);
        $this->assertSame(1, Reserve::query()->count());
        $this->assertSame(1, $guest->reserves()->count());
    }

    private function payload(Room $room, array $guests): array
    {
        return [
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => $guests,
        ];
    }
}
