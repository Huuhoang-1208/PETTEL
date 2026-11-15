import type { CSSProperties } from "react";

export type PettelIconName =
  | "paw"
  | "pin"
  | "home"
  | "calendar"
  | "search"
  | "shield"
  | "camera"
  | "heart"
  | "star"
  | "globe"
  | "bell"
  | "user"
  | "chevron"
  | "plus"
  | "menu"
  | "arrow"
  | "check"
  | "walk"
  | "clock"
  | "mail"
  | "key"
  | "eye"
  | "eye-off"
  | "award"
  | "sliders"
  | "dog"
  | "cat"
  | "share";

export function PettelIcon({
  name,
  className,
  style,
}: {
  name: PettelIconName;
  className?: string;
  style?: CSSProperties;
}) {
  const paths: Record<Exclude<PettelIconName, "paw">, React.ReactNode> = {
    mail: (
      <>
        <rect x="3" y="5" width="18" height="14" rx="2" />
        <path d="m3 6 9 7 9-7" />
      </>
    ),
    key: (
      <>
        <circle cx="7" cy="12" r="4" />
        <path d="M11 12h10m-4 0v3m-3-3v2" />
      </>
    ),
    eye: (
      <>
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
        <circle cx="12" cy="12" r="3" />
      </>
    ),
    "eye-off": (
      <>
        <path d="m3 3 18 18M10 5.2c.6-.1 1.3-.2 2-.2 6.5 0 10 7 10 7a19 19 0 0 1-3 4M6 6.5A20 20 0 0 0 2 12s3.5 7 10 7c1.7 0 3.2-.5 4.5-1.2M10 10a3 3 0 0 0 4 4" />
      </>
    ),
    award: (
      <>
        <circle cx="12" cy="9" r="6.5" />
        <path d="m8.5 14.5-1 7 4.5-2.5 4.5 2.5-1-7M12 5.5l1.1 2.2 2.4.4-1.8 1.7.4 2.4-2.1-1.1-2.1 1.1.4-2.4-1.8-1.7 2.4-.4L12 5.5Z" />
      </>
    ),
    sliders: (
      <>
        <path d="M4 6h16M4 12h16M4 18h16" />
        <circle cx="8" cy="6" r="2" fill="currentColor" />
        <circle cx="15" cy="12" r="2" fill="currentColor" />
        <circle cx="10" cy="18" r="2" fill="currentColor" />
      </>
    ),
    dog: (
      <>
        <path d="M7 7 4 4 3 11c-.4 5 2.8 9 9 9s9.4-4 9-9l-1-7-3 3a8 8 0 0 0-10 0Z" />
        <path d="M8 13h.01M16 13h.01M9.5 16c1.5 1 3.5 1 5 0" />
      </>
    ),
    cat: (
      <>
        <path d="M5 9 4 3l5 3a9 9 0 0 1 6 0l5-3-1 6a8 8 0 1 1-14 0Z" />
        <path d="M8.5 12h.01m7 0h.01M10 15c1.2 1 2.8 1 4 0m-4.5 0L7 16m7.5-1L17 16" />
      </>
    ),
    share: (
      <>
        <circle cx="18" cy="5" r="3" />
        <circle cx="6" cy="12" r="3" />
        <circle cx="18" cy="19" r="3" />
        <path d="m8.6 10.5 6.8-4m-6.8 7 6.8 4" />
      </>
    ),
    pin: (
      <>
        <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z" />
        <circle cx="12" cy="10" r="2.5" />
      </>
    ),
    home: (
      <>
        <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7" />
        <path d="M10 8h4" />
      </>
    ),
    calendar: (
      <>
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M7 3v4m10-4v4M3 11h18M7 15h2m3 0h2m3 0h1m-11 3h2m3 0h2" />
      </>
    ),
    search: (
      <>
        <circle cx="10.8" cy="10.8" r="6.8" />
        <path d="m16 16 5 5" />
      </>
    ),
    shield: (
      <>
        <path d="M12 2 3.5 5.5v6C3.5 17 12 22 12 22s8.5-5 8.5-10.5v-6L12 2Z" />
        <path d="m8 12 3 3 5-6" />
      </>
    ),
    camera: (
      <>
        <path d="M8 5 6 8H3v12h18V8h-3l-2-3H8Z" />
        <circle cx="12" cy="13" r="3" />
      </>
    ),
    heart: (
      <path d="M20.8 4.6a5.4 5.4 0 0 0-7.6 0L12 5.8l-1.2-1.2a5.4 5.4 0 0 0-7.6 7.6L12 21l8.8-8.8a5.4 5.4 0 0 0 0-7.6Z" />
    ),
    star: <path d="m12 2 3 6.4 7 .9-5.1 5 1.3 7-6.2-3.4L5.8 21l1.3-6.7-5.1-5 7-.9L12 2Z" />,
    globe: (
      <>
        <circle cx="12" cy="12" r="9" />
        <ellipse cx="12" cy="12" rx="4" ry="9" />
        <path d="M3 12h18M5 6.5h14M5 17.5h14" />
      </>
    ),
    bell: (
      <>
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
      </>
    ),
    user: (
      <>
        <circle cx="12" cy="8" r="3.5" />
        <path d="M4.5 21v-2a7.5 7.5 0 0 1 15 0v2h-15Z" />
      </>
    ),
    chevron: <path d="m6 9 6 6 6-6" />,
    plus: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M8 12h8m-4-4v8" />
      </>
    ),
    menu: <path d="M4 6h16M4 12h16M4 18h16" />,
    arrow: <path d="M4 12h16m-6-6 6 6-6 6" />,
    check: <path d="m5 12 4 4L19 6" />,
    walk: (
      <>
        <circle cx="14" cy="3.5" r="1.5" />
        <path d="m7 10 3-3 4 1 2 4 4 1M12 8l-2 6 4 2 1 6M10 14l-2 8M6 6l3 1" />
      </>
    ),
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 6v6l4 3" />
      </>
    ),
  };

  return (
    <svg
      className={className}
      style={style}
      viewBox="0 0 24 24"
      fill={name === "paw" ? "currentColor" : "none"}
      stroke={name === "paw" ? "none" : "currentColor"}
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      {name === "paw" ? (
        <>
          <ellipse cx="5.2" cy="9" rx="2.4" ry="3.1" transform="rotate(-25 5.2 9)" />
          <ellipse cx="9.6" cy="4.9" rx="2.2" ry="3" transform="rotate(-10 9.6 4.9)" />
          <ellipse cx="14.5" cy="5" rx="2.2" ry="3" transform="rotate(12 14.5 5)" />
          <ellipse cx="18.9" cy="9.2" rx="2.4" ry="3.1" transform="rotate(25 18.9 9.2)" />
          <path d="M12 10c-2.5 0-3.2 2.3-5.1 4.1C3.4 17.2 5.4 22 9 20.8a9.7 9.7 0 0 1 6 0c3.7 1.2 5.6-3.6 2.1-6.7C15.2 12.3 14.5 10 12 10Z" />
        </>
      ) : (
        paths[name]
      )}
    </svg>
  );
}
