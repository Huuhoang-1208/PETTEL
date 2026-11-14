"use client";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { PetSearchField } from "@/features/home/components/pet-search-field";
import { SearchField } from "@/features/home/components/search-field";
import { careServices, locations } from "@/features/home/constants";
import { useHomeSearch } from "@/features/home/hooks/use-home-search";
import styles from "@/features/home/styles/search.module.css";

export function HomeSearch() {
  const {
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

    setPetId,
    open,
    setOpen,
    error,
    setError,
    form,
    selectedPet,
    dateLabel,
    search,
  } = useHomeSearch();
  return (
    <div className={styles.searchArea}>
      <form
        className={styles.searchBar}
        onSubmit={search}
        ref={form}
        aria-label="Tìm người chăm sóc thú cưng"
      >
        <SearchField
          field="area"
          label="Khu vực / địa điểm"
          value={locations[area]}
          icon="pin"
          open={open}
          setOpen={setOpen}
          tone="blue"
        >
          <label>
            Khu vực tìm sitter
            <select
              name="area_code"
              value={area}
              onChange={(event) => {
                setArea(event.target.value);
                setOpen(null);
              }}
            >
              {Object.entries(locations).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
        </SearchField>
        <SearchField
          field="service"
          label="Dịch vụ chăm sóc"
          value={careServices[service]}
          icon="home"
          open={open}
          setOpen={setOpen}
          tone="orange"
        >
          <label>
            Dịch vụ chăm sóc
            <select
              name="service_type"
              value={service}
              onChange={(event) => {
                setService(event.target.value);
                setOpen(null);
              }}
            >
              {Object.entries(careServices).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <p>Chăm ban ngày tính theo giờ; lưu trú qua đêm tính theo đêm.</p>
        </SearchField>
        <SearchField
          field="dates"
          label="Thời gian gửi"
          value={dateLabel()}
          icon="calendar"
          open={open}
          setOpen={setOpen}
          tone="purple"
        >
          <label>
            Bắt đầu (giờ Việt Nam)
            <input
              type="datetime-local"
              name="start_at"
              value={start}
              onInput={(event) => setStart(event.currentTarget.value)}
            />
          </label>
          <label>
            Kết thúc (giờ Việt Nam)
            <input
              type="datetime-local"
              name="end_at"
              value={end}
              onInput={(event) => setEnd(event.currentTarget.value)}
            />
          </label>
          <p>
            {service === "DAYCARE"
              ? "Chăm ban ngày từ 1–12 giờ, trong cùng một ngày."
              : "Lưu trú từ 12 giờ đến 30 ngày."}
          </p>
          <div className={styles.panelActions}>
            <button
              type="button"
              onClick={() => {
                setStart("");
                setEnd("");
                setError("");
                setOpen(null);
              }}
            >
              Bỏ chọn
            </button>
            <button type="button" onClick={() => setOpen(null)}>
              Xong
            </button>
          </div>
        </SearchField>
        <PetSearchField
          open={open}
          setOpen={setOpen}
          selectedPet={selectedPet}
          petType={petType}
          petSize={petSize}
          hasPetsRole={hasPetsRole}
          setPetId={setPetId}
          activePets={activePets}
          pets={pets}
          setPetType={setPetType}
          setPetSize={setPetSize}
          user={user}
        />
        <button type="submit" className={styles.searchButton}>
          <PettelIcon name="search" />
          <span>Tìm Sitter</span>
        </button>
      </form>
      {error && (
        <p className={styles.searchError} role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
