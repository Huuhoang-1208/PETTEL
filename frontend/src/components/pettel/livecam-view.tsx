import { PageBehavior } from "./page-behavior";

export function LivecamView() {
  return (
    <>
      <PageBehavior kind="livecam" />
      <div className="livecam-overlay">
    <div className="livecam-container">
      
      
      <div className="livecam-live-badge">
        <span className="live-dot"></span> LIVE
      </div>

      
      <div className="livecam-feed">
        <video className="livecam-feed-video" id="live-video" data-test-id="duplo-hls-video" playsInline poster="https://i.pinimg.com/236x/84/78/61/847861f6266c2f8dfaa1dadf5fec0b8c.jpg" preload="auto" autoPlay>
          <source src="/7800090249519.mp4" type="video/mp4" />
          <track kind="captions" />
          Trình duyệt của bạn không hỗ trợ thẻ video.
        </video>
      </div>

      
      <div className="livecam-info">
        <div className="livecam-location" id="livecam-location">
          <i className="fa-solid fa-map-marker-alt"></i> Phòng của Min & Mun · Cat Moon Hotel
        </div>
        <div className="livecam-timestamp" id="livecam-timestamp">
          <i className="fa-solid fa-clock"></i> Cập nhật: vừa xong
        </div>
      </div>

      
      <div className="livecam-controls">
        <button className="livecam-control-btn" id="screenshot-btn">
          <i className="fa-solid fa-camera"></i>
          Chụp màn hình
        </button>
        <button className="livecam-close-btn" id="close-btn">
          <i className="fa-solid fa-xmark"></i>
          Đóng
        </button>
      </div>

    </div>
  </div>
    </>
  );
}
