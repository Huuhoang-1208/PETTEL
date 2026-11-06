<?php

namespace App\Domain\Booking\Payment;

use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;

/** Fail-closed default until BE-HA05 binds its sandbox adapter. */
class UnavailablePaymentCancellationGateway implements PaymentCancellationGateway
{
    public function cancel(
        string $operationType,
        PaymentAttempt $attempt,
        BookingCancellationOperation $operation,
    ): PaymentCancellationResult {
        return PaymentCancellationResult::retryable('PAYMENT_GATEWAY_NOT_CONFIGURED');
    }
}
