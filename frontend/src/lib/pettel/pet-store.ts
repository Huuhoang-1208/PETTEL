"use client";

import { useEffect, useMemo, useSyncExternalStore } from "react";
import { initializeDemoPets } from "./demo-data";

export type Pet = {
  id: string;
  name: string;
  species: string;
  sex: string;
  age: number;
  diet: string;
  vaccination: string;
  personality: string[];
  specialNote: string;
};

const key = "pettel_pets";
const changeEvent = "pettel:pets-changed";

function subscribe(callback: () => void) {
  window.addEventListener("storage", callback);
  window.addEventListener(changeEvent, callback);
  return () => {
    window.removeEventListener("storage", callback);
    window.removeEventListener(changeEvent, callback);
  };
}

function snapshot() {
  return localStorage.getItem(key);
}

export function usePets() {
  const stored = useSyncExternalStore(subscribe, snapshot, () => null);
  useEffect(() => {
    initializeDemoPets();
    window.dispatchEvent(new Event(changeEvent));
  }, []);

  const pets = useMemo<Pet[]>(() => {
    try {
      const value: unknown = JSON.parse(stored ?? "[]");
      return Array.isArray(value) ? value : [];
    } catch {
      return [];
    }
  }, [stored]);

  function savePets(value: Pet[]) {
    localStorage.setItem(key, JSON.stringify(value));
    window.dispatchEvent(new Event(changeEvent));
  }

  return { pets, savePets };
}
