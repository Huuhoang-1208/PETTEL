<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class InquiryMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        private string $inquiryId,
        private string $messageId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('inquiries.'.$this->inquiryId)];
    }

    public function broadcastAs(): string
    {
        return 'inquiry.message.sent';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'inquiry_id' => $this->inquiryId,
            'message_id' => $this->messageId,
        ];
    }
}
