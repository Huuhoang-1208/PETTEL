"use client";

export function SelectFilter({
  name,
  label,
  choices,
  value,
}: {
  name: string;
  label: string;
  choices: Record<string, string>;
  value?: string | null;
}) {
  return (
    <label>
      {label}
      <select name={name} defaultValue={value ?? ""}>
        <option value="">Tất cả</option>
        {Object.entries(choices).map(([key, text]) => (
          <option key={key} value={key}>
            {text}
          </option>
        ))}
      </select>
    </label>
  );
}
