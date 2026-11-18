/* eslint-disable @next/next/no-img-element -- Preserve the supplied image sizing during migration. */
import { DemoBehavior } from "@/features/demo/components/demo-behavior";

export function VideocallView() {
  return (
    <>
      <DemoBehavior kind="videocall" />
      <div className="videocall-overlay">
        <div className="videocall-calling" id="videocall-calling">
          <div className="videocall-calling-content">
            <div className="videocall-calling-avatar">
              <img src="/images/hotel_interior.png" alt="Avatar" id="calling-avatar" />
              <div className="videocall-calling-pulse"></div>
              <div className="videocall-calling-pulse"></div>
              <div className="videocall-calling-pulse"></div>
            </div>
            <h2 id="calling-name">Cat Moon Hotel</h2>
            <p className="videocall-calling-status">Đang kết nối...</p>
            <div className="videocall-calling-icon">
              <i className="fa-solid fa-phone"></i>
            </div>
          </div>
        </div>

        <div className="videocall-container" id="videocall-container" style={{ display: "none" }}>
          <div className="videocall-remote-feed">
            <video
              id="remote-video"
              className="videocall-feed-image"
              autoPlay
              playsInline
              loop
              muted
            >
              <source src="/video-call.mp4" type="video/mp4" />
              Trình duyệt của bạn không hỗ trợ thẻ video.
            </video>
            <div className="videocall-sitter-label" id="videocall-sitter-label">
              Cat Moon Hotel
            </div>
          </div>

          <div className="videocall-self-view">
            <video
              id="self-video"
              autoPlay
              playsInline
              muted
              style={{
                width: "100%",
                height: "100%",
                objectFit: "cover",
                display: "none",
              }}
            ></video>
            <div className="videocall-self-placeholder" id="self-placeholder">
              Bạn
            </div>
          </div>

          <div className="videocall-timer" id="videocall-timer">
            00:00
          </div>

          <div className="videocall-controls">
            <button className="videocall-control-btn" id="toggle-mic-btn" title="Tắt mic">
              <i className="fa-solid fa-microphone"></i>
            </button>
            <button className="videocall-control-btn" id="toggle-camera-btn" title="Tắt camera">
              <i className="fa-solid fa-video"></i>
            </button>
            <button
              className="videocall-control-btn videocall-end-btn"
              id="end-call-btn"
              title="Kết thúc cuộc gọi"
            >
              <i className="fa-solid fa-phone-slash"></i>
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
