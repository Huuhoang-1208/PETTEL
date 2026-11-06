<?php

namespace App\Domain\Booking\Payment;

final readonly class PaymentCancellationResult
{
    private function __construct(
        public string $outcome,
        public ?string $errorCode = null,
    ) {}

    public static function succeeded(): self
    {
        return new self('SUCCEEDED');
    }

    public static function retryable(string $errorCode): self
    {
        return new self('RETRYABLE', $errorCode);
    }

    public static function rejected(string $errorCode): self
    {
        return new self('REJECTED', $errorCode);
    }
}
