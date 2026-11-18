import { sittersData } from "@/legacy/scripts/demo-data";
/** @param {import("./page-scope").PageScope} scope */
export default function initialize(scope) {
  // Load sitter data from URL
  function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
  }
  let updateInterval = null;
  function loadLiveCamData() {
    const sitterId = parseInt(getQueryParam("id")) || 3;
    if (typeof sittersData !== "undefined") {
      const sitter = sittersData.find((s) => s.id === sitterId);
      if (sitter) {
        document.getElementById("livecam-location").innerHTML =
          `<i class="fa-solid fa-map-marker-alt"></i> Phòng của thú cưng · ${sitter.name}`;
        document.title = `Live Cam - ${sitter.name} | PETTEL`;
      }
    }
    // Update timestamp every 30 seconds
    updateTimestamp();
    updateInterval = scope.setInterval(updateTimestamp, 30000);
  }
  function updateTimestamp() {
    const now = new Date();
    const time = `${now.getHours()}:${now.getMinutes().toString().padStart(2, "0")}`;
    document.getElementById("livecam-timestamp").innerHTML =
      `<i class="fa-solid fa-clock"></i> Cập nhật: vừa xong (${time})`;
  }
  // Screenshot button
  scope.listen(document.getElementById("screenshot-btn"), "click", () => {
    // Show toast notification
    const toast = document.createElement("div");
    toast.className = "livecam-toast";
    toast.innerHTML = '<i class="fa-solid fa-check-circle"></i> Đã lưu ảnh';
    document.body.appendChild(toast);
    scope.onCleanup(() => toast.remove());
    scope.setTimeout(() => {
      toast.classList.add("show");
    }, 10);
    scope.setTimeout(() => {
      toast.classList.remove("show");
      scope.setTimeout(() => {
        document.body.removeChild(toast);
      }, 300);
    }, 2000);
  });
  // Close button
  scope.listen(document.getElementById("close-btn"), "click", () => {
    if (updateInterval) {
      scope.clearInterval(updateInterval);
    }
    const sitterId = parseInt(getQueryParam("id")) || 3;
    scope.navigate(`/chat?id=${sitterId}`);
  });
  // Initialize
  if (document.readyState === "loading") {
    scope.listen(document, "DOMContentLoaded", () => {
      loadLiveCamData();
      scope.setTimeout(() => {
        const video = document.getElementById("live-video");
        if (video) {
          video.muted = false;
          video.volume = 1;
        }
      }, 100);
    });
  } else {
    loadLiveCamData();
    scope.setTimeout(() => {
      const video = document.getElementById("live-video");
      if (video) {
        video.muted = false;
        video.volume = 1;
      }
    }, 100);
  }
}
