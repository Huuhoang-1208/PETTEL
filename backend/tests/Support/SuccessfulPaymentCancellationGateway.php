<?php

namespace Tests\Support;

use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Domain\Booking\Payment\PaymentCancellationResult;
use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;

class SuccessfulPaymentCancellationGateway implements PaymentCancellationGateway
{
    /** @var list<array{type: string, attempt_id: int, key: string}> */
    public array $calls = [];

    public function cancel(
        string $operationType,
        PaymentAttempt $attempt,
        BookingCancellationOperation $operation,
    ): PaymentCancellationResult {
        $this->calls[] = [
            'type' => $operationType,
            'attempt_id' => (int) $attempt->id,
            'key' => $operation->gateway_operation_key,
        ];

        return PaymentCancellationResult::succeeded();
    }
}
