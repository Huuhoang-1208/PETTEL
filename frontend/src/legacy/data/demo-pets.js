export function initializeDemoPets() {
  if (localStorage.getItem("pettel_pets") !== null) return;
  const mockPets = [
    {
      id: "min",
      name: "Min",
      species: "cat",
      sex: "male",
      age: 3,
      diet: "2× daily · No fish",
      vaccination: "Up to date · Mar 2026",
      personality: ["shy", "indoor-only"],
      specialNote: "Stress when meeting strangers — needs 30 min warm-up before handling",
    },
    {
      id: "mun",
      name: "Mun",
      species: "cat",
      sex: "male",
      age: 1.5,
      diet: "3× daily · Wet food only",
      vaccination: "Up to date · Jan 2026",
      personality: ["active", "shy"],
      specialNote: "Ăn nhiều, cần kiểm soát khẩu phần",
    },
  ];
  localStorage.setItem("pettel_pets", JSON.stringify(mockPets));
}
