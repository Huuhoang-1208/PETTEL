"use client";

import { Field } from "@/features/home/types";
import { useApi } from "@/hooks/use-api";
import { type Page, type Pet } from "@/lib/api/types";
import { useSession } from "@/providers/session-provider";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";
export function useHomeSearch() {
  const router = useRouter();
  const { user } = useSession();
  const hasPetsRole = user?.roles?.some((role) => role.role === "OWNER");
  const pets = useApi<Page<Pet>>(hasPetsRole ? "/api/pets" : null);
  const activePets = pets.data?.data.filter((pet) => pet.status === "ACTIVE") ?? [];
  const [area, setArea] = useState("DN_CENTER");
  const [service, setService] = useState("BOARDING");
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  const [petType, setPetType] = useState("DOG");
  const [petSize, setPetSize] = useState("SMALL");
  const [petId, setPetId] = useState("");
  const [open, setOpen] = useState<Field | null>(null);
  const [error, setError] = useState("");
  const form = useRef<HTMLFormElement>(null);
  const selectedPet = activePets.find((pet) => pet.id === petId);
  useEffect(() => {
    if (!open) return;
    const closeOutside = (event: PointerEvent) => {
      if (event.target instanceof Node && !form.current?.contains(event.target)) setOpen(null);
    };
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(null);
    };
    document.addEventListener("pointerdown", closeOutside);
    document.addEventListener("keydown", closeOnEscape);
    return () => {
      document.removeEventListener("pointerdown", closeOutside);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [open]);
  function dateLabel() {
    if (!start || !end) return "Chọn ngày gửi";
    const format = (value: string) => `${value.slice(8, 10)}/${value.slice(5, 7)}`;
    return start.slice(0, 10) === end.slice(0, 10)
      ? `${format(start)}, ${start.slice(11)}–${end.slice(11)}`
      : `${format(start)} → ${format(end)}`;
  }
  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    const params = new URLSearchParams();
    if (area) params.set("area_code", area);
    params.set("service_type", service);
    if (selectedPet) params.set("pet_id", selectedPet.id);
    else {
      params.set("species", petType);
      params.set("size", petSize);
    }
    if (start || end) {
      if (!start || !end) {
        setError("Bạn hãy chọn cả thời gian bắt đầu và kết thúc nhé.");
        setOpen("dates");
        return;
      }
      const startDate = new Date(`${start}:00+07:00`);
      const endDate = new Date(`${end}:00+07:00`);
      if (
        !Number.isFinite(startDate.getTime()) ||
        !Number.isFinite(endDate.getTime()) ||
        startDate <= new Date() ||
        endDate <= startDate
      ) {
        setError("Chọn thời gian bắt đầu trong tương lai và kết thúc sau khi bắt đầu.");
        setOpen("dates");
        return;
      }
      params.set("start_at", startDate.toISOString());
      params.set("end_at", endDate.toISOString());
    }
    router.push(`/services?${params.toString()}`);
  }
  return {
    router,
    user,
    hasPetsRole,
    pets,
    activePets,
    area,
    setArea,
    service,
    setService,
    start,
    setStart,
    end,
    setEnd,
    petType,
    setPetType,
    petSize,
    setPetSize,
    petId,
    setPetId,
    open,
    setOpen,
    error,
    setError,
    form,
    selectedPet,
    dateLabel,
    search,
  };
}
export type HomeSearchState = ReturnType<typeof useHomeSearch>;
