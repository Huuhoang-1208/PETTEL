"use client";

import { api } from "@/lib/api/client";
import { errorText } from "@/lib/api/errors";
import { type Message, type Page } from "@/lib/api/types";
import type { ChannelAuthorizationCallback } from "pusher-js";
import { useEffect, useRef, useState } from "react";

type ChannelAuthorizationResponse = NonNullable<Parameters<ChannelAuthorizationCallback>[1]>;

export function useBookingMessages(bookingId: string, kind: "bookings" | "inquiries" = "bookings") {
  const [messages, setMessages] = useState<Message[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [nextPage, setNextPage] = useState<string | null>(null);
  const refreshRef = useRef<() => Promise<void>>(async () => {});

  useEffect(() => {
    let active = true;
    let requestVersion = 0;
    let disconnect = () => {};
    let realtimeConnected = false;

    const reportError = (failure: unknown) => {
      if (active) {
        setError(errorText(failure));
      }
    };

    async function refreshMessages() {
      const version = ++requestVersion;

      try {
        const { data } = await api.get<Page<Message>>(
          `/api/${kind}/${bookingId}/messages?per_page=50`,
        );

        if (active && version === requestVersion) {
          setMessages([...data.data].reverse());
          setNextPage(data.links?.next ?? null);
          setError(null);
        }
      } catch (failure) {
        reportError(failure);
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
          authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
            api
              .post<ChannelAuthorizationResponse>("/api/broadcasting/auth", {
                socket_id: socketId,
                channel_name: channel.name,
              })
              .then(({ data }) => callback(null, data))
              .catch((error: Error) => callback(error, null));
          },
        }),
      });

      disconnect = () => echo.disconnect();
      const connection = (echo.connector as unknown as {
        pusher: { connection: { bind: (event: string, callback: () => void) => void } };
      }).pusher.connection;
      connection.bind("connected", () => {
        realtimeConnected = true;
        void refreshMessages();
      });
      connection.bind("disconnected", () => { realtimeConnected = false; });
      connection.bind("unavailable", () => { realtimeConnected = false; });

      echo
        .private(`${kind}.${bookingId}`)
        .subscribed(() => {
          realtimeConnected = true;
          void refreshMessages();
        })
        .listen(kind === "bookings" ? ".booking.message.sent" : ".inquiry.message.sent", () => void refreshMessages())
        .error(() => {
          realtimeConnected = false;
          void refreshMessages();
        });
    }

    refreshRef.current = refreshMessages;
    void refreshMessages();
    // Poll only as a fallback; a healthy Reverb subscription delivers new messages immediately.
    const timer = window.setInterval(() => {
      if (!document.hidden && !realtimeConnected) void refreshMessages();
    }, 5000);
    if (process.env.NEXT_PUBLIC_REVERB_APP_KEY) void connect().catch(() => {});

    return () => {
      active = false;
      window.clearInterval(timer);
      refreshRef.current = async () => {};
      disconnect();
    };
  }, [bookingId, kind]);

  return { messages, error, nextPage, refresh: () => refreshRef.current() };
}
