"use client";

import { useBookingMessages } from "@/features/chat/hooks/use-booking-messages";

export function BookingChat({ bookingId }: { bookingId: string }) {
  const { messages, error } = useBookingMessages(bookingId);

  return (
    <section>
      {error && <p role="alert">{error}</p>}

      <ul>
        {messages.map((message) => (
          <li key={message.id}>
            <strong>{message.sender_id}: </strong>
            {message.body ?? "Tin nhắn có ảnh"}
          </li>
        ))}
      </ul>
    </section>
  );
}
