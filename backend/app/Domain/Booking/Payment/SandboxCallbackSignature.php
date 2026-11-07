<?php

namespace App\Domain\Booking\Payment;

use Illuminate\Support\Facades\Config;

class SandboxCallbackSignature
{
    /**
     * @param  array{event_id: string, booking_id: string, attempt_reference: string, amount_vnd: int, result: string}  $payload
     */
    public function sign(array $payload): string
    {
        $secret = Config::get('services.payment_sandbox.secret');
        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException('Payment sandbox secret is not configured.');
        }

        return hash_hmac('sha256', $this->canonical($payload), $secret);
    }

    /**
     * @param  array{event_id: string, booking_id: string, attempt_reference: string, amount_vnd: int, result: string}  $payload
     */
    public function verify(array $payload, string $signature): bool
    {
        try {
            return hash_equals($this->sign($payload), $signature);
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * @param  array{event_id: string, booking_id: string, attempt_reference: string, amount_vnd: int, result: string}  $payload
     */
    public function hash(array $payload): string
    {
        return hash('sha256', $this->canonical($payload));
    }

    /**
     * @param  array{event_id: string, booking_id: string, attempt_reference: string, amount_vnd: int, result: string}  $payload
     *
     * @throws \JsonException
     */
    private function canonical(array $payload): string
    {
        return json_encode([
            'event_id' => $payload['event_id'],
            'booking_id' => $payload['booking_id'],
            'attempt_reference' => $payload['attempt_reference'],
            'amount_vnd' => $payload['amount_vnd'],
            'result' => $payload['result'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
