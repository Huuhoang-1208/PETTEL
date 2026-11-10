<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessCancellationOutbox;
use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Domain\Booking\Payment\SandboxCallbackSignature;
use App\Models\PaymentAttempt;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\Support\SuccessfulPaymentCancellationGateway;
use Tests\TestCase;

class SandboxPaymentSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payment_sandbox.simulation_enabled', true);
        config()->set('services.payment_sandbox.secret', 'test-sandbox-secret');
    }

    public function test_authorization_after_completed_cancellation_does_not_resurrect_booking(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-after-cancel-1');

        $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'cancel-before-payment-callback')
            ->postJson('/api/bookings/'.$fixture['booking']->id.'/cancel')
            ->assertStatus(202);

        $gateway = new SuccessfulPaymentCancellationGateway;
        $this->app->instance(PaymentCancellationGateway::class, $gateway);

        $stats = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $stats['completed']);
        $this->assertSame('CANCELLED', $fixture['booking']->fresh()->status);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'VOIDED',
        ]);

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($payload),
        )
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.booking_status', 'CANCELLED')
            ->assertJsonPath('data.processing_status', 'PROCESSED');

        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'CANCELLED',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'booking_id' => $fixture['booking']->id,
            'payment_attempt_id' => $attempt->id,
            'reason_code' => 'PAYMENT_LATE_AUTHORIZED',
            'operation_type' => 'REFUND',
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_owner_can_create_attempt_and_simulate_success(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-success-1');

        $this->actingAs($fixture['owner'])
            ->postJson('/api/bookings/'.$fixture['booking']->id.'/sandbox-payments/'.$attempt->id.'/simulate', [
                'result' => 'SUCCESS',
            ])
            ->assertOk()
            ->assertJsonPath('data.processing_status', 'PROCESSED')
            ->assertJsonPath('data.attempt_status', 'AUTHORIZED')
            ->assertJsonPath('data.booking_status', 'CONFIRMED');

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $fixture['booking']->id,
            'to_status' => 'CONFIRMED',
            'source' => 'SANDBOX',
        ]);
        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_payment_start_replay_returns_the_existing_attempt_with_http_200(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $url = '/api/bookings/'.$fixture['booking']->id.'/sandbox-payments';
        $key = 'payment-start-replay-1';

        $created = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', $key)
            ->postJson($url)
            ->assertCreated();

        $replayed = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', $key)
            ->postJson($url)
            ->assertOk();

        $this->assertSame($created->json('data'), $replayed->json('data'));
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_failure_can_be_retried_and_timeout_keeps_attempt_pending(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $firstAttempt = $this->startAttempt($fixture, 'payment-failure-1');

        $this->actingAs($fixture['owner'])
            ->postJson('/api/bookings/'.$fixture['booking']->id.'/sandbox-payments/'.$firstAttempt->id.'/simulate', [
                'result' => 'FAILURE',
            ])
            ->assertOk()
            ->assertJsonPath('data.attempt_status', 'FAILED')
            ->assertJsonPath('data.booking_status', 'AWAITING_PAYMENT');

        $secondAttempt = $this->startAttempt($fixture, 'payment-retry-2');
        $this->assertSame(2, (int) $secondAttempt->attempt_no);

        $this->actingAs($fixture['owner'])
            ->postJson('/api/bookings/'.$fixture['booking']->id.'/sandbox-payments/'.$secondAttempt->id.'/simulate', [
                'result' => 'TIMEOUT',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.result', 'TIMEOUT');

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $secondAttempt->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_callback_requires_signature_and_replays_event_idempotently(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-callback-1');
        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];

        $this->postJson('/api/sandbox/payment-callback', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CALLBACK_SIGNATURE');

        $signature = app(SandboxCallbackSignature::class)->sign($payload);
        $this->withHeader('X-Payment-Signature', $signature)
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.replayed', false)
            ->assertJsonPath('data.booking_status', 'CONFIRMED');

        $this->withHeader('X-Payment-Signature', $signature)
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.replayed', true);

        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_authorization_during_pending_void_queues_refund_and_parks_void_for_review(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-pending-void-race');

        $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'cancel-pending-void-race')
            ->postJson('/api/bookings/'.$fixture['booking']->id.'/cancel')
            ->assertStatus(202);

        $voidOperation = DB::table('booking_cancellation_operations')
            ->where('booking_id', $fixture['booking']->id)
            ->where('operation_type', 'VOID')
            ->first();
        $this->assertNotNull($voidOperation);
        $this->assertSame('PENDING', $voidOperation->status);

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($payload),
        )
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.booking_status', 'AWAITING_PAYMENT')
            ->assertJsonPath('data.processing_status', 'PROCESSED');

        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'AWAITING_PAYMENT',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'id' => $voidOperation->id,
            'status' => 'MANUAL_REVIEW',
            'last_error_code' => 'PAYMENT_CALLBACK_RACE',
        ]);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'booking_id' => $fixture['booking']->id,
            'payment_attempt_id' => $attempt->id,
            'reason_code' => 'PAYMENT_LATE_AUTHORIZED',
            'operation_type' => 'REFUND',
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseMissing('booking_status_events', [
            'booking_id' => $fixture['booking']->id,
            'to_status' => 'CONFIRMED',
        ]);
    }

    public function test_late_authorization_queues_refund_without_confirming_booking(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $fixture['booking']->payment_due_at = now('UTC')->subMinute();
        $fixture['booking']->save();
        $attempt = $this->startAttemptAllowExpiredDeadline($fixture, 'payment-late-1');
        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];

        $this->withHeader('X-Payment-Signature', app(SandboxCallbackSignature::class)->sign($payload))
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.booking_status', 'AWAITING_PAYMENT')
            ->assertJsonPath('data.compensation_operation_id', fn ($id) => is_string($id));

        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'AWAITING_PAYMENT',
        ]);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'booking_id' => $fixture['booking']->id,
            'payment_attempt_id' => $attempt->id,
            'reason_code' => 'PAYMENT_LATE_AUTHORIZED',
            'operation_type' => 'REFUND',
            'status' => 'PENDING',
        ]);
    }

    public function test_malformed_callback_payload_is_rejected_before_processing(): void
    {
        $this->postJson('/api/sandbox/payment-callback', [
            'event_id' => 'not-a-uuid',
            'booking_id' => '1',
            'attempt_reference' => 'sandbox-attempt',
            'amount_vnd' => 50000,
            'result' => 'FAILED',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_wrong_booking_reference_is_rejected_without_changing_attempts(): void
    {
        $first = $this->awaitingPaymentFixture();
        $second = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($first, 'payment-wrong-booking-1');

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $second['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'FAILED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($payload),
        )
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.processing_status', 'REJECTED')
            ->assertJsonPath('data.rejection_code', 'PAYMENT_REFERENCE_MISMATCH');

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $second['booking']->id,
            'status' => 'AWAITING_PAYMENT',
        ]);
    }

    public function test_amount_mismatch_is_rejected_without_confirming_booking(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-wrong-amount-1');

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd + 1,
            'result' => 'AUTHORIZED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($payload),
        )
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.processing_status', 'REJECTED')
            ->assertJsonPath('data.rejection_code', 'PAYMENT_AMOUNT_MISMATCH');

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'AWAITING_PAYMENT',
        ]);
    }

    public function test_failed_callback_for_old_attempt_is_rejected(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $oldAttempt = $this->startAttempt($fixture, 'payment-old-attempt-1');

        $this->actingAs($fixture['owner'])
            ->postJson(
                '/api/bookings/'.$fixture['booking']->id.'/sandbox-payments/'.$oldAttempt->id.'/simulate',
                ['result' => 'FAILURE'],
            )
            ->assertOk()
            ->assertJsonPath('data.attempt_status', 'FAILED');

        $currentAttempt = $this->startAttempt($fixture, 'payment-current-attempt-2');

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $oldAttempt->provider_ref,
            'amount_vnd' => (int) $oldAttempt->amount_vnd,
            'result' => 'FAILED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($payload),
        )
            ->postJson('/api/sandbox/payment-callback', $payload)
            ->assertOk()
            ->assertJsonPath('data.processing_status', 'REJECTED')
            ->assertJsonPath('data.rejection_code', 'PAYMENT_STATE_MISMATCH');

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $oldAttempt->id,
            'status' => 'FAILED',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $currentAttempt->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_callback_event_write_failure_rolls_back_payment_and_booking(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-atomic-1');

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];
        $signature = app(SandboxCallbackSignature::class)->sign($payload);

        $this->withoutExceptionHandling();

        DB::listen(static function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `booking_status_events`')) {
                throw new RuntimeException('Simulated callback event failure.');
            }
        });

        try {
            $this->withHeader('X-Payment-Signature', $signature)
                ->postJson('/api/sandbox/payment-callback', $payload);

            $this->fail('Expected callback event write failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated callback event failure.', $exception->getMessage());
        }

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'AWAITING_PAYMENT',
        ]);
        $this->assertDatabaseMissing('booking_status_events', [
            'booking_id' => $fixture['booking']->id,
            'to_status' => 'CONFIRMED',
        ]);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_failure_event_arriving_after_authorization_does_not_reverse_state(): void
    {
        $fixture = $this->awaitingPaymentFixture();
        $attempt = $this->startAttempt($fixture, 'payment-order-1');

        $authorizedPayload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $fixture['booking']->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($authorizedPayload),
        )
            ->postJson('/api/sandbox/payment-callback', $authorizedPayload)
            ->assertOk()
            ->assertJsonPath('data.booking_status', 'CONFIRMED');

        $failedPayload = [
            ...$authorizedPayload,
            'event_id' => (string) Str::uuid(),
            'result' => 'FAILED',
        ];

        $this->withHeader(
            'X-Payment-Signature',
            app(SandboxCallbackSignature::class)->sign($failedPayload),
        )
            ->postJson('/api/sandbox/payment-callback', $failedPayload)
            ->assertOk()
            ->assertJsonPath('data.processing_status', 'REJECTED')
            ->assertJsonPath('data.rejection_code', 'PAYMENT_STATE_MISMATCH');

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'CONFIRMED',
        ]);
    }

    /** @return array<string, mixed> */
    private function awaitingPaymentFixture(): array
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->status = 'AWAITING_PAYMENT';
        $booking->payment_due_at = now('UTC')->addMinutes(15);
        $booking->save();
        $fixture['booking'] = $booking->refresh();

        return $fixture;
    }

    /** @param array<string, mixed> $fixture */
    private function startAttempt(array $fixture, string $idempotencyKey): PaymentAttempt
    {
        $response = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/bookings/'.$fixture['booking']->id.'/sandbox-payments');
        $response->assertCreated();

        return PaymentAttempt::query()->findOrFail($response->json('data.id'));
    }

    /** @param array<string, mixed> $fixture */
    private function startAttemptAllowExpiredDeadline(array $fixture, string $idempotencyKey): PaymentAttempt
    {
        DB::table('bookings')->where('id', $fixture['booking']->id)
            ->update(['payment_due_at' => now('UTC')->addMinute()]);
        $attempt = $this->startAttempt($fixture, $idempotencyKey);
        DB::table('bookings')->where('id', $fixture['booking']->id)
            ->update(['payment_due_at' => now('UTC')->subMinute()]);

        return $attempt;
    }
}
