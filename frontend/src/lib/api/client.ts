import axios from "axios";

export const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080",
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

export async function login(email: string, password: string) {
  await api.get("/sanctum/csrf-cookie");
  await api.post("/login", { email, password });
}
