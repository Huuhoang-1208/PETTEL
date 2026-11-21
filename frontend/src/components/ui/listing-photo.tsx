import Image from "next/image";

type ListingPhotoProps = {
  src: string;
  alt: string;
  sizes: string;
};

/** Optimize local catalog images; let the browser request API thumbnails directly. */
export function ListingPhoto({ src, alt, sizes }: ListingPhotoProps) {
  if (src.startsWith("/") && !src.startsWith("//")) {
    return <Image src={src} alt={alt} width={600} height={400} sizes={sizes} loading="lazy" />;
  }

  // API thumbnails are already resized WebP. A server-side image proxy would
  // lose session cookies for protected media and cannot resolve host localhost in Docker.
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} width={600} height={400} loading="lazy" decoding="async" />
  );
}
