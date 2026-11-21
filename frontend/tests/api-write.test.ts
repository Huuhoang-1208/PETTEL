import { beforeEach, describe, expect, it, vi } from "vitest";
import { api } from "@/lib/api/client";
import { write } from "@/lib/api/request";

vi.mock("@/lib/api/client", () => ({ api: { get: vi.fn(), request: vi.fn() } }));

beforeEach(() => vi.resetAllMocks());

describe("authenticated writes", () => {
  it("obtains CSRF before sending a booking with its idempotency key", async () => {
    const order: string[] = [];
    vi.mocked(api.get).mockImplementation(async () => {
      order.push("csrf");
      return { data: null };
    });
    vi.mocked(api.request).mockImplementation(async () => {
      order.push("write");
      return { data: { id: "42" } };
    });
    await expect(
      write("post", "/api/v1/bookings", { pet_id: "7" }, "booking-123"),
    ).resolves.toEqual({ id: "42" });
    expect(order).toEqual(["csrf", "write"]);
    expect(api.get).toHaveBeenCalledWith("/sanctum/csrf-cookie");
    expect(api.request).toHaveBeenCalledWith(
      expect.objectContaining({
        headers: { "Idempotency-Key": "booking-123" },
        data: { pet_id: "7" },
      }),
    );
  });

  it("does not submit when CSRF initialization fails", async () => {
    const failure = new Error("CSRF unavailable");
    vi.mocked(api.get).mockRejectedValue(failure);
    await expect(write("delete", "/api/v1/pets/7")).rejects.toBe(failure);
    expect(api.request).not.toHaveBeenCalled();
  });

  it("preserves server validation details for the form error display", async () => {
    const failure = { response: { status: 422, data: { errors: { name: ["Required"] } } } };
    vi.mocked(api.get).mockResolvedValue({ data: null });
    vi.mocked(api.request).mockRejectedValue(failure);
    await expect(write("post", "/api/v1/pets", {})).rejects.toBe(failure);
    expect(api.request).toHaveBeenCalledTimes(1);
  });
});
