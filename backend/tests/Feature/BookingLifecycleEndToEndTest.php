<?php

namespace Tests\Feature;

use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\PaymentReleaseResult;
use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\Support\SequencePaymentReleaseGateway;
use Tests\TestCase;

class BookingLifecycleEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_reaches_completed_after_release_retry(): void
    {
        $fixture = BookingFixture::make();

        // 1. Owner tạo booking qua API.
        $created = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'])
            ->assertCreated()
            ->assertJsonPath('status', 'REQUESTED');

        $booking = Booking::query()->findOrFail($created->json('id'));

        // 2. Caregiver chấp nhận booking.
        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$booking->id}/accept")
            ->assertOk()
            ->assertJsonPath('status', 'AWAITING_PAYMENT');

        // 3. Owner tạo payment attempt và mô phỏng thanh toán thành công.
        $attemptResponse = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'e2e-payment-'.$booking->id)
            ->postJson("/api/bookings/{$booking->id}/sandbox-payments")
            ->assertCreated();

        $attempt = PaymentAttempt::query()->findOrFail(
            $attemptResponse->json('data.id'),
        );

        $this->postJson(
            "/api/bookings/{$booking->id}/sandbox-payments/{$attempt->id}/simulate",
            ['result' => 'SUCCESS'],
        )
            ->assertOk()
            ->assertJsonPath('data.attempt_status', 'AUTHORIZED')
            ->assertJsonPath('data.booking_status', 'CONFIRMED');

        // 4. Caregiver bắt đầu và yêu cầu xác nhận hoàn tất.
        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$booking->id}/start")
            ->assertOk()
            ->assertJsonPath('status', 'IN_PROGRESS');

        $this->postJson("/api/bookings/{$booking->id}/request-completion")
            ->assertOk()
            ->assertJsonPath('status', 'AWAITING_CONFIRMATION');

        // 5. Owner xác nhận. Booking chờ tác vụ release xử lý.
        $confirmation = $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$booking->id}/confirm-completion")
            ->assertAccepted()
            ->assertJsonPath('booking.status', 'AWAITING_CONFIRMATION')
            ->assertJsonPath('release.status', 'PENDING');

        $operation = BookingReleaseOperation::query()
            ->findOrFail($confirmation->json('release.id'));
        $originalOperationKey = $operation->gateway_operation_key;

        // 6. Lần chạy đầu bị timeout: booking vẫn chờ, payment chưa release.
        $gateway = new SequencePaymentReleaseGateway([
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::succeeded(),
        ]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $this->artisan('bookings:process-releases --batch=100')
            ->assertExitCode(0);

        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertSame('PENDING', $operation->fresh()->status);
        $this->assertSame('AUTHORIZED', $attempt->fresh()->status);
        $this->assertSame(1, $operation->fresh()->attempt_count);

        // 7. Sau thời gian retry, command xử lý lại cùng operation và hoàn tất.
        $this->travel(1)->minutes();

        $this->artisan('bookings:process-releases --batch=100')
            ->assertExitCode(0);

        $this->assertSame('COMPLETED', $booking->fresh()->status);
        $this->assertSame('COMPLETED', $operation->fresh()->status);
        $this->assertSame('RELEASED', $attempt->fresh()->status);
        $this->assertSame(2, $operation->fresh()->attempt_count);

        // Retry phải dùng lại cùng gateway operation key.
        $this->assertCount(2, $gateway->operationKeys);
        $this->assertSame(
            [$originalOperationKey, $originalOperationKey],
            $gateway->operationKeys,
        );

        // Kiểm tra lịch sử nghiệp vụ từ đầu đến cuối.
        $history = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)
            ->orderBy('id')
            ->get(['from_status', 'to_status'])
            ->map(fn ($event) => [$event->from_status, $event->to_status])
            ->all();

        $this->assertSame([
            [null, 'REQUESTED'],
            ['REQUESTED', 'AWAITING_PAYMENT'],
            ['AWAITING_PAYMENT', 'CONFIRMED'],
            ['CONFIRMED', 'IN_PROGRESS'],
            ['IN_PROGRESS', 'AWAITING_CONFIRMATION'],
            ['AWAITING_CONFIRMATION', 'COMPLETED'],
        ], $history);

        // Chỉ ghi một payment event release dù gateway được gọi lại.
        $this->assertDatabaseCount('payment_events', 2);
        $this->assertDatabaseHas('payment_events', [
            'payment_attempt_id' => $attempt->id,
            'event_type' => 'RELEASED',
        ]);
    }
}
