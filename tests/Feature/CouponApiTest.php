<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouponApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_coupon_is_deleted(): void
    {
        $coupon = Coupon::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(self::API."/coupons/{$coupon->id}")->assertNoContent();

        $this->assertModelMissing($coupon);
    }

    public function test_used_coupon_is_deactivated_to_keep_reserve_history(): void
    {
        $room = Room::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'USADO']);
        $this->postJson(self::API.'/reserves', [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests' => [['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5571999990001']],
            'coupon_code' => 'USADO',
        ])->assertCreated();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(self::API."/coupons/{$coupon->id}")->assertNoContent();

        $this->assertFalse($coupon->fresh()->active);
        $this->getJson(self::API.'/reserves')->assertJsonPath('data.0.coupon_code', 'USADO');
    }
}
