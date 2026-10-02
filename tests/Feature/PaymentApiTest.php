<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $reserve = Reserve::factory()->create();

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 3, 'value' => 10])
            ->assertUnauthorized();
    }

    public function test_registers_payments_and_updates_reserve_status(): void
    {
        $reserve = Reserve::factory()->create(['total' => 300]);
        Sanctum::actingAs(User::factory()->receptionist($reserve->hotel)->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 3, 'value' => 100])
            ->assertCreated()
            ->assertJsonPath('data.value', 100.0)
            ->assertJsonPath('reserve.status', 'partially_paid')
            ->assertJsonPath('reserve.balance', 200.0);

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 2, 'value' => 200])
            ->assertCreated()
            ->assertJsonPath('reserve.status', 'paid')
            ->assertJsonPath('reserve.balance', 0.0);

        $this->getJson(self::API."/reserves/{$reserve->id}/payments")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rejects_payment_above_balance(): void
    {
        $reserve = Reserve::factory()->create(['total' => 300]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 4, 'value' => 300.01])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');
    }

    public function test_charges_interest_above_interest_free_installments(): void
    {
        config(['hotel.payments.interest_free_installments' => 3, 'hotel.payments.monthly_interest_percent' => 1.99]);
        $reserve = Reserve::factory()->create(['total' => 300]);
        Sanctum::actingAs(User::factory()->admin()->create());

        // 100,00 em 6x: 3 parcelas excedentes * 1,99% = 5,97% => 5,97 de juros
        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 1, 'value' => 100, 'installments' => 6])
            ->assertCreated()
            ->assertJsonPath('data.installments', 6)
            ->assertJsonPath('data.interest', 5.97);

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 1, 'value' => 100, 'installments' => 3])
            ->assertCreated()
            ->assertJsonPath('data.interest', 0.0);
    }

    public function test_rejects_installments_for_methods_other_than_credit_card(): void
    {
        $reserve = Reserve::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 3, 'value' => 50, 'installments' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installments');
    }

    public function test_rejects_payment_on_cancelled_reserve(): void
    {
        $reserve = Reserve::factory()->cancelled()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 3, 'value' => 50])
            ->assertStatus(409);
    }

    public function test_staff_from_another_hotel_cannot_register_payment(): void
    {
        $reserve = Reserve::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist(Hotel::factory()->create())->create());

        $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => 3, 'value' => 50])
            ->assertForbidden();
    }
}
