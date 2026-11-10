<?php

namespace App\Domain\Booking\Payment;

use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;

class SandboxPaymentCancellationGateway implements PaymentCancellationGateway
{
    public function cancel(
        string $operationType,
        PaymentAttempt $attempt,
        BookingCancellationOperation $operation,
    ): PaymentCancellationResult {
        if (
            $operationType !== $operation->operation_type
            || (string) $attempt->id !== (string) $operation->payment_attempt_id
            || (string) $attempt->booking_id !== (string) $operation->booking_id
        ) {
            return PaymentCancellationResult::rejected('PAYMENT_STATE_MISMATCH');
        }

        return match ($operationType) {
            'VOID' => in_array($attempt->status, ['PENDING', 'VOIDED'], true)
                ? PaymentCancellationResult::succeeded()
                : PaymentCancellationResult::rejected('PAYMENT_STATE_MISMATCH'),

            'REFUND' => in_array($attempt->status, ['AUTHORIZED', 'REFUNDED'], true)
                ? PaymentCancellationResult::succeeded()
                : PaymentCancellationResult::rejected('PAYMENT_STATE_MISMATCH'),

            default => PaymentCancellationResult::rejected('PAYMENT_STATE_MISMATCH'),
        };
    }
}
