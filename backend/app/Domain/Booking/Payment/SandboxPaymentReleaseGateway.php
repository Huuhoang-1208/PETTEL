<?php

namespace App\Domain\Booking\Payment;

use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;

class SandboxPaymentReleaseGateway implements PaymentReleaseGateway
{
    public function release(
        PaymentAttempt $attempt,
        BookingReleaseOperation $operation,
    ): PaymentReleaseResult {
        if ((string) $operation->payment_attempt_id !== (string) $attempt->id) {
            return PaymentReleaseResult::rejected('PAYMENT_STATE_MISMATCH');
        }

        if (! in_array($attempt->status, ['AUTHORIZED', 'RELEASED'], true)) {
            return PaymentReleaseResult::rejected('PAYMENT_STATE_MISMATCH');
        }

        // This is a local sandbox: the durable release effect is committed
        // by the processor using the operation's stable gateway key.
        return PaymentReleaseResult::succeeded();
    }
}
