<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Estorno de pagamentos: saldo, status e relatório recalculados. */
class RefundApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_refunds_a_payment_and_balance_is_recalculated(): void
    {
        [$room, $reserve, $payment] = $this->paidReserve();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$payment->id}/refund")
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('reserve.status', 'pending')
            ->assertJsonPath('reserve.balance', 200.0);

        $this->assertNotNull($payment->fresh()->refunded_at);
        $this->getJson(self::API."/reserves/{$reserve->id}")->assertJsonPath('data.amounts.paid', 0.0);
    }

    public function test_refund_cannot_be_repeated(): void
    {
        [$room, $reserve, $payment] = $this->paidReserve();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$payment->id}/refund")->assertOk();
        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$payment->id}/refund")
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment.0', 'Este pagamento já foi estornado.');
    }

    public function test_receptionist_and_other_hotels_cannot_refund(): void
    {
        [$room, $reserve, $payment] = $this->paidReserve();

        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());
        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$payment->id}/refund")->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->manager(Room::factory()->create()->hotel)->create());
        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$payment->id}/refund")->assertForbidden();
    }

    public function test_payment_must_belong_to_the_reserve(): void
    {
        [$room, $reserve] = $this->paidReserve();
        [, , $otherPayment] = $this->paidReserve();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$otherPayment->id}/refund")->assertNotFound();
    }

    public function test_cancelled_reserve_keeps_received_money_in_the_report_until_refunded(): void
    {
        [$room, $reserve, $payment] = $this->paidReserve();
        Sanctum::actingAs(User::factory()->admin()->create());
        $query = '?from='.now()->startOfMonth()->toDateString().'&to='.now()->addMonths(2)->endOfMonth()->toDateString();

        $this->patchJson(self::API."/reserves/{$reserve->id}/cancel")->assertOk();
        $this->getJson(self::API."/hotels/{$room->hotel_id}/report{$query}")
            ->assertJsonPath('data.reserves.paid_amount', 100.0)
            ->assertJsonPath('data.reserves.refunded_amount', 0.0);

        $this->postJson(self::API."/reserves/{$reserve->id}/payments/{$payment->id}/refund")
            ->assertOk()
            ->assertJsonPath('reserve.status', 'cancelled');

        $this->getJson(self::API."/hotels/{$room->hotel_id}/report{$query}")
            ->assertJsonPath('data.reserves.paid_amount', 0.0)
            ->assertJsonPath('data.reserves.refunded_amount', 100.0);
    }

    public function test_xml_payments_are_refunded_at_the_source(): void
    {
        $this->artisan('import:xml')->assertSuccessful();
        $payment = Payment::query()->where('source', 'xml')->firstOrFail();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API."/reserves/{$payment->reserve_id}/payments/{$payment->id}/refund")
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment.0', 'Pagamentos importados do XML devem ser estornados no sistema de origem.');
    }

    /** @return array{0: Room, 1: Reserve, 2: Payment} */
    private function paidReserve(): array
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        $id = $this->postJson(self::API.'/reserves', [
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => [['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5571999990001']],
        ])->json('data.id');

        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());
        $this->postJson(self::API."/reserves/{$id}/payments", ['method' => 3, 'value' => 100])->assertCreated();
        $this->app['auth']->forgetGuards();

        $reserve = Reserve::query()->findOrFail($id);

        return [$room, $reserve, $reserve->payments()->firstOrFail()];
    }
}
