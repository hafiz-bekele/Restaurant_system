<?php

namespace Tests\Feature;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationDaySurcharge;
use App\Models\ReservationPricing;
use App\Models\RestaurantTable;
use App\Services\Payment\ChapaService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure base pricing is seeded
        $pricing = [
            ['duration_hours' => 1,  'price' => 12.50],
            ['duration_hours' => 2,  'price' => 25.00],
            ['duration_hours' => 3,  'price' => 37.50],
            ['duration_hours' => 6,  'price' => 75.00],
            ['duration_hours' => 12, 'price' => 150.00],
            ['duration_hours' => 18, 'price' => 225.00],
            ['duration_hours' => 24, 'price' => 300.00],
        ];
        foreach ($pricing as $p) {
            ReservationPricing::updateOrCreate(['duration_hours' => $p['duration_hours']], $p);
        }

        $surcharges = [
            ['days_ahead' => 0, 'percent' => 0.00],
            ['days_ahead' => 1, 'percent' => 25.00],
            ['days_ahead' => 2, 'percent' => 40.00],
        ];
        foreach ($surcharges as $s) {
            ReservationDaySurcharge::updateOrCreate(['days_ahead' => $s['days_ahead']], $s);
        }
    }

    public function test_price_calculation_for_today_tomorrow_and_future_durations(): void
    {
        $service = app(ReservationService::class);

        // Table reservations are complimentary (no hourly charges or advance surcharges)
        $todayPrice = $service->calculatePrice(2, Carbon::now());
        $this->assertEquals(0.00, $todayPrice['price_base']);
        $this->assertEquals(0.00, $todayPrice['surcharge_percent']);
        $this->assertEquals(0.00, $todayPrice['surcharge_amount']);
        $this->assertEquals(0.00, $todayPrice['total_price']);

        $tomorrowPrice = $service->calculatePrice(2, Carbon::now()->addDay());
        $this->assertEquals(0.00, $tomorrowPrice['total_price']);

        $twoDaysPrice = $service->calculatePrice(3, Carbon::now()->addDays(2));
        $this->assertEquals(0.00, $twoDaysPrice['total_price']);
    }

    public function test_24_hour_max_duration_rule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ReservationService::class)->calculatePrice(25, Carbon::now());
    }

    public function test_overlap_logic_and_available_tables_filtering(): void
    {
        $service = app(ReservationService::class);

        $table = RestaurantTable::firstOrCreate(
            ['name' => 'Unit-Test-Table-A'],
            [
                'capacity'   => 4,
                'area'       => 'indoor',
                'is_active'  => true,
                'status'     => 'available',
                'qr_token'   => Str::random(32),
            ]
        );

        $testStart = Carbon::now()->addDays(1)->setHour(14)->setMinute(0)->setSecond(0);
        $testEnd   = $testStart->copy()->addHours(2); // 14:00 - 16:00

        // Clean any existing reservations for this test table
        Reservation::where('table_id', $table->id)->delete();

        // 1. Table should be available initially
        $available = $service->getAvailableTables($testStart, 2, 4);
        $this->assertTrue($available->contains('id', $table->id));

        // 2. Create confirmed reservation from 14:00 to 16:00
        Reservation::create([
            'table_id'        => $table->id,
            'customer_name'   => 'Existing Diner',
            'phone'           => '+251911223344',
            'email'           => 'diner@example.com',
            'party_size'      => 4,
            'start_at'        => $testStart,
            'end_at'          => $testEnd,
            'duration_hours'  => 2,
            'status'          => ReservationStatus::Confirmed,
            'total_price'     => 25.00,
            'price_base'      => 25.00,
        ]);

        // 3. Request overlapping window (15:00 - 17:00): table MUST NOT be returned
        $overlappingStart = $testStart->copy()->addHour(); // 15:00
        $availableOverlapping = $service->getAvailableTables($overlappingStart, 2, 4);
        $this->assertFalse($availableOverlapping->contains('id', $table->id));

        // 4. Request non-overlapping window (16:00 - 18:00): table SHOULD be available
        $afterStart = $testEnd->copy(); // 16:00
        $availableAfter = $service->getAvailableTables($afterStart, 2, 4);
        $this->assertTrue($availableAfter->contains('id', $table->id));
    }

    public function test_concurrent_booking_of_same_table_fails_on_second_request(): void
    {
        $service = app(ReservationService::class);

        $table = RestaurantTable::firstOrCreate(
            ['name' => 'Unit-Test-Table-B'],
            [
                'capacity'   => 4,
                'area'       => 'vip',
                'is_active'  => true,
                'status'     => 'available',
                'qr_token'   => Str::random(32),
            ]
        );

        Reservation::where('table_id', $table->id)->delete();

        $bookingData = [
            'table_id'         => $table->id,
            'customer_name'    => 'Guest First',
            'phone'            => '+251912345678',
            'email'            => 'first@example.com',
            'party_size'       => 4,
            'start_at'         => Carbon::now()->addDays(2)->setHour(18)->setMinute(0)->toDateTimeString(),
            'duration_hours'   => 2,
            'special_requests' => 'Quiet please',
        ];

        // First booking succeeds
        $firstResult = $service->createPendingReservation($bookingData);
        $this->assertNotNull($firstResult['reservation']);
        $this->assertEquals(ReservationStatus::PendingPayment, $firstResult['reservation']->status);

        // Second booking for overlapping time must fail
        $this->expectException(\DomainException::class);
        $service->createPendingReservation($bookingData);
    }

    public function test_expiry_scheduler_cancels_holds_and_expires_finished_reservations(): void
    {
        $service = app(ReservationService::class);

        $table = RestaurantTable::firstOrCreate(
            ['name' => 'Unit-Test-Table-C'],
            [
                'capacity'   => 2,
                'area'       => 'quiet',
                'is_active'  => true,
                'status'     => 'available',
                'qr_token'   => Str::random(32),
            ]
        );

        // 1. Pending reservation with expired hold (15 minutes ago)
        $expiredHold = Reservation::create([
            'table_id'        => $table->id,
            'customer_name'   => 'Hold Expired Guest',
            'phone'           => '+251911112233',
            'email'           => 'expired@example.com',
            'party_size'      => 2,
            'start_at'        => Carbon::now()->addDays(1),
            'end_at'          => Carbon::now()->addDays(1)->addHours(2),
            'duration_hours'  => 2,
            'status'          => ReservationStatus::PendingPayment,
            'hold_expires_at' => Carbon::now()->subMinutes(5),
            'total_price'     => 25.00,
            'price_base'      => 25.00,
        ]);

        // 2. Confirmed reservation that finished in the past
        $finishedRes = Reservation::create([
            'table_id'        => $table->id,
            'customer_name'   => 'Finished Guest',
            'phone'           => '+251922334455',
            'email'           => 'finished@example.com',
            'party_size'      => 2,
            'start_at'        => Carbon::now()->subHours(3),
            'end_at'          => Carbon::now()->subHours(1),
            'duration_hours'  => 2,
            'status'          => ReservationStatus::Confirmed,
            'total_price'     => 25.00,
            'price_base'      => 25.00,
        ]);

        // Run scheduler
        $result = $service->expireOverdueReservations();

        $this->assertGreaterThanOrEqual(1, $result['cancelled_holds']);
        $this->assertGreaterThanOrEqual(1, $result['expired_reservations']);

        $this->assertEquals(ReservationStatus::Cancelled, $expiredHold->fresh()->status);
        $this->assertEquals(ReservationStatus::Expired, $finishedRes->fresh()->status);
    }

    public function test_api_available_tables_and_create_reservation_endpoint(): void
    {
        $table = RestaurantTable::create([
            'name'       => 'Api-Test-Table',
            'capacity'   => 4,
            'area'       => 'window',
            'features'   => ['Sunset Vista', 'Window View'],
            'is_active'  => true,
            'status'     => 'available',
            'qr_token'   => Str::random(32),
        ]);

        $tomorrow = Carbon::now()->addDay()->toDateString();

        // 1. Availability API
        $availResponse = $this->getJson("/api/v1/reservations/available-tables?date={$tomorrow}&start_time=19:00&duration_hours=2&party_size=3");
        $availResponse->assertStatus(200);
        $this->assertTrue(collect($availResponse->json('data'))->contains('id', $table->id));

        // 2. Create Reservation API
        $postResponse = $this->postJson('/api/v1/reservations', [
            'table_id'         => $table->id,
            'customer_name'    => 'Abebe Bikila',
            'phone'            => '+251 91 122 3344',
            'email'            => 'abebe@ethio.et',
            'party_size'       => 3,
            'date'             => $tomorrow,
            'start_time'       => '19:00',
            'duration_hours'   => 2,
            'special_requests' => 'Window seat celebration',
        ]);

        $postResponse->assertStatus(201);
        $resData = $postResponse->json('data');
        $this->assertNotNull($resData['tx_ref']);
        $ulid = $resData['reservation']['ulid'];

        // 3. Status is confirmed directly
        $this->assertEquals('confirmed', $resData['reservation']['status']);

        // 4. Overlap check: table is now blocked for same window
        $recheck = $this->getJson("/api/v1/reservations/available-tables?date={$tomorrow}&start_time=19:00&duration_hours=2&party_size=3");
        $this->assertFalse(collect($recheck->json('data'))->contains('id', $table->id));

        // 5. Verify payment endpoint
        $verifyRes = $this->postJson("/api/v1/reservations/{$ulid}/verify-payment");
        $verifyRes->assertStatus(200);
        $this->assertEquals('paid', $verifyRes->json('data.status'));
        $this->assertEquals('confirmed', $verifyRes->json('data.reservation.status'));
    }

    public function test_webhook_signature_and_idempotent_processing(): void
    {
        $table = RestaurantTable::create([
            'name'       => 'Webhook-Test-Table',
            'capacity'   => 2,
            'area'       => 'quiet',
            'features'   => ['Noise Isolated'],
            'is_active'  => true,
            'status'     => 'available',
            'qr_token'   => Str::random(32),
        ]);

        $service = app(ReservationService::class);
        $created = $service->createPendingReservation([
            'table_id'       => $table->id,
            'customer_name'  => 'Sara Tesfaye',
            'phone'          => '0911223344',
            'email'          => 'sara@example.et',
            'party_size'     => 2,
            'start_at'       => Carbon::now()->addDays(2)->setHour(20)->setMinute(0)->toDateTimeString(),
            'duration_hours' => 3,
        ]);

        $ulid = $created['reservation']->ulid;
        $txRef = 'RSV-' . $ulid;

        $webhookPayload = json_encode([
            'status'   => 'success',
            'tx_ref'   => $txRef,
            'amount'   => 52.50,
            'currency' => 'ETB',
        ]);

        config(['services.chapa.webhook_secret' => 'test-webhook-secret-123']);
        $validSignature = hash_hmac('sha256', $webhookPayload, 'test-webhook-secret-123');

        // First webhook call: should confirm reservation
        $res1 = $this->call(
            'POST',
            '/api/v1/webhooks/chapa',
            [],
            [],
            [],
            [
                'HTTP_CHAPA_SIGNATURE' => $validSignature,
                'CONTENT_TYPE'         => 'application/json',
            ],
            $webhookPayload
        );

        $res1->assertStatus(200);

        $reservation = Reservation::where('ulid', $ulid)->first();
        $this->assertEquals(ReservationStatus::Confirmed, $reservation->status);

        // Second webhook call (idempotent duplicate): should safely return 200 without error
        $res2 = $this->call(
            'POST',
            '/api/v1/webhooks/chapa',
            [],
            [],
            [],
            [
                'HTTP_CHAPA_SIGNATURE' => $validSignature,
                'CONTENT_TYPE'         => 'application/json',
            ],
            $webhookPayload
        );

        $res2->assertStatus(200);
    }
}
