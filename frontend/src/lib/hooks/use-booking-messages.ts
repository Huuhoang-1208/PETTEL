"use client";

import { api } from "@/lib/api/client";
import type { ChannelAuthorizationCallback } from "pusher-js";
import { useEffect, useState } from "react";

type ChannelAuthorizationResponse = NonNullable<
  Parameters<ChannelAuthorizationCallback>[1]
>;

type BookingMessage = {
  id: string;
  sender_id: string;
  body: string | null;
  created_at: string;
};

export function useBookingMessages(bookingId: string) {
  const [messages, setMessages] = useState<BookingMessage[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    let requestVersion = 0;
    let disconnect = () => {};

    const reportError = () => {
      if (active) {
        setError("Không kết nối được chat. Kiểm tra phiên đăng nhập và Reverb.");
      }
    };

    async function refreshMessages() {
      const version = ++requestVersion;

      try {
        const { data } = await api.get<{ data: BookingMessage[] }>(
          `/api/bookings/${bookingId}/messages`,
        );

        if (active && version === requestVersion) {
          setMessages([...data.data].reverse());
          setError(null);
        }
      } catch {
        reportError();
      }
    }

    async function connect() {
      const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import("laravel-echo"),
        import("pusher-js"),
      ]);

      if (!active) return;

      const echo = new Echo({
        broadcaster: "reverb",
        Pusher,
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? "localhost",
        wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8081),
        forceTLS: false,
        enabledTransports: ["ws"],

        authorizer: (channel: { name: string }) => ({
          authorize: (
            socketId: string,
            callback: ChannelAuthorizationCallback,
          ) => {
            api.post<ChannelAuthorizationResponse>("/api/broadcasting/auth", {
              socket_id: socketId,
              channel_name: channel.name,
            })
              .then(({ data }) => callback(null, data))
              .catch((error: Error) => callback(error, null));
          },
        }),
      });

      disconnect = () => echo.disconnect();

      echo.private(`bookings.${bookingId}`)
        .subscribed(() => void refreshMessages())
        .listen(".booking.message.sent", () => void refreshMessages())
        .error(reportError);
    }

    void connect().catch(reportError);

    return () => {
      active = false;
      disconnect();
    };
  }, [bookingId]);

  return { messages, error };
}
