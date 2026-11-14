import { sittersData } from "@/legacy/scripts/demo-data";
/** @param {import("./page-scope").PageScope} scope */
export default function initialize(scope) {
  // ===============================
  // PETTEL - Main JavaScript
  // Filter & Rendering Logic
  // ===============================
  // ===== STATE MANAGEMENT =====
  let currentFilters = {
    petType: [],
    personality: [],
    specialNeeds: [],
    experienceLevel: [],
    diet: [],
    environment: [],
    availability: [],
    trustLevel: [], // New: pro, verified, new
    priceRange: "all", // New: price filter
    district: "all", // New: district filter
    services: [], // No default filter - show all sitters
    searchQuery: "", // New: search query
  };
  let allSitters = [];
  let filteredSitters = [];
  function formatPrice(price) {
    return price.toLocaleString("vi-VN");
  }
  // ===== FILTER LOGIC =====
  function matchesFilters(sitter, filters) {
    // Check petType
    if (filters.petType.length > 0) {
      const hasMatch = filters.petType.some((pet) => sitter.petType.includes(pet));
      if (!hasMatch) return false;
    }
    // Check personality
    if (filters.personality.length > 0) {
      const hasMatch = filters.personality.some((p) => sitter.personality.includes(p));
      if (!hasMatch) return false;
    }
    // Check specialNeeds
    if (filters.specialNeeds.length > 0) {
      const hasMatch = filters.specialNeeds.some((need) => sitter.specialNeeds.includes(need));
      if (!hasMatch) return false;
    }
    // Check experienceLevel (exact match)
    if (filters.experienceLevel.length > 0) {
      if (!filters.experienceLevel.includes(sitter.experienceLevel)) {
        return false;
      }
    }
    // Check diet
    if (filters.diet.length > 0) {
      const hasMatch = filters.diet.some((d) => sitter.diet.includes(d));
      if (!hasMatch) return false;
    }
    // Check environment (exact match)
    if (filters.environment.length > 0) {
      if (!filters.environment.includes(sitter.environment)) {
        return false;
      }
    }
    // Check availability
    if (filters.availability.length > 0) {
      const hasMatch = filters.availability.some((avail) => sitter.availability.includes(avail));
      if (!hasMatch) return false;
    }
    // Check trust level
    if (filters.trustLevel.length > 0) {
      // "pro" filter: only pro
      if (filters.trustLevel.includes("pro-only")) {
        if (sitter.trustLevel !== "pro") return false;
      }
      // "verified+" filter: pro or verified
      if (filters.trustLevel.includes("verified-plus")) {
        if (sitter.trustLevel !== "pro" && sitter.trustLevel !== "verified") return false;
        // Sort by confidence score DESC by default
        filteredSitters.sort((a, b) => b.confidenceScore - a.confidenceScore);
      }
    }
    // Check price range
    if (filters.priceRange && filters.priceRange !== "all") {
      const price = sitter.price;
      if (filters.priceRange === "0-100" && price >= 100000) return false;
      if (filters.priceRange === "100-200" && (price < 100000 || price >= 200000)) return false;
      if (filters.priceRange === "200-300" && (price < 200000 || price >= 300000)) return false;
      if (filters.priceRange === "300-400" && (price < 300000 || price >= 400000)) return false;
      if (filters.priceRange === "400-500" && (price < 400000 || price >= 500000)) return false;
      if (filters.priceRange === "500+" && price < 500000) return false;
    }
    // Check district
    if (filters.district && filters.district !== "all") {
      if (sitter.district && sitter.district.toLowerCase() !== filters.district.toLowerCase()) {
        return false;
      }
    }
    // Check services
    if (filters.services && filters.services.length > 0) {
      // Sitter must have at least one of the selected services
      const hasMatch = filters.services.some(
        (service) => sitter.services && sitter.services.includes(service),
      );
      if (!hasMatch) return false;
    }
    // Check search query
    if (filters.searchQuery && filters.searchQuery.trim() !== "") {
      const searchTerm = filters.searchQuery.toLowerCase();
      const matchesName = sitter.name.toLowerCase().includes(searchTerm);
      const matchesLocation = sitter.location.toLowerCase().includes(searchTerm);
      const matchesDistrict = sitter.district.toLowerCase().includes(searchTerm);
      if (!matchesName && !matchesLocation && !matchesDistrict) {
        return false;
      }
    }
    return true;
  }
  function applyFilters() {
    showLoading();
    // Simulate network delay for better UX
    scope.setTimeout(() => {
      filteredSitters = allSitters.filter((sitter) => matchesFilters(sitter, currentFilters));
      renderListings();
      updateDropdownCounts();
      hideLoading();
    }, 300);
  }
  // ===== UPDATE DROPDOWN COUNTS =====
  function updateDropdownCounts() {
    // Update price dropdown counts
    const priceOptions = document.querySelectorAll("#filter-price-menu .filter-dropdown-option");
    priceOptions.forEach((option) => {
      const value = option.getAttribute("data-value");
      let count = 0;
      if (value === "all") {
        count = allSitters.length;
      } else {
        const tempFilters = { ...currentFilters, priceRange: value };
        count = allSitters.filter((sitter) => matchesFilters(sitter, tempFilters)).length;
      }
      // Update text with count
      const baseText = option.textContent.replace(/\s*\(\d+\)$/, "");
      option.textContent = `${baseText} (${count})`;
    });
    // Update district dropdown counts
    const districtOptions = document.querySelectorAll(
      "#filter-district-menu .filter-dropdown-option",
    );
    districtOptions.forEach((option) => {
      const value = option.getAttribute("data-value");
      let count = 0;
      if (value === "all") {
        count = allSitters.length;
      } else {
        const tempFilters = { ...currentFilters, district: value };
        count = allSitters.filter((sitter) => matchesFilters(sitter, tempFilters)).length;
      }
      // Update text with count
      const baseText = option.textContent.replace(/\s*\(\d+\)$/, "");
      option.textContent = `${baseText} (${count})`;
    });
  }
  function getTrustBadgeHTML(trustLevel) {
    if (trustLevel === "pro") {
      return '<div class="trust-badge trust-badge--pro"><i class="fa-solid fa-medal"></i> PETTEL Pro</div>';
    } else if (trustLevel === "verified") {
      return '<div class="trust-badge trust-badge--verified"><i class="fa-solid fa-shield-check"></i> PETTEL Verified</div>';
    } else if (trustLevel === "new") {
      return '<div class="trust-badge trust-badge--new"><i class="fa-solid fa-user"></i> New Member</div>';
    }
    return "";
  }
  function createListingCard(sitter) {
    const petTypeLabels = sitter.petType
      .map((pet) =>
        pet === "cat" ? '<i class="fa-solid fa-cat"></i>' : '<i class="fa-solid fa-dog"></i>',
      )
      .join(" ");
    const trustBadge = getTrustBadgeHTML(sitter.trustLevel);
    const card = document.createElement("a");
    card.href = `/detail?id=${sitter.id}`;
    card.className = "listing-card";
    card.innerHTML = `
    <div class="listing-card__image">
      <img src="${sitter.image}" alt="${sitter.name}" loading="lazy">
      <div class="confidence-score-pill">Score ${sitter.confidenceScore}</div>
    </div>
    <div class="listing-card__content">
      <div>
        <div class="listing-card__type">${sitter.type}</div>
        <div class="listing-card__name">${sitter.name}</div>
        ${trustBadge}
        <div class="listing-card__status">${sitter.status}</div>
        <div class="listing-card__amenities">${sitter.amenities.slice(0, 3).join(" · ")}</div>
        <div class="listing-card__tags" style="margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap;">
          <span style="font-size: 14px;">${petTypeLabels}</span>
        </div>
      </div>
      <div class="listing-card__bottom">
        <div class="listing-card__rating">
          <span>${sitter.rating}</span>
          <span class="star">★</span>
          <span class="count">(${sitter.reviewCount.toLocaleString("vi-VN")} đánh giá)</span>
        </div>
        <div class="listing-card__price">${formatPrice(sitter.price)} VND <span>/ ngày</span></div>
      </div>
      <div class="listing-card__favorite" aria-label="Yêu thích">
        <svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
      </div>
    </div>
  `;
    // Add favorite button functionality
    const favoriteBtn = card.querySelector(".listing-card__favorite");
    scope.listen(favoriteBtn, "click", (e) => {
      e.preventDefault();
      favoriteBtn.classList.toggle("active");
    });
    return card;
  }
  function renderListings() {
    const container = document.getElementById("listings-container");
    const countElement = document.getElementById("listings-count");
    const noResults = document.getElementById("no-results");
    if (!container || !countElement || !noResults) {
      console.error("Required listing elements not found");
      return;
    }
    container.innerHTML = "";
    if (filteredSitters.length === 0) {
      noResults.style.display = "flex";
      countElement.textContent = "Không có kết quả";
    } else {
      noResults.style.display = "none";
      countElement.textContent = `${filteredSitters.length}+ Sitter ở Đà Nẵng`;
      filteredSitters.forEach((sitter) => {
        const card = createListingCard(sitter);
        container.appendChild(card);
      });
    }
  }
  function showLoading() {
    const overlay = document.getElementById("loading-overlay");
    if (overlay) {
      overlay.style.display = "flex";
    }
  }
  function hideLoading() {
    const overlay = document.getElementById("loading-overlay");
    if (overlay) {
      overlay.style.display = "none";
    }
  }
  // ===== EVENT HANDLERS =====
  function setupFilterToggle() {
    const toggleBtn = document.getElementById("toggle-filters-btn");
    const filtersPanel = document.getElementById("advanced-filters");
    if (!toggleBtn || !filtersPanel) {
      console.warn("Filter toggle elements not found");
      return;
    }
    scope.listen(toggleBtn, "click", () => {
      filtersPanel.classList.toggle("open");
    });
  }
  function setupFilterCheckboxes() {
    const checkboxes = document.querySelectorAll('.filter-checkbox input[type="checkbox"]');
    checkboxes.forEach((checkbox) => {
      scope.listen(checkbox, "change", (e) => {
        const filterType = e.target.dataset.filter;
        const value = e.target.value;
        if (e.target.checked) {
          if (!currentFilters[filterType].includes(value)) {
            currentFilters[filterType].push(value);
          }
        } else {
          currentFilters[filterType] = currentFilters[filterType].filter((v) => v !== value);
        }
        // Real-time filter? Uncomment below to enable
        // debouncedApplyFilters();
      });
    });
  }
  function setupFilterActions() {
    const applyBtn = document.getElementById("apply-filters-btn");
    const clearBtn = document.getElementById("clear-filters-btn");
    if (!applyBtn || !clearBtn) {
      console.warn("Filter action buttons not found");
      return;
    }
    scope.listen(applyBtn, "click", () => {
      applyFilters();
    });
    scope.listen(clearBtn, "click", () => {
      // Clear all filters
      currentFilters = {
        petType: [],
        personality: [],
        specialNeeds: [],
        experienceLevel: [],
        diet: [],
        environment: [],
        availability: [],
        trustLevel: [],
      };
      // Uncheck all checkboxes
      const checkboxes = document.querySelectorAll('.filter-checkbox input[type="checkbox"]');
      checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
      });
      // Apply cleared filters
      applyFilters();
    });
  }
  // ===== TRUST FILTER BUTTONS =====
  function setupTrustFilters() {
    const proOnlyBtn = document.getElementById("filter-pro-only");
    const verifiedPlusBtn = document.getElementById("filter-verified-plus");
    if (!proOnlyBtn || !verifiedPlusBtn) {
      console.warn("Trust filter buttons not found");
      return;
    }
    scope.listen(proOnlyBtn, "click", () => {
      proOnlyBtn.classList.toggle("filter-pill--active");
      proOnlyBtn.classList.toggle("filter-pill--inactive");
      if (proOnlyBtn.classList.contains("filter-pill--active")) {
        if (!currentFilters.trustLevel.includes("pro-only")) {
          currentFilters.trustLevel.push("pro-only");
        }
        // Remove verified-plus if active
        currentFilters.trustLevel = currentFilters.trustLevel.filter((t) => t !== "verified-plus");
        verifiedPlusBtn.classList.remove("filter-pill--active");
        verifiedPlusBtn.classList.add("filter-pill--inactive");
      } else {
        currentFilters.trustLevel = currentFilters.trustLevel.filter((t) => t !== "pro-only");
      }
      applyFilters();
    });
    scope.listen(verifiedPlusBtn, "click", () => {
      verifiedPlusBtn.classList.toggle("filter-pill--active");
      verifiedPlusBtn.classList.toggle("filter-pill--inactive");
      if (verifiedPlusBtn.classList.contains("filter-pill--active")) {
        if (!currentFilters.trustLevel.includes("verified-plus")) {
          currentFilters.trustLevel.push("verified-plus");
        }
        // Remove pro-only if active
        currentFilters.trustLevel = currentFilters.trustLevel.filter((t) => t !== "pro-only");
        proOnlyBtn.classList.remove("filter-pill--active");
        proOnlyBtn.classList.add("filter-pill--inactive");
      } else {
        currentFilters.trustLevel = currentFilters.trustLevel.filter((t) => t !== "verified-plus");
      }
      applyFilters();
    });
  }
  // ===== SERVICE FILTER BUTTONS =====
  function setupServiceFilters() {
    const trongTaiGiaBtn = document.getElementById("filter-trongtaigia");
    const guiGamBtn = document.getElementById("filter-guigam");
    const datDiDaoBtn = document.getElementById("filter-datdidao");
    const serviceButtons = [
      { btn: trongTaiGiaBtn, service: "trongtaigia" },
      { btn: guiGamBtn, service: "guigam" },
      { btn: datDiDaoBtn, service: "datdidao" },
    ];
    serviceButtons.forEach(({ btn, service }) => {
      if (btn) {
        scope.listen(btn, "click", () => {
          btn.classList.toggle("filter-pill--active");
          btn.classList.toggle("filter-pill--inactive");
          // Update filter state
          if (btn.classList.contains("filter-pill--active")) {
            if (!currentFilters.services.includes(service)) {
              currentFilters.services.push(service);
            }
          } else {
            const index = currentFilters.services.indexOf(service);
            if (index > -1) {
              currentFilters.services.splice(index, 1);
            }
          }
          applyFilters();
        });
      }
    });
  }
  // ===== RESET AND APPLY FILTER ACTIONS =====
  // (These buttons are now inside the pet selector dropdown)
  function setupFilterResetApply() {
    // Logic moved to setupSmartMatch()
  }
  // ===== SMART MATCH =====
  let selectedPetIds = []; // Changed from single to multiple
  // Initialize mock pet data if not exists
  function initializeMockPets() {
    const existingPets = localStorage.getItem("pettel_pets");
    if (!existingPets) {
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
          specialNote: "Stress when meeting strangers — needs 30 min warm-up",
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
  }
  function loadPetsToSelector() {
    const petList = document.getElementById("pet-selector-list");
    if (!petList) {
      console.warn("pet-selector-list element not found");
      return;
    }
    const pets = JSON.parse(localStorage.getItem("pettel_pets") || "[]");
    petList.innerHTML = "";
    if (pets.length === 0) {
      petList.innerHTML =
        '<div style="padding: 16px; color: #9CA3AF; text-align: center; font-size: 14px;">Chưa có thú cưng nào</div>';
      return;
    }
    // Add "Select All" checkbox if there are pets
    const selectAllItem = document.createElement("div");
    selectAllItem.className = "pet-checkbox-item select-all";
    selectAllItem.innerHTML = `
    <input type="checkbox" id="pet-select-all">
    <label for="pet-select-all">Chọn tất cả</label>
  `;
    petList.appendChild(selectAllItem);
    // Add individual pet checkboxes
    pets.forEach((pet) => {
      const item = document.createElement("div");
      item.className = "pet-checkbox-item";
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.id = `pet-${pet.id}`;
      checkbox.value = pet.id;
      checkbox.className = "pet-checkbox";
      const label = document.createElement("label");
      label.htmlFor = checkbox.id;
      label.textContent = pet.name;
      item.append(checkbox, label);
      petList.appendChild(item);
    });
    // Setup "Select All" logic
    const selectAllCheckbox = document.getElementById("pet-select-all");
    const petCheckboxes = document.querySelectorAll(".pet-checkbox");
    if (selectAllCheckbox) {
      scope.listen(selectAllCheckbox, "change", (e) => {
        petCheckboxes.forEach((cb) => {
          cb.checked = e.target.checked;
        });
        updateSelectedPets();
      });
    }
    // Individual checkbox change
    petCheckboxes.forEach((cb) => {
      scope.listen(cb, "change", () => {
        // Update "Select All" state
        const allChecked = Array.from(petCheckboxes).every((c) => c.checked);
        const someChecked = Array.from(petCheckboxes).some((c) => c.checked);
        if (selectAllCheckbox) {
          selectAllCheckbox.checked = allChecked;
          selectAllCheckbox.indeterminate = someChecked && !allChecked;
        }
        updateSelectedPets();
      });
    });
  }
  function updateSelectedPets() {
    const petCheckboxes = document.querySelectorAll(".pet-checkbox:checked");
    selectedPetIds = Array.from(petCheckboxes).map((cb) => cb.value);
  }
  function applySmartMatch(petIds) {
    const pets = JSON.parse(localStorage.getItem("pettel_pets") || "[]");
    if (!petIds || petIds.length === 0) return;
    // Get all selected pets
    const selectedPets = pets.filter((p) => petIds.includes(p.id));
    if (selectedPets.length === 0) return;
    // Combine filters from all selected pets
    const combinedPetTypes = new Set();
    const combinedPersonalities = new Set();
    const combinedEnvironments = new Set();
    selectedPets.forEach((pet) => {
      combinedPetTypes.add(pet.species);
      pet.personality.forEach((p) => combinedPersonalities.add(p));
      if (pet.personality.includes("indoor-only")) {
        combinedEnvironments.add("indoor-only");
      }
    });
    // Update filters
    currentFilters.petType = Array.from(combinedPetTypes);
    currentFilters.personality = Array.from(combinedPersonalities);
    currentFilters.environment = Array.from(combinedEnvironments);
    // Update banner
    const petNames = selectedPets.map((p) => p.name).join(", ");
    const selectedPetNameEl = document.getElementById("selected-pet-name");
    const banner = document.getElementById("smart-match-banner");
    if (selectedPetNameEl) {
      selectedPetNameEl.textContent = petNames;
    }
    if (banner) {
      banner.style.display = "flex";
    }
    // Check corresponding filter checkboxes
    document.querySelectorAll('.filter-checkbox input[type="checkbox"]').forEach((checkbox) => {
      checkbox.checked = false;
    });
    currentFilters.petType.forEach((type) => {
      const cb = document.querySelector(`.filter-checkbox input[value="${type}"]`);
      if (cb) cb.checked = true;
    });
    currentFilters.personality.forEach((p) => {
      const cb = document.querySelector(`.filter-checkbox input[value="${p}"]`);
      if (cb) cb.checked = true;
    });
    currentFilters.environment.forEach((e) => {
      const cb = document.querySelector(`.filter-checkbox input[value="${e}"]`);
      if (cb) cb.checked = true;
    });
    applyFilters();
  }
  function setupSmartMatch() {
    const toggleBtn = document.getElementById("smart-match-toggle");
    const dropdown = document.getElementById("pet-selector-dropdown");
    const banner = document.getElementById("smart-match-banner");
    const closeBtn = document.getElementById("smart-match-close");
    const applyBtn = document.getElementById("pet-apply-btn");
    const resetBtn = document.getElementById("pet-reset-btn");
    // Null check - only critical elements
    if (!toggleBtn || !dropdown) {
      console.warn("Smart Match critical elements not found");
      return;
    }
    loadPetsToSelector();
    // Toggle dropdown visibility
    scope.listen(toggleBtn, "click", (e) => {
      e.stopPropagation();
      const isVisible = dropdown.style.display === "block";
      if (!isVisible) {
        // Position dropdown relative to button (fixed positioning)
        const rect = toggleBtn.getBoundingClientRect();
        dropdown.style.top = `${rect.bottom + 5}px`; // 5px below button
        dropdown.style.left = `${rect.left}px`; // Align left edge with button
        dropdown.style.display = "block";
        toggleBtn.classList.add("filter-pill--active");
        toggleBtn.classList.remove("filter-pill--inactive");
      } else {
        dropdown.style.display = "none";
        toggleBtn.classList.remove("filter-pill--active");
        toggleBtn.classList.add("filter-pill--inactive");
      }
    });
    // Apply button
    if (applyBtn) {
      scope.listen(applyBtn, "click", () => {
        if (selectedPetIds.length > 0) {
          applySmartMatch(selectedPetIds);
          dropdown.style.display = "none";
        } else {
          alert("Vui lòng chọn ít nhất một thú cưng");
        }
      });
    }
    // Reset button
    if (resetBtn) {
      scope.listen(resetBtn, "click", () => {
        // Uncheck all pet checkboxes
        document.querySelectorAll(".pet-checkbox").forEach((cb) => {
          cb.checked = false;
        });
        const selectAllCheckbox = document.getElementById("pet-select-all");
        if (selectAllCheckbox) {
          selectAllCheckbox.checked = false;
          selectAllCheckbox.indeterminate = false;
        }
        // Uncheck all Advanced Filters checkboxes
        document.querySelectorAll("[data-filter]").forEach((checkbox) => {
          checkbox.checked = false;
        });
        selectedPetIds = [];
        // Hide banner and dropdown
        banner.style.display = "none";
        dropdown.style.display = "none";
        toggleBtn.classList.remove("filter-pill--active");
        toggleBtn.classList.add("filter-pill--inactive");
        // Reset filters
        currentFilters = {
          petType: [],
          personality: [],
          specialNeeds: [],
          experienceLevel: [],
          diet: [],
          environment: [],
          availability: [],
          trustLevel: [],
        };
        applyFilters();
      });
    }
    // Close dropdown when clicking outside
    scope.listen(document, "click", (e) => {
      if (!toggleBtn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = "none";
      }
    });
    // Close banner
    if (closeBtn) {
      scope.listen(closeBtn, "click", () => {
        if (banner) banner.style.display = "none";
        selectedPetIds = [];
        toggleBtn.classList.remove("filter-pill--active");
        toggleBtn.classList.add("filter-pill--inactive");
        // Uncheck all
        document.querySelectorAll(".pet-checkbox").forEach((cb) => {
          cb.checked = false;
        });
        const selectAllCheckbox = document.getElementById("pet-select-all");
        if (selectAllCheckbox) {
          selectAllCheckbox.checked = false;
        }
      });
    }
  }
  // ===== PRICE & DISTRICT DROPDOWN HANDLERS =====
  function setupPriceDistrictFilters() {
    // Price dropdown
    const priceBtn = document.getElementById("filter-price-btn");
    const priceMenu = document.getElementById("filter-price-menu");
    const priceText = document.getElementById("filter-price-text");
    if (priceBtn && priceMenu) {
      scope.listen(priceBtn, "click", (e) => {
        e.stopPropagation();
        const isVisible = priceMenu.style.display === "block";
        priceMenu.style.display = isVisible ? "none" : "block";
        // Position menu below button
        if (priceMenu.style.display === "block") {
          const rect = priceBtn.getBoundingClientRect();
          priceMenu.style.top = `${rect.bottom + 8}px`;
          priceMenu.style.left = `${rect.left}px`;
        }
        // Close district menu
        const districtMenu = document.getElementById("filter-district-menu");
        if (districtMenu) districtMenu.style.display = "none";
      });
      // Price options
      priceMenu.querySelectorAll(".filter-dropdown-option").forEach((option) => {
        scope.listen(option, "click", () => {
          const value = option.getAttribute("data-value");
          currentFilters.priceRange = value;
          priceText.textContent = option.textContent;
          priceMenu.style.display = "none";
          applyFilters();
        });
      });
    }
    // District dropdown
    const districtBtn = document.getElementById("filter-district-btn");
    const districtMenu = document.getElementById("filter-district-menu");
    const districtText = document.getElementById("filter-district-text");
    if (districtBtn && districtMenu) {
      scope.listen(districtBtn, "click", (e) => {
        e.stopPropagation();
        const isVisible = districtMenu.style.display === "block";
        districtMenu.style.display = isVisible ? "none" : "block";
        // Position menu below button
        if (districtMenu.style.display === "block") {
          const rect = districtBtn.getBoundingClientRect();
          districtMenu.style.top = `${rect.bottom + 8}px`;
          districtMenu.style.left = `${rect.left}px`;
        }
        // Close price menu
        if (priceMenu) priceMenu.style.display = "none";
      });
      // District options
      districtMenu.querySelectorAll(".filter-dropdown-option").forEach((option) => {
        scope.listen(option, "click", () => {
          const value = option.getAttribute("data-value");
          currentFilters.district = value;
          districtText.textContent = option.textContent;
          districtMenu.style.display = "none";
          applyFilters();
        });
      });
    }
    // Close dropdowns when clicking outside
    scope.listen(document, "click", () => {
      if (priceMenu) priceMenu.style.display = "none";
      if (districtMenu) districtMenu.style.display = "none";
    });
  }
  // ===== INITIALIZATION =====
  // ===== SEARCH FUNCTIONALITY =====
  let searchTimeout = null; // Debounce timer
  function setupSearch() {
    const searchInput = document.getElementById("search-input");
    const searchBtn = document.getElementById("search-btn");
    if (!searchInput || !searchBtn) {
      console.warn("Search elements not found");
      return;
    }
    // Handle search button click
    scope.listen(searchBtn, "click", () => {
      // Clear any pending auto-search
      if (searchTimeout) {
        scope.clearTimeout(searchTimeout);
        searchTimeout = null;
      }
      performSearch(searchInput.value);
    });
    // Handle enter key press
    scope.listen(searchInput, "keypress", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        // Clear any pending auto-search
        if (searchTimeout) {
          scope.clearTimeout(searchTimeout);
          searchTimeout = null;
        }
        performSearch(searchInput.value);
      }
    });
    // Auto-search with debounce (1.5 seconds after user stops typing)
    scope.listen(searchInput, "input", (e) => {
      // Clear previous timeout
      if (searchTimeout) {
        scope.clearTimeout(searchTimeout);
      }
      // Set new timeout for auto-search
      searchTimeout = scope.setTimeout(() => {
        performSearch(e.target.value);
      }, 1500); // 1.5 seconds delay
    });
  }
  function performSearch(query) {
    const searchTerm = query.trim().toLowerCase();
    // Update search query in filters
    currentFilters.searchQuery = searchTerm;
    // Apply all filters (including search)
    applyFilters();
  }
  function init() {
    try {
      // Check if sittersData is available (from data.js)
      if (typeof sittersData === "undefined") {
        console.error("sittersData not found. Make sure data.js is loaded.");
        hideLoading();
        return;
      }
      // Initialize mock pet data for Smart Match
      initializeMockPets();
      // Initialize data
      allSitters = [...sittersData];
      filteredSitters = [...sittersData];
      // Sort by confidence score by default
      filteredSitters.sort((a, b) => b.confidenceScore - a.confidenceScore);
      // Setup UI
      setupFilterToggle();
      setupFilterCheckboxes();
      setupFilterActions();
      setupTrustFilters();
      setupServiceFilters();
      setupFilterResetApply();
      setupSmartMatch();
      setupPriceDistrictFilters();
      setupSearch();
      // Initial render with loading simulation
      showLoading();
      scope.setTimeout(() => {
        renderListings();
        updateDropdownCounts();
        hideLoading();
      }, 500);
    } catch (error) {
      console.error("Error during initialization:", error);
      hideLoading();
    }
  }
  // Run on page load
  if (document.readyState === "loading") {
    scope.listen(document, "DOMContentLoaded", init);
  } else {
    init();
  }
}
