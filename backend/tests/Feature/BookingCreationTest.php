<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\CalculateBookingPrice;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_fixture_is_ready(): void
    {
        $fixture = $this->bookingFixture();

        $this->assertTrue($fixture['owner']->hasRole('OWNER'));
        $this->assertTrue($fixture['caregiver']->hasRole('CAREGIVER'));
        $this->assertSame('ACTIVE', $fixture['owner']->status);
        $this->assertSame('ACTIVE', $fixture['caregiver']->status);
        $this->assertNotSame($fixture['owner']->id, $fixture['caregiver']->id);
        $this->assertSame('ACTIVE', $fixture['pet']->status);
        $this->assertSame($fixture['owner']->id, $fixture['pet']->user_id);
        $this->assertNotSame($fixture['owner']->id, $fixture['foreign_pet']->user_id);

        $this->assertDatabaseHas('listings', [
            'id' => $fixture['active_listing_id'],
            'status' => 'ACTIVE',
            'price_vnd' => 50000,
        ]);
        $this->assertDatabaseHas('listings', [
            'id' => $fixture['draft_listing_id'],
            'status' => 'DRAFT',
        ]);

        $this->assertTrue($fixture['start_at']->isFuture());

        foreach ([$fixture['active_listing_id'], $fixture['draft_listing_id']] as $listingId) {
            $this->assertTrue(DB::table('availability_slots')
                ->where('listing_id', $listingId)
                ->where('start_at', '<=', $fixture['start_at'])
                ->where('end_at', '>=', $fixture['end_at'])
                ->whereNull('deleted_at')
                ->exists());
        }

        $snapshot = $fixture['pet']->snapshotForBooking();
        $this->assertSame(1, $snapshot['schema_version']);
        $this->assertSame('No chicken', $snapshot['diet_notes']);
        $this->assertArrayNotHasKey('user_id', $snapshot);

        $this->actingAs($fixture['owner'], 'web')
            ->getJson('/api/user')
            ->assertOk();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_valid_input_uses_session_owner_and_creates_requested_booking(): void
    {
        $fixture = $this->bookingFixture();
        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'] + [
                'owner_id' => $fixture['foreign_pet']->user_id,
                'amount' => 1,
                'status' => 'CONFIRMED',
            ])->assertCreated()->assertJsonPath('status', 'REQUESTED');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', [
            'owner_id' => $fixture['owner']->id,
            'caregiver_user_id' => $fixture['caregiver']->id,
            'price_snapshot_vnd' => 150000,
            'status' => 'REQUESTED',
        ]);
    }

    public function test_booking_route_requires_an_active_owner(): void
    {
        $fixture = $this->bookingFixture();
        $this->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'])->assertUnauthorized();

        DB::table('user_roles')->where('user_id', $fixture['caregiver']->id)->where('role', 'OWNER')->delete();
        $this->actingAs($fixture['caregiver'])->postJson('/api/bookings', $fixture['payload'])->assertForbidden();

        foreach (['SUSPENDED', 'DELETION_PENDING', 'DEACTIVATED'] as $status) {
            $fixture['owner']->status = $status;
            $fixture['owner']->save();
            $this->actingAs($fixture['owner'])->postJson('/api/bookings', $fixture['payload'])->assertForbidden();
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_invalid_input_fields_are_rejected_before_source_checks(): void
    {
        $fixture = $this->bookingFixture();
        $this->actingAs($fixture['owner']);

        foreach ([null, 'UPPERCASE', ' leading-space', 'trailing-space ', str_repeat('a', 129)] as $key) {
            $this->withHeaders($key === null ? [] : ['Idempotency-Key' => $key]);
            if ($key === null) {
                $this->withoutHeader('Idempotency-Key');
            }
            $this->postJson('/api/bookings', $fixture['payload'] + ['Idempotency-Key' => 'forged-body-key'])
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['error' => ['details' => ['Idempotency-Key'], 'request_id']]);
        }

        $this->withHeader('Idempotency-Key', $fixture['idempotency_key']);
        foreach ([
            ['pet_id', 0],
            ['listing_id', []],
            ['instructions', str_repeat('x', 2001)],
            ['start_at', '2030-01-01T08:00:00'],
            ['start_at', '2030-02-30T08:00:00Z'],
            ['start_at', '2030-01-01T25:00:00Z'],
            ['start_at', '2030-01-01T08:00:00.1234567Z'],
        ] as [$field, $value]) {
            $payload = array_replace($fixture['payload'], [$field => $value]);
            $this->postJson('/api/bookings', $payload)->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['error' => ['details' => [$field]]]);
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_invalid_sources_and_ranges_are_rejected(): void
    {
        foreach ([
            'foreign_pet' => [404, 'NOT_FOUND'],
            'deleted_pet' => [404, 'NOT_FOUND'],
            'archived_pet' => [409, 'INVALID_STATE'],
            'hidden_draft' => [404, 'NOT_FOUND'],
            'visible_draft' => [409, 'INVALID_STATE'],
            'deleted_listing' => [404, 'NOT_FOUND'],
            'suspended_caregiver' => [409, 'INVALID_STATE'],
            'deleted_profile' => [409, 'INVALID_STATE'],
            'missing_caregiver_role' => [409, 'INVALID_STATE'],
            'self_booking' => [422, 'VALIDATION_ERROR'],
            'bad_rules' => [409, 'INVALID_STATE'],
            'bad_rule_type' => [409, 'INVALID_STATE'],
            'species_mismatch' => [422, 'VALIDATION_ERROR'],
            'missing_pet_size' => [422, 'VALIDATION_ERROR'],
            'past_start' => [422, 'VALIDATION_ERROR'],
            'backwards' => [422, 'VALIDATION_ERROR'],
            'too_short' => [422, 'VALIDATION_ERROR'],
            'too_long' => [422, 'VALIDATION_ERROR'],
            'crosses_local_day' => [422, 'VALIDATION_ERROR'],
            'no_slot' => [409, 'AVAILABILITY_CONFLICT'],
            'microsecond_outside_slot' => [409, 'AVAILABILITY_CONFLICT'],
        ] as $case => [$status, $code]) {
            $fixture = $this->bookingFixture();
            $payload = $fixture['payload'];
            $listing = DB::table('listings')->where('id', $fixture['active_listing_id']);

            switch ($case) {
                case 'foreign_pet':
                    $payload['pet_id'] = (string) $fixture['foreign_pet']->id;
                    break;
                case 'deleted_pet':
                    $fixture['pet']->delete();
                    break;
                case 'archived_pet':
                    DB::table('pets')->where('id', $fixture['pet']->id)->update(['status' => 'ARCHIVED']);
                    break;
                case 'hidden_draft':
                    $payload['listing_id'] = (string) $fixture['draft_listing_id'];
                    break;
                case 'visible_draft':
                    $payload['listing_id'] = (string) $fixture['draft_listing_id'];
                    DB::table('user_roles')->insert(['user_id' => $fixture['owner']->id, 'role' => 'ADMIN', 'assigned_at' => now()]);
                    break;
                case 'deleted_listing':
                    $listing->update(['deleted_at' => now()]);
                    break;
                case 'suspended_caregiver':
                    DB::table('users')->where('id', $fixture['caregiver']->id)->update(['status' => 'SUSPENDED']);
                    break;
                case 'deleted_profile':
                    DB::table('caregiver_profiles')->where('user_id', $fixture['caregiver']->id)->update(['deleted_at' => now()]);
                    break;
                case 'missing_caregiver_role':
                    DB::table('user_roles')->where('user_id', $fixture['caregiver']->id)->where('role', 'CAREGIVER')->delete();
                    break;
                case 'self_booking':
                    DB::table('user_roles')->insert(['user_id' => $fixture['owner']->id, 'role' => 'CAREGIVER', 'assigned_at' => now()]);
                    DB::table('caregiver_profiles')->where('user_id', $fixture['caregiver']->id)->update(['user_id' => $fixture['owner']->id]);
                    break;
                case 'bad_rules':
                    $listing->update(['acceptance_rules_json' => json_encode(['schema_version' => 2])]);
                    break;
                case 'bad_rule_type':
                    $listing->update(['acceptance_rules_json' => json_encode(['schema_version' => 1, 'species' => [['DOG']], 'sizes' => ['SMALL']])]);
                    break;
                case 'species_mismatch':
                    DB::table('pets')->where('id', $fixture['pet']->id)->update(['species' => 'CAT']);
                    break;
                case 'missing_pet_size':
                    DB::table('pets')->where('id', $fixture['pet']->id)->update(['size' => null]);
                    break;
                case 'past_start':
                    $payload['start_at'] = now()->subHour()->toIso8601String();
                    break;
                case 'backwards':
                    $payload['end_at'] = $fixture['start_at']->subSecond()->toIso8601String();
                    break;
                case 'too_short':
                    $payload['end_at'] = $fixture['start_at']->addSeconds(3599)->toIso8601String();
                    break;
                case 'too_long':
                    $payload['end_at'] = $fixture['start_at']->addSeconds(43201)->toIso8601String();
                    break;
                case 'crosses_local_day':
                    $payload['start_at'] = now('Asia/Ho_Chi_Minh')->addDays(2)->setTime(23, 30)->toIso8601String();
                    $payload['end_at'] = now('Asia/Ho_Chi_Minh')->addDays(3)->setTime(0, 30)->toIso8601String();
                    break;
                case 'no_slot':
                    DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])->delete();
                    break;
                case 'microsecond_outside_slot':
                    DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])->update(['end_at' => $fixture['end_at']->format('Y-m-d H:i:s.u')]);
                    $payload['end_at'] = $fixture['end_at']->addMicrosecond()->format('Y-m-d\TH:i:s.u\Z');
                    break;
            }

            $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
                ->postJson('/api/bookings', $payload)->assertStatus($status)->assertJsonPath('error.code', $code);
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_only_blocking_bookings_conflict_and_adjacent_ranges_are_allowed(): void
    {
        foreach (['REQUESTED', 'AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION', 'COMPLETED', 'CANCELLED', 'ADJACENT'] as $state) {
            $fixture = $this->bookingFixture();
            DB::table('bookings')->insert([
                'owner_id' => $fixture['owner']->id,
                'pet_id' => $fixture['pet']->id,
                'listing_id' => $fixture['active_listing_id'],
                'caregiver_user_id' => $fixture['caregiver']->id,
                'start_at' => ($state === 'ADJACENT' ? $fixture['start_at']->subHours(2) : $fixture['start_at'])->format('Y-m-d H:i:s.u'),
                'end_at' => ($state === 'ADJACENT' ? $fixture['start_at'] : $fixture['end_at'])->format('Y-m-d H:i:s.u'),
                'status' => $state === 'ADJACENT' ? 'CONFIRMED' : $state,
                'price_snapshot_vnd' => 150000,
                'price_unit' => 'HOUR',
                'price_quantity' => 3,
                'pet_snapshot_json' => json_encode($fixture['pet']->snapshotForBooking()),
                'listing_snapshot_json' => '{}',
                'policy_snapshot_json' => '{}',
                'request_idempotency_key' => 'existing-booking',
                'request_payload_hash' => str_repeat('a', 64),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $response = $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
                ->postJson('/api/bookings', $fixture['payload']);
            if (in_array($state, ['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'], true)) {
                $response->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
            } else {
                $response->assertCreated();
            }
        }
    }

    public function test_boarding_duration_bounds(): void
    {
        foreach ([43199 => 422, 43200 => 201, 2592000 => 201, 2592001 => 422] as $seconds => $status) {
            $fixture = $this->bookingFixture();
            DB::table('listings')->where('id', $fixture['active_listing_id'])
                ->update(['service_type' => 'BOARDING', 'price_unit' => 'NIGHT']);
            $end = $fixture['start_at']->addSeconds($seconds);
            DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])
                ->update(['end_at' => $end->addHour()->format('Y-m-d H:i:s.u')]);
            $payload = array_replace($fixture['payload'], ['end_at' => $end->toIso8601String()]);
            $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
                ->postJson('/api/bookings', $payload)->assertStatus($status);
        }
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_quote_needs_no_key_and_ignores_client_price(): void
    {
        $fixture = $this->bookingFixture();
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $fixture['payload'] + [
            'amount' => 1,
            'price_vnd' => 1,
            'owner_id' => $fixture['caregiver']->id,
        ])->assertOk()->assertExactJson([
            'price_total_vnd' => 150000,
            'price_unit' => 'HOUR',
            'quantity' => 3,
            'pricing_version' => 1,
            'cancellation_policy_version' => 1,
        ]);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_status_events', 0);
    }

    public function test_quote_and_create_call_the_same_calculator(): void
    {
        $calculator = \Mockery::mock(CalculateBookingPrice::class)->makePartial();
        $calculator->shouldReceive('calculate')->twice()->passthru();
        $this->app->instance(CalculateBookingPrice::class, $calculator);

        $fixture = $this->bookingFixture();
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertOk()->assertJsonPath('price_total_vnd', 150000);
        $this->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_quote_reflects_current_listing_price(): void
    {
        $fixture = $this->bookingFixture();
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertOk()->assertJsonPath('price_total_vnd', 150000);
        DB::table('listings')->where('id', $fixture['active_listing_id'])->update(['price_vnd' => 70000]);
        $this->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertOk()->assertJsonPath('price_total_vnd', 210000);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_quote_uses_the_same_auth_ownership_and_availability_checks(): void
    {
        $fixture = $this->bookingFixture();
        $this->postJson('/api/bookings/quote', $fixture['payload'])->assertUnauthorized();
        $foreign = array_replace($fixture['payload'], ['pet_id' => (string) $fixture['foreign_pet']->id]);
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $foreign)->assertNotFound();
        DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])->delete();
        $this->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_quote_rounds_up_an_extra_microsecond(): void
    {
        $fixture = $this->bookingFixture();
        $payload = array_replace($fixture['payload'], [
            'end_at' => $fixture['start_at']->addHour()->addMicrosecond()->format('Y-m-d\TH:i:s.u\Z'),
        ]);
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $payload)
            ->assertOk()->assertJsonPath('quantity', 2)->assertJsonPath('price_total_vnd', 100000);
    }

    public function test_boarding_quote_uses_blocks_of_24_hours(): void
    {
        $fixture = $this->bookingFixture();
        DB::table('listings')->where('id', $fixture['active_listing_id'])
            ->update(['service_type' => 'BOARDING', 'price_unit' => 'NIGHT', 'price_vnd' => 200000]);
        $end = $fixture['start_at']->addHours(25);
        DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])
            ->update(['end_at' => $end->addHour()->format('Y-m-d H:i:s.u')]);
        $payload = array_replace($fixture['payload'], ['end_at' => $end->toIso8601String()]);
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $payload)
            ->assertOk()->assertJsonPath('quantity', 2)->assertJsonPath('price_unit', 'NIGHT')
            ->assertJsonPath('price_total_vnd', 400000);
    }

    public function test_quote_and_create_both_reject_price_overflow(): void
    {
        $fixture = $this->bookingFixture();
        DB::table('listings')->where('id', $fixture['active_listing_id'])->update(['price_vnd' => PHP_INT_MAX]);
        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_created_booking_has_versioned_snapshots_and_utc_precision(): void
    {
        $fixture = $this->bookingFixture();
        $start = $fixture['start_at']->addMicroseconds(123456);
        $end = $fixture['end_at']->addMicroseconds(654321);
        $payload = array_replace($fixture['payload'], [
            'start_at' => $start->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:s.uP'),
            'end_at' => $end->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:s.uP'),
            'instructions' => "  Cafe\u{0301} / feed at 09:00  ",
        ]);

        $response = $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $payload)->assertCreated();
        $booking = Booking::query()->findOrFail($response->json('id'));

        $response->assertExactJson([
            'id' => (string) $booking->id,
            'status' => 'REQUESTED',
            'pet_id' => (string) $fixture['pet']->id,
            'listing_id' => (string) $fixture['active_listing_id'],
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $end->format('Y-m-d\TH:i:s.u\Z'),
            'price_snapshot_vnd' => 150000,
            'payment_due_at' => null,
        ]);
        $this->assertInstanceOf(CarbonImmutable::class, $booking->start_at);
        $this->assertSame($start->format('Y-m-d H:i:s.u'), $booking->start_at->format('Y-m-d H:i:s.u'));
        $this->assertSame($end->format('Y-m-d H:i:s.u'), $booking->end_at->format('Y-m-d H:i:s.u'));
        $this->assertSame(3, $booking->price_quantity);
        $this->assertSame('HOUR', $booking->price_unit);
        $this->assertSame(1, $booking->pricing_version);
        $this->assertSame(1, $booking->cancellation_policy_version);
        $this->assertEquals($fixture['pet']->snapshotForBooking(), $booking->pet_snapshot_json);
        $this->assertEquals([
            'schema_version' => 1,
            'listing_id' => (string) $fixture['active_listing_id'],
            'listing_version' => 1,
            'title' => 'Test daycare',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'unit_price_vnd' => 50000,
            'price_unit' => 'HOUR',
            'acceptance_rules' => ['schema_version' => 1, 'species' => ['DOG'], 'sizes' => ['SMALL']],
        ], $booking->listing_snapshot_json);
        $this->assertEquals([
            'schema_version' => 1,
            'cancellation_policy_version' => 1,
            'cancellation_fee_vnd' => 0,
            'payment_mode' => 'SANDBOX',
        ], $booking->policy_snapshot_json);
        $this->assertSame('Café / feed at 09:00', $booking->instructions_snapshot);
        $this->assertNull($booking->current_payment_attempt_id);
        $this->assertSame($fixture['idempotency_key'], $booking->request_idempotency_key);
        $this->assertSame(hash('sha256', json_encode([
            'pet_id' => (string) $fixture['pet']->id,
            'listing_id' => (string) $fixture['active_listing_id'],
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $end->format('Y-m-d\TH:i:s.u\Z'),
            'instructions' => 'Café / feed at 09:00',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), $booking->request_payload_hash);
        $this->assertDatabaseCount('booking_status_events', 1);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => 'REQUESTED',
            'source' => 'USER',
            'actor_user_id' => $fixture['owner']->id,
        ]);

        $fixture['pet']->update(['diet_notes' => 'No fish']);
        DB::table('listings')->where('id', $fixture['active_listing_id'])->update(['price_vnd' => 70000, 'version' => 2]);
        $booking->refresh();
        $this->assertSame('No chicken', $booking->pet_snapshot_json['diet_notes']);
        $this->assertSame(50000, $booking->listing_snapshot_json['unit_price_vnd']);
        $this->assertSame(1, $booking->listing_snapshot_json['listing_version']);
        $this->assertSame(150000, $booking->price_snapshot_vnd);
        $this->assertSame('Café / feed at 09:00', $booking->instructions_snapshot);
    }

    public function test_booking_rolls_back_when_status_event_write_fails(): void
    {
        $fixture = $this->bookingFixture();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `booking_status_events`')) {
                throw new \RuntimeException('Simulated status-event failure');
            }
        });

        try {
            $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
                ->postJson('/api/bookings', $fixture['payload']);
            $this->fail('Expected the event write to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated status-event failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_status_events', 0);
    }

    public function test_task4_duplicate_key_returns_a_controlled_conflict(): void
    {
        $fixture = $this->bookingFixture();
        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $this->postJson('/api/bookings', $fixture['payload'])->assertConflict()
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    /** @return array<string, mixed> */
    private function bookingFixture(): array
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $caregiver = User::factory()->create();

        foreach ([
            [$owner, 'OWNER'],
            [$otherOwner, 'OWNER'],
            [$caregiver, 'OWNER'],
            [$caregiver, 'CAREGIVER'],
        ] as [$user, $role]) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role' => $role,
                'assigned_at' => now(),
            ]);
        }

        $pet = $owner->pets()->create([
            'name' => 'Mochi',
            'species' => 'DOG',
            'size' => 'SMALL',
            'diet_notes' => 'No chicken',
        ])->refresh();

        $foreignPet = $otherOwner->pets()->create([
            'name' => 'Luna',
            'species' => 'DOG',
            'size' => 'SMALL',
        ])->refresh();

        $profileId = DB::table('caregiver_profiles')->insertGetId([
            'user_id' => $caregiver->id,
            'type' => 'INDIVIDUAL',
            'display_name' => 'Test Pet Care',
            'area_code' => 'DN_CENTER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // P0 contract version 1, agreed on 02/11/2026.
        $rules = [
            'schema_version' => 1,
            'species' => ['DOG'],
            'sizes' => ['SMALL'],
        ];

        $listingData = [
            'caregiver_profile_id' => $profileId,
            'title' => 'Test daycare',
            'description' => 'Booking test fixture',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 50000,
            'price_unit' => 'HOUR',
            'capacity' => 1,
            'acceptance_rules_json' => json_encode($rules, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $activeListingId = DB::table('listings')->insertGetId([
            ...$listingData,
            'status' => 'ACTIVE',
            'published_at' => now(),
        ]);

        $draftListingId = DB::table('listings')->insertGetId([
            ...$listingData,
            'status' => 'DRAFT',
            'published_at' => null,
        ]);

        $start = now('Asia/Ho_Chi_Minh')->addDays(2)->setTime(8, 0)->utc();
        $end = $start->copy()->addMinutes(130);

        // Give both listings valid slots so the negative case isolates status.
        foreach ([$activeListingId, $draftListingId] as $listingId) {
            DB::table('availability_slots')->insert([
                'listing_id' => $listingId,
                'start_at' => $start->copy()->subHour(),
                'end_at' => $end->copy()->addHour(),
                'created_by_user_id' => $caregiver->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'owner' => $owner,
            'caregiver' => $caregiver,
            'pet' => $pet,
            'foreign_pet' => $foreignPet,
            'active_listing_id' => $activeListingId,
            'draft_listing_id' => $draftListingId,
            'start_at' => $start,
            'end_at' => $end,
            'idempotency_key' => 'booking-test-001',
            'payload' => [
                'pet_id' => (string) $pet->id,
                'listing_id' => (string) $activeListingId,
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'instructions' => 'Feed at 09:00 Vietnam time',
            ],
        ];
    }
}
