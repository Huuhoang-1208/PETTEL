<?php

namespace Tests\Support;

use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\PaymentReleaseResult;
use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;

class SequencePaymentReleaseGateway implements PaymentReleaseGateway
{
    /** @var list<string> */
    public array $operationKeys = [];

    /** @var list<array{attempt_id: string, operation_id: string}> */
    public array $calls = [];

    /** @param list<PaymentReleaseResult> $results */
    public function __construct(private array $results) {}

    public function release(
        PaymentAttempt $attempt,
        BookingReleaseOperation $operation,
    ): PaymentReleaseResult {
        $this->operationKeys[] = $operation->gateway_operation_key;
        $this->calls[] = [
            'attempt_id' => (string) $attempt->id,
            'operation_id' => (string) $operation->id,
        ];

        return array_shift($this->results) ?? PaymentReleaseResult::succeeded();
    }
}
