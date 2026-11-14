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
  | "clock";

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
