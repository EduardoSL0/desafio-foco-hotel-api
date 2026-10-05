<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Promotion;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReserveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_reserve_with_dailies_guests_and_total(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);

        $response = $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.nights', 3)
            ->assertJsonPath('data.amounts.subtotal', 300.0)
            ->assertJsonPath('data.amounts.total', 300.0)
            ->assertJsonPath('data.amounts.balance', 300.0)
            ->assertJsonCount(3, 'data.dailies')
            ->assertJsonPath('data.dailies.0.date', $this->day(10))
            ->assertJsonPath('data.guests.0.name', 'Maria');

        $this->assertDatabaseHas('reserves', ['id' => $response->json('data.id'), 'room_id' => $room->id, 'hotel_id' => $room->hotel_id]);
        $this->assertDatabaseCount('dailies', 3);
        $this->assertDatabaseHas('guests', ['name' => 'Maria', 'last_name' => 'Souza', 'phone' => '5571999990000']);
    }

    public function test_reuses_existing_guest(): void
    {
        $room = Room::factory()->create(['inventory' => 2]);

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();

        $this->assertDatabaseCount('guests', 1);
    }

    public function test_applies_hotel_service_fee(): void
    {
        $hotel = Hotel::factory()->withServiceFee(10)->create();
        $room = Room::factory()->for($hotel)->create(['daily_price' => 100]);

        $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertCreated()
            ->assertJsonPath('data.amounts.fees', 30.0)
            ->assertJsonPath('data.amounts.total', 330.0);
    }

    public function test_applies_percent_coupon_and_counts_usage(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        $coupon = Coupon::factory()->create(['code' => 'DESC10', 'type' => DiscountType::Percent, 'value' => 10]);

        $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'desc10']))
            ->assertCreated()
            ->assertJsonPath('data.coupon_code', 'DESC10')
            ->assertJsonPath('data.amounts.discount', 30.0)
            ->assertJsonPath('data.amounts.total', 270.0);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_fixed_coupon_never_exceeds_total(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Coupon::factory()->fixed(1000)->create(['code' => 'GRATIS']);

        $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'GRATIS']))
            ->assertCreated()
            ->assertJsonPath('data.amounts.total', 0.0)
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_rejects_invalid_or_exhausted_coupon(): void
    {
        $room = Room::factory()->create();
        Coupon::factory()->create(['code' => 'ESGOTADO', 'max_uses' => 1, 'used_count' => 1]);
        Coupon::factory()->create(['code' => 'OUTROHOTEL', 'hotel_id' => Hotel::factory()]);

        foreach (['NAOEXISTE', 'ESGOTADO', 'OUTROHOTEL'] as $code) {
            $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => $code]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('coupon_code');
        }

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_applies_promotion_only_on_covered_nights(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Promotion::factory()->create([
            'hotel_id' => $room->hotel_id,
            'discount_percent' => 50,
            'starts_at' => $this->day(10),
            'ends_at' => $this->day(10),
        ]);

        $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertCreated()
            ->assertJsonPath('data.dailies.0.discount', 50.0)
            ->assertJsonPath('data.dailies.1.discount', 0.0)
            ->assertJsonPath('data.amounts.discount', 50.0)
            ->assertJsonPath('data.amounts.total', 250.0);
    }

    public function test_combines_promotion_coupon_and_service_fee_in_order(): void
    {
        $hotel = Hotel::factory()->withServiceFee(10)->create();
        $room = Room::factory()->for($hotel)->create(['daily_price' => 100]);
        Promotion::factory()->create(['hotel_id' => $hotel->id, 'discount_percent' => 20, 'starts_at' => $this->day(0), 'ends_at' => $this->day(60)]);
        Coupon::factory()->create(['code' => 'MENOS10', 'value' => 10]);

        // 300 - 20% promo (60) = 240; - 10% cupom (24) = 216; + 10% taxa (21,60) = 237,60
        $this->postJson(self::API.'/reserves/quote', $this->payload($room, ['coupon_code' => 'MENOS10']))
            ->assertOk()
            ->assertJsonPath('data.subtotal', 300.0)
            ->assertJsonPath('data.discount', 84.0)
            ->assertJsonPath('data.fees', 21.6)
            ->assertJsonPath('data.total', 237.6)
            ->assertJsonPath('data.available_units', 1);

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_rejects_reserve_when_room_is_fully_booked(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        Reserve::factory()->forRoom($room)->between($this->day(11), $this->day(12))->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Não há disponibilidade para este quarto no período informado.');
    }

    public function test_accepts_reserve_while_inventory_has_units(): void
    {
        $room = Room::factory()->create(['inventory' => 2]);
        Reserve::factory()->forRoom($room)->between($this->day(10), $this->day(13))->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
        $this->postJson(self::API.'/reserves', $this->payload($room))->assertStatus(409);
    }

    public function test_allows_back_to_back_reserves(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        Reserve::factory()->forRoom($room)->between($this->day(7), $this->day(10))->create();
        Reserve::factory()->forRoom($room)->between($this->day(13), $this->day(15))->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
    }

    public function test_cancelled_reserves_do_not_block_inventory(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        Reserve::factory()->forRoom($room)->cancelled()->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
    }

    public function test_rejects_more_guests_than_room_capacity(): void
    {
        $room = Room::factory()->create(['capacity' => 1]);
        $guests = [
            ['name' => 'Ana', 'last_name' => 'Lima', 'phone' => '5571911111111'],
            ['name' => 'Bia', 'last_name' => 'Lima', 'phone' => '5571922222222'],
        ];

        $this->postJson(self::API.'/reserves', $this->payload($room, ['guests' => $guests]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guests');
    }

    public function test_validates_dates_and_guests(): void
    {
        $room = Room::factory()->create();

        $this->postJson(self::API.'/reserves', [
            'room_id' => $room->id,
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'guests' => [['name' => 'Sem telefone', 'last_name' => 'X', 'phone' => '123']],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in', 'check_out', 'guests.0.phone']);
    }

    public function test_rejects_room_without_rate(): void
    {
        $room = Room::factory()->withoutRate()->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O quarto não possui tarifa (daily_price) configurada.');
    }

    public function test_rejects_deleted_room(): void
    {
        $room = Room::factory()->create();
        $room->delete();

        $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');
    }

    public function test_public_request_cannot_send_payments_with_reserve(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);

        $this->postJson(self::API.'/reserves', $this->payload($room, [
            'payments' => [['method' => 3, 'value' => 300]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payments');

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_staff_from_another_hotel_cannot_send_payments_with_reserve(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Sanctum::actingAs(User::factory()->receptionist(Hotel::factory()->create())->create());

        $this->postJson(self::API.'/reserves', $this->payload($room, [
            'payments' => [['method' => 3, 'value' => 100]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payments');
    }

    public function test_creates_reserve_with_initial_payment(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());

        $this->postJson(self::API.'/reserves', $this->payload($room, [
            'payments' => [['method' => 3, 'value' => 100]],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.amounts.paid', 100.0)
            ->assertJsonPath('data.amounts.balance', 200.0)
            ->assertJsonPath('data.payments.0.method_label', 'Pix');
    }

    public function test_rolls_back_reserve_when_initial_payment_is_invalid(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());

        $this->postJson(self::API.'/reserves', $this->payload($room, [
            'payments' => [['method' => 1, 'value' => 500]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payments.0.value');

        $this->assertDatabaseCount('reserves', 0);
        $this->assertDatabaseCount('guests', 0);
    }

    public function test_staff_lists_only_reserves_from_own_hotel(): void
    {
        $own = Room::factory()->create();
        $other = Room::factory()->create();
        Reserve::factory()->forRoom($own)->count(2)->create();
        Reserve::factory()->forRoom($other)->create();

        Sanctum::actingAs(User::factory()->receptionist($own->hotel)->create());

        $this->getJson(self::API.'/reserves')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_staff_cannot_view_reserve_from_another_hotel(): void
    {
        $reserve = Reserve::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist(Hotel::factory()->create())->create());

        $this->getJson(self::API."/reserves/{$reserve->id}")->assertForbidden();
    }

    public function test_manager_cancels_reserve_and_frees_inventory(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        $reserve = Reserve::factory()->forRoom($room)->create();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->patchJson(self::API."/reserves/{$reserve->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->patchJson(self::API."/reserves/{$reserve->id}/cancel")->assertStatus(409);

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
    }

    public function test_cancelling_releases_coupon_usage(): void
    {
        $room = Room::factory()->create(['inventory' => 2]);
        $coupon = Coupon::factory()->create(['code' => 'UNICO', 'max_uses' => 1]);

        $id = $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'UNICO']))
            ->assertCreated()
            ->json('data.id');

        // Cupom esgotado: segunda reserva é recusada.
        $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'UNICO']))
            ->assertJsonValidationErrors('coupon_code');

        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());
        $this->patchJson(self::API."/reserves/{$id}/cancel")->assertOk();

        $this->assertSame(0, $coupon->fresh()->used_count);
        $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'UNICO']))->assertCreated();
    }

    public function test_idempotency_key_prevents_duplicate_reserves(): void
    {
        $room = Room::factory()->create(['inventory' => 5]);
        $headers = ['Idempotency-Key' => 'reserva-abc-123'];

        $first = $this->postJson(self::API.'/reserves', $this->payload($room), $headers)->assertCreated();

        $this->postJson(self::API.'/reserves', $this->payload($room), $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('reserves', 1);

        // Mesma chave com outro conteúdo é rejeitada.
        $this->postJson(self::API.'/reserves', $this->payload($room, ['check_out' => $this->day(14)]), $headers)
            ->assertUnprocessable();

        $this->assertDatabaseCount('reserves', 1);
    }

    public function test_rejects_malformed_idempotency_key(): void
    {
        $room = Room::factory()->create();

        $this->postJson(self::API.'/reserves', $this->payload($room), ['Idempotency-Key' => 'curta'])
            ->assertStatus(400);
    }

    public function test_responses_carry_request_id(): void
    {
        $this->getJson(self::API.'/hotels')->assertHeader('X-Request-Id');

        $this->getJson(self::API.'/hotels', ['X-Request-Id' => 'trace-12345678'])
            ->assertHeader('X-Request-Id', 'trace-12345678');
    }

    public function test_filters_reserves_by_status(): void
    {
        $room = Room::factory()->create();
        Reserve::factory()->forRoom($room)->create();
        Reserve::factory()->forRoom($room)->cancelled()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(self::API.'/reserves?status=cancelled')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::API.'/reserves?status=invalido')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_optional_hotel_id_must_match_the_room(): void
    {
        $room = Room::factory()->create();
        $other = Hotel::factory()->create();

        $this->postJson(self::API.'/reserves', $this->payload($room, ['hotel_id' => $other->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_id.0', 'O quarto informado não pertence ao hotel informado.');

        $this->postJson(self::API.'/reserves/quote', $this->payload($room, ['hotel_id' => $other->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->postJson(self::API.'/reserves', $this->payload($room, ['hotel_id' => $room->hotel_id]))
            ->assertCreated();
    }

    public function test_receptionist_cannot_cancel_reserve(): void
    {
        $reserve = Reserve::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($reserve->hotel)->create());

        $this->patchJson(self::API."/reserves/{$reserve->id}/cancel")->assertForbidden();
    }

    private function payload(Room $room, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $room->id,
            'check_in' => $this->day(10),
            'check_out' => $this->day(13),
            'guests' => [
                ['name' => 'Maria', 'last_name' => 'Souza', 'phone' => '5571999990000', 'email' => 'maria@example.com'],
            ],
        ], $overrides);
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    public function test_idempotency_compares_json_content_not_key_order(): void
    {
        $room = Room::factory()->create(['inventory' => 3]);
        $payload = $this->payload($room);

        $first = $this->postJson(self::API.'/reserves', $payload, ['Idempotency-Key' => 'ordem-12345'])->assertCreated();
        $reordered = $this->call('POST', self::API.'/reserves', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => 'ordem-12345',
        ], json_encode(array_reverse($payload, true), JSON_PRETTY_PRINT));

        $reordered->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('data.id'), $reordered->json('data.id'));
        $this->assertSame(1, Reserve::query()->count());
    }

    public function test_coupon_discount_is_spread_over_the_dailies(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Coupon::factory()->fixed(10)->create(['code' => 'DEZREAIS']);

        $data = $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'DEZREAIS']))
            ->assertCreated()
            ->json('data');

        // 3 noites de 100 e cupom de 10: as diárias somam exatamente o total (290).
        $net = array_sum(array_map(fn (array $d) => round($d['value'] - $d['discount'], 2), $data['dailies']));
        $this->assertEqualsWithDelta(290.0, $net, 0.001);
        $this->assertSame(290.0, $data['amounts']['total']);
    }
}
