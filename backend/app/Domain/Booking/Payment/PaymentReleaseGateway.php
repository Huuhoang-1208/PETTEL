<?php

namespace App\Domain\Booking\Payment;

use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;

interface PaymentReleaseGateway
{
    public function release(
        PaymentAttempt $attempt,
        BookingReleaseOperation $operation,
    ): PaymentReleaseResult;
}
