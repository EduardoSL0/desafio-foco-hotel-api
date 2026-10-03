<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReserveStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportXmlCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_hotels_rooms_and_reserves_from_xml(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $this->assertDatabaseCount('hotels', 3);
        $this->assertDatabaseCount('rooms', 6);
        $this->assertDatabaseCount('reserves', 6);
        $this->assertDatabaseCount('dailies', 18);
        $this->assertDatabaseCount('payments', 1);
        // "Fulaninho de Tal" aparece em duas reservas com o mesmo telefone: 1 hóspede só.
        $this->assertDatabaseCount('guests', 5);
        $this->assertSame(2, Guest::query()->where('name', 'Fulaninho')->first()->reserves()->count());

        $this->assertDatabaseHas('hotels', ['external_code' => '1', 'name' => 'Hotel Foco Prime']);

        $room = Room::query()->where('external_code', '3')->with('hotel')->first();
        $this->assertSame('Room 1 Hotel 2', $room->name);
        $this->assertSame('2', $room->hotel->external_code);
    }

    public function test_maps_reserve_totals_dailies_payments_and_status(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $reserve = Reserve::query()->where('external_code', '1')->with(['dailies', 'payments', 'guests'])->first();

        $this->assertSame('2022-12-01', $reserve->check_in->toDateString());
        $this->assertSame('2022-12-04', $reserve->check_out->toDateString());
        $this->assertEquals(300.00, (float) $reserve->total);
        $this->assertCount(3, $reserve->dailies);
        $this->assertSame(PaymentMethod::CreditCard, $reserve->payments->first()->method);
        $this->assertSame(ReserveStatus::PartiallyPaid, $reserve->status);
        $this->assertSame('xml', $reserve->source);
        $this->assertSame('5571995959595', $reserve->guests->first()->phone);

        $unpaid = Reserve::query()->where('external_code', '3')->first();
        $this->assertSame(ReserveStatus::Pending, $unpaid->status);
    }

    public function test_backfills_room_rate_from_imported_dailies(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $this->assertEquals(100.00, (float) Room::query()->where('external_code', '1')->value('daily_price'));
        $this->assertEquals(300.00, (float) Room::query()->where('external_code', '4')->value('daily_price'));
        // Quartos do hotel 3 não possuem reservas no XML: continuam sem tarifa.
        $this->assertNull(Room::query()->where('external_code', '5')->value('daily_price'));
    }

    public function test_reports_daily_outside_reserve_period(): void
    {
        $this->artisan('import:xml')
            ->expectsOutputToContain('Reserva 6: diária 2022-12-03 fora do período')
            ->assertSuccessful();
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('import:xml')->assertSuccessful();
        $this->artisan('import:xml')->assertSuccessful();

        $this->assertDatabaseCount('hotels', 3);
        $this->assertDatabaseCount('rooms', 6);
        $this->assertDatabaseCount('reserves', 6);
        $this->assertDatabaseCount('dailies', 18);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('guests', 5);
    }

    public function test_reimport_preserves_payments_registered_through_the_api(): void
    {
        $this->artisan('import:xml')->assertSuccessful();

        $reserve = Reserve::query()->where('external_code', '1')->first();
        $reserve->payments()->create(['method' => PaymentMethod::Pix, 'value' => 200, 'source' => 'api']);

        $this->artisan('import:xml')->assertSuccessful();

        $reserve->refresh();
        $this->assertSame(2, $reserve->payments()->count());
        $this->assertSame(ReserveStatus::Paid, $reserve->status);
    }

    public function test_skips_reserve_whose_room_belongs_to_another_hotel(): void
    {
        $dir = $this->fixtureDirectory(<<<'XML'
            <Reserves>
                <Reserve id="99" hotelCode="1" roomCode="3">
                    <CheckIn>2022-12-01</CheckIn>
                    <CheckOut>2022-12-02</CheckOut>
                    <Total>100.00</Total>
                </Reserve>
            </Reserves>
            XML);

        $this->artisan('import:xml', ['--path' => $dir])
            ->expectsOutputToContain('quarto 3 não pertence ao hotel 1')
            ->assertSuccessful();

        $this->assertDatabaseCount('reserves', 0);
    }

    public function test_fails_without_touching_database_when_a_file_is_missing(): void
    {
        $this->artisan('import:xml', ['--path' => sys_get_temp_dir().'/inexistente-'.uniqid()])
            ->assertFailed();

        $this->assertDatabaseCount('hotels', 0);
    }

    public function test_fails_on_malformed_xml(): void
    {
        $dir = $this->fixtureDirectory('<Reserves><Reserve id="1">');

        $this->artisan('import:xml', ['--path' => $dir])->assertFailed();

        $this->assertDatabaseCount('hotels', 0);
    }

    public function test_rejects_file_with_wrong_root_element(): void
    {
        $dir = $this->fixtureDirectory('<Reserves/>', '<Foo><Hotel id="9"><Name>Intruso</Name></Hotel></Foo>');

        $this->artisan('import:xml', ['--path' => $dir])->assertFailed();

        $this->assertDatabaseCount('hotels', 0);
    }

    public function test_invalid_records_are_skipped_without_aborting_the_import(): void
    {
        $hotels = '<Hotels><Hotel id="1"><Name>Hotel Foco Prime</Name></Hotel><Hotel id="2"><Name>Hotel Foco Beach</Name></Hotel>'
            .'<Hotel id="3"><Name>Hotel Foco Privillege</Name></Hotel><Hotel id="4"><Name>'.str_repeat('A', 300).'</Name></Hotel>'
            .'<Hotel id="1"><Name>Duplicado</Name></Hotel></Hotels>';
        $reserves = <<<'XML'
            <Reserves>
                <Reserve id="80" hotelCode="1" roomCode="1">
                    <CheckIn>2023-01-01</CheckIn><CheckOut>2023-01-02</CheckOut><Total>99999999999999</Total>
                </Reserve>
                <Reserve id="81" hotelCode="1" roomCode="1">
                    <CheckIn>2023-02-01</CheckIn><CheckOut>2023-02-03</CheckOut>
                    <Dailies>
                        <Daily><Date>2023-02-01</Date><Value>-50</Value></Daily>
                        <Daily><Date>2023-02-02</Date><Value>120</Value></Daily>
                    </Dailies>
                </Reserve>
                <Reserve id="81" hotelCode="1" roomCode="1">
                    <CheckIn>2023-05-01</CheckIn><CheckOut>2023-05-02</CheckOut><Total>1</Total>
                </Reserve>
            </Reserves>
            XML;

        $this->artisan('import:xml', ['--path' => $this->fixtureDirectory($reserves, $hotels)])
            ->expectsOutputToContain('nome (máx. 150 caracteres) longo demais')
            ->expectsOutputToContain('Hotel 1: id duplicado')
            ->expectsOutputToContain('Reserva 80: valor acima do máximo')
            ->expectsOutputToContain('diária 2023-02-01 com valor inválido ignorada')
            ->expectsOutputToContain('Reserva 81: id duplicado')
            ->assertSuccessful();

        $this->assertDatabaseCount('hotels', 3);
        $this->assertSame('Hotel Foco Prime', Hotel::query()->where('external_code', '1')->value('name'));
        $this->assertDatabaseMissing('reserves', ['external_code' => '80']);

        $reserve = Reserve::query()->where('external_code', '81')->with('dailies')->first();
        $this->assertSame('2023-02-01', $reserve->check_in->toDateString());
        $this->assertCount(1, $reserve->dailies);
        $this->assertEquals(120.0, (float) $reserve->total);
    }

    /** Cria um diretório temporário com os XMLs reais de hotéis/quartos e um reserves.xml customizado. */
    private function fixtureDirectory(string $reservesXml, ?string $hotelsXml = null): string
    {
        $dir = sys_get_temp_dir().'/foco-import-'.uniqid();
        File::ensureDirectoryExists($dir);
        $hotelsXml === null
            ? File::copy(database_path('xml/hotels.xml'), "{$dir}/hotels.xml")
            : File::put("{$dir}/hotels.xml", $hotelsXml);
        File::copy(database_path('xml/rooms.xml'), "{$dir}/rooms.xml");
        File::put("{$dir}/reserves.xml", $reservesXml);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));

        return $dir;
    }
}
