<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class BookingMessageSent implements
    ShouldBroadcast,
    ShouldDispatchAfterCommit,
    ShouldRescue
{
    use Dispatchable;

    public function __construct(
        private string $bookingId,
        private string $messageId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bookings.' . $this->bookingId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'message_id' => $this->messageId,
        ];
    }
}