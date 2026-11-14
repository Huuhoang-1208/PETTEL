import { sittersData } from "@/legacy/scripts/demo-data";
/** @param {import("./page-scope").PageScope} scope */
export default function initialize(scope) {
  // Load sitter data from URL
  function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
  }
  let callSeconds = 0;
  let callTimer = null;
  let micOn = true;
  let cameraOn = true;
  let localStream = null;
  // Audio context for connection tone
  let audioContext = null;
  const pageVideos = Array.from(document.querySelectorAll("video"));
  scope.onCleanup(() => {
    localStream?.getTracks().forEach((track) => track.stop());
    if (audioContext && audioContext.state !== "closed") void audioContext.close();
    pageVideos.forEach((video) => video.pause());
  });
  // Play Messenger-style calling tone
  function playConnectionTone() {
    try {
      audioContext = new (window.AudioContext || window.webkitAudioContext)();
      // Messenger-style melody: E5 -> C5 (up and down pattern)
      const notes = [
        { freq: 659.25, start: 0, duration: 0.15 }, // E5
        { freq: 523.25, start: 0.18, duration: 0.15 }, // C5
        { freq: 659.25, start: 1.2, duration: 0.15 }, // E5
        { freq: 523.25, start: 1.38, duration: 0.15 }, // C5
        { freq: 659.25, start: 2.4, duration: 0.15 }, // E5
        { freq: 523.25, start: 2.58, duration: 0.15 }, // C5
      ];
      notes.forEach((note) => {
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        oscillator.frequency.value = note.freq;
        oscillator.type = "sine";
        const startTime = audioContext.currentTime + note.start;
        const endTime = startTime + note.duration;
        // Smooth envelope
        gainNode.gain.setValueAtTime(0, startTime);
        gainNode.gain.linearRampToValueAtTime(0.25, startTime + 0.02);
        gainNode.gain.linearRampToValueAtTime(0.25, endTime - 0.02);
        gainNode.gain.linearRampToValueAtTime(0, endTime);
        oscillator.start(startTime);
        oscillator.stop(endTime);
      });
    } catch {}
  }
  // Play end call sound
  function playEndCallSound() {
    try {
      const tempContext = new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = tempContext.createOscillator();
      const gainNode = tempContext.createGain();
      oscillator.connect(gainNode);
      gainNode.connect(tempContext.destination);
      // Descending tone (disconnect sound)
      oscillator.frequency.setValueAtTime(400, tempContext.currentTime);
      oscillator.frequency.exponentialRampToValueAtTime(200, tempContext.currentTime + 0.3);
      oscillator.type = "sine";
      gainNode.gain.setValueAtTime(0.3, tempContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(0.01, tempContext.currentTime + 0.3);
      oscillator.start();
      oscillator.stop(tempContext.currentTime + 0.3);
      scope.setTimeout(() => {
        tempContext.close();
      }, 500);
    } catch {}
  }
  function connectCall() {
    document.getElementById("videocall-calling").style.display = "none";
    document.getElementById("videocall-container").style.display = "block";
    const remoteVideo = document.getElementById("remote-video");
    if (remoteVideo) {
      remoteVideo.muted = false;
      const playPromise = remoteVideo.play();
      if (playPromise !== undefined) {
        playPromise.catch(() => {
          // Autoplay can be blocked until the user interacts.
        });
      }
    }
    // Start call timer
    callTimer = scope.setInterval(() => {
      callSeconds++;
      const minutes = Math.floor(callSeconds / 60)
        .toString()
        .padStart(2, "0");
      const seconds = (callSeconds % 60).toString().padStart(2, "0");
      document.getElementById("videocall-timer").textContent = `${minutes}:${seconds}`;
    }, 1000);
  }
  async function startWebcam() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({
        video: true,
        audio: true,
      });
      if (!scope.active) {
        localStream.getTracks().forEach((track) => track.stop());
        return;
      }
      const videoElement = document.getElementById("self-video");
      const placeholder = document.getElementById("self-placeholder");
      videoElement.srcObject = localStream;
      videoElement.style.display = "block";
      placeholder.style.display = "none";
    } catch {
      // Keep placeholder if camera access fails
    }
  }
  function loadVideoCallData() {
    const sitterId = parseInt(getQueryParam("id")) || 3;
    if (typeof sittersData !== "undefined") {
      const sitter = sittersData.find((s) => s.id === sitterId);
      if (sitter) {
        document.getElementById("videocall-sitter-label").textContent = sitter.name;
        document.getElementById("calling-name").textContent = sitter.name;
        document.title = `Gọi video với ${sitter.name} | PETTEL`;
      }
    }
    // Play ringing tone
    playConnectionTone();
    // Show calling screen for 3-4 seconds
    scope.setTimeout(() => {
      connectCall();
      startWebcam();
    }, 3500);
  }
  // Toggle mic
  scope.listen(document.getElementById("toggle-mic-btn"), "click", function () {
    micOn = !micOn;
    const icon = this.querySelector("i");
    if (micOn) {
      icon.className = "fa-solid fa-microphone";
      this.style.opacity = "1";
      this.title = "Tắt mic";
      if (localStream) {
        localStream.getAudioTracks().forEach((track) => (track.enabled = true));
      }
    } else {
      icon.className = "fa-solid fa-microphone-slash";
      this.style.opacity = "0.7";
      this.style.background = "rgba(220, 38, 38, 0.9)";
      this.title = "Bật mic";
      if (localStream) {
        localStream.getAudioTracks().forEach((track) => (track.enabled = false));
      }
    }
    if (micOn) {
      this.style.background = "";
    }
  });
  // Toggle camera
  scope.listen(document.getElementById("toggle-camera-btn"), "click", function () {
    cameraOn = !cameraOn;
    const icon = this.querySelector("i");
    const videoElement = document.getElementById("self-video");
    const placeholder = document.getElementById("self-placeholder");
    if (cameraOn) {
      icon.className = "fa-solid fa-video";
      this.style.opacity = "1";
      this.title = "Tắt camera";
      if (localStream) {
        localStream.getVideoTracks().forEach((track) => (track.enabled = true));
        videoElement.style.display = "block";
        placeholder.style.display = "none";
      }
    } else {
      icon.className = "fa-solid fa-video-slash";
      this.style.opacity = "0.7";
      this.style.background = "rgba(220, 38, 38, 0.9)";
      this.title = "Bật camera";
      if (localStream) {
        localStream.getVideoTracks().forEach((track) => (track.enabled = false));
      }
      videoElement.style.display = "none";
      placeholder.style.display = "flex";
    }
    if (cameraOn) {
      this.style.background = "";
    }
  });
  // End call
  scope.listen(document.getElementById("end-call-btn"), "click", () => {
    // Play end call sound first
    playEndCallSound();
    if (callTimer) {
      scope.clearInterval(callTimer);
    }
    // Stop webcam
    if (localStream) {
      localStream.getTracks().forEach((track) => track.stop());
    }
    // Stop audio context
    if (audioContext) {
      audioContext.close();
    }
    // Wait for sound to play before redirecting
    const sitterId = parseInt(getQueryParam("id")) || 3;
    scope.setTimeout(() => {
      scope.navigate(`/chat?id=${sitterId}`);
    }, 400);
  });
  // Initialize
  if (document.readyState === "loading") {
    scope.listen(document, "DOMContentLoaded", loadVideoCallData);
  } else {
    loadVideoCallData();
  }
}
