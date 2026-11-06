<?php

namespace App\Domain\Booking\Payment;

use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;

interface PaymentCancellationGateway
{
    public function cancel(
        string $operationType,
        PaymentAttempt $attempt,
        BookingCancellationOperation $operation,
    ): PaymentCancellationResult;
}
