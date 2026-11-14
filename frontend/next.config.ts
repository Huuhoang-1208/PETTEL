import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
  cacheComponents: true,
  partialPrefetching: true,
  async redirects() {
    return [
      { source: "/index.html", destination: "/services", permanent: true },
      ...["q", "service_type", "area_code", "species", "size", "pet_id", "start_at", "end_at", "min_price_vnd", "max_price_vnd", "page"].map((key) => ({
        source: "/",
        has: [{ type: "query" as const, key }],
        destination: "/services",
        permanent: false,
      })),
      ...["detail", "detail-dotung", "detail-minhnhat", "detail-baotram", "detail-baotram-house", "pet-profile", "chat", "videocall", "livecam"].map((page) => ({
        source: `/${page}.html`,
        destination: `/${page}`,
        permanent: true,
      })),
    ];
  },
};

export default nextConfig;
