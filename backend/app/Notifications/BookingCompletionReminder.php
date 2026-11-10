<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BookingCompletionReminder extends Notification
{
    public function __construct(
        public readonly string $bookingId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{type: string, booking_id: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'BOOKING_COMPLETION_REMINDER',
            'booking_id' => $this->bookingId,
        ];
    }
}
