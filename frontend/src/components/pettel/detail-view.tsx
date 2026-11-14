/* eslint-disable @next/next/no-img-element -- Preserve the supplied image sizing during migration. */
import Link from "next/link";
import { PageBehavior } from "./page-behavior";

export function DetailView() {
  return (
    <>
      <PageBehavior kind="detail" />
      <header className="detail-header" id="detail-header">
    <Link href="/" className="detail-header__back" id="back-btn" aria-label="Quay lại">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
      </svg>
    </Link>
    <div className="detail-header__actions">
      <button className="detail-header__action-btn" id="share-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"></path>
        </svg>
        Chia sẻ
      </button>
      <button className="detail-header__action-btn" id="save-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
        </svg>
        Lưu
      </button>
      <button className="detail-header__action-btn" id="user-profile-btn">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
        </svg>
      </button>
    </div>
  </header>

  
  <div className="detail-page" id="detail-page">

    
    <div className="detail-hero" id="detail-hero">
      <img src="/Pet sister/Brunos-Play-Center-1000x750.jpg" alt="Cat Moon Hotel - Nội thất khách sạn thú cưng" />
    </div>

    
    <div className="detail-body">

      
      <div className="detail-content">

        
        <div className="detail-title" id="detail-title">
          <h1>Cat Moon Hotel</h1>
          <div className="detail-title__meta">
            <span className="star">★</span>
            <span style={{"fontWeight": "600"}}>5.0</span>
            <span className="dot">·</span>
            <a href="#reviews"><strong>301 đánh giá</strong></a>
            <span className="dot">·</span>
            <span><i className="fa-solid fa-medal"></i> &quot;Chuyên gia nghiên cứu tâm lý thú cưng&quot;</span>
          </div>
        </div>

        
        <div className="detail-description" id="detail-description">
          <p>
            Kính mời quý khách đến với không gian sang trọng và ấm cúng của Cat Moon
            Hotel của Lê Hằng, tọa lạc tại vị trí đắc địa trung tâm thành phố Đà Nẵng. Chúng tôi
            tự hào mang đến dịch vụ chăm sóc thú cưng chuyên nghiệp với những phòng nghỉ
            được thiết kế tỉ mỉ, đảm bảo thú cưng của bạn được sống trong môi trường thoải mái và
            thưởng không gian sống thoải mái nhất.
          </p>
        </div>

        
        <div className="detail-amenities" id="detail-amenities">
          <h2>Tiện ích nổi bật</h2>
          <div className="amenities-grid">
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-cat"></i></div>
              <span>Tối đa 6 bé mèo</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-broom"></i></div>
              <span>6 khay vệ sinh</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-bed"></i></div>
              <span>6 nệm ngủ cao cấp</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-gamepad"></i></div>
              <span>Khu vui chơi riêng</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-scissors"></i></div>
              <span>Dịch vụ cắt tỉa lông</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-wind"></i></div>
              <span>Máy lọc không khí</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-snowflake"></i></div>
              <span>Điều hòa 24/7</span>
            </div>
            <div className="amenity-item">
              <div className="icon"><i className="fa-solid fa-gift"></i></div>
              <span>Giảm 10% khách quay lại</span>
            </div>
          </div>
        </div>

        
        <div className="detail-trust" id="detail-trust">
          <h2>Chứng nhận & Xác minh</h2>
          <div className="trust-verification-grid" id="trust-verification-grid">
            
          </div>
          <div className="verified-reviews-count" id="verified-reviews-count">
            
          </div>
        </div>

        
        <div className="detail-reviews" id="reviews">
          <h2>Đánh giá</h2>
          <div className="reviews-summary">
            <span className="star">★</span>
            <span className="score">5.0</span>
            <span className="dot" style={{"color": "#999"}}>·</span>
            <span>301 đánh giá</span>
          </div>

          
          <div className="review-card" id="review-1">
            <div className="review-card__header">
              <a className="facebook-account-link" href="#" target="_blank" rel="noopener noreferrer">
                <div className="review-card__avatar">
                  <div style={{"width": "100%", "height": "100%", "background": "linear-gradient(135deg,#667eea,#764ba2)", "display": "flex", "alignItems": "center", "justifyContent": "center", "color": "white", "fontWeight": "700", "fontSize": "18px"}}>K</div>
                </div>
              </a>
              <div className="review-card__info">
                <div className="name">
                  Nguyễn Khánh Huy
                  <span className="verified-review-tag"><i className="fa-solid fa-check-circle"></i> Đã hoàn thành booking</span>
                  <span className="facebook-account-label">Tài khoản liên kết Facebook</span>
                </div>
                <div className="date">2 tháng trước</div>
              </div>
            </div>
            <div className="review-card__content">
              Tôi đã đưa chú mèo của mình đến đây và thực sự rất hài lòng với
              dịch vụ. Bạn chủ không chỉ cắt tỉa lông rất đẹp mà còn chăm sóc
              rất kỹ lưỡng. Mèo của tôi không hề sợ hãi, thay vào đó
              ngược lại rất thoải mái. Tôi rất hài lòng và sẽ quay lại!
            </div>
            <div className="review-card__photo">
              <a href="#" target="_blank" rel="noopener noreferrer" className="facebook-photo-link">
                <img src="" alt="Facebook user image" />
              </a>
            </div>
          </div>

          
          <div className="review-card" id="review-2">
            <div className="review-card__header">
              <a className="facebook-account-link" href="#" target="_blank" rel="noopener noreferrer">
                <div className="review-card__avatar">
                  <div style={{"width": "100%", "height": "100%", "background": "linear-gradient(135deg,#f093fb,#f5576c)", "display": "flex", "alignItems": "center", "justifyContent": "center", "color": "white", "fontWeight": "700", "fontSize": "18px"}}>L</div>
                </div>
              </a>
              <div className="review-card__info">
                <div className="name">
                  Linh Trần
                  <span className="verified-review-tag"><i className="fa-solid fa-check-circle"></i> Đã hoàn thành booking</span>
                  <span className="facebook-account-label">Tài khoản liên kết Facebook</span>
                </div>
                <div className="date">8 tháng trước</div>
              </div>
            </div>
            <div className="review-card__content">
              Được hôm đặt tới đây tắm mà thích thú cưng quá trời. Mấy bạn
              chủ mà không thèm về luôn á, vẫn muốn ở lại nữa. Chắc là bé
              tình lắm, bảo sao tới đây cứ đòi được thả ra chơi hoài.
            </div>
            <div className="review-card__photo">
              <a href="#" target="_blank" rel="noopener noreferrer" className="facebook-photo-link">
                <img src="" alt="Facebook user image" />
              </a>
            </div>
            <button className="review-card__show-more">Show more →</button>
          </div>

          
          <div className="review-card" id="review-3">
            <div className="review-card__header">
              <a className="facebook-account-link" href="#" target="_blank" rel="noopener noreferrer">
                <div className="review-card__avatar">
                  <div style={{"width": "100%", "height": "100%", "background": "linear-gradient(135deg,#4facfe,#00f2fe)", "display": "flex", "alignItems": "center", "justifyContent": "center", "color": "white", "fontWeight": "700", "fontSize": "18px"}}>T</div>
                </div>
              </a>
              <div className="review-card__info">
                <div className="name">
                  Bùi Tuấn
                  <span className="verified-review-tag"><i className="fa-solid fa-check-circle"></i> Đã hoàn thành booking</span>
                  <span className="facebook-account-label">Tài khoản liên kết Facebook</span>
                </div>
                <div className="date">1 tháng trước</div>
              </div>
            </div>
            <div className="review-card__content">
              Địa chỉ tắm Spa, chăm sóc, cắt tỉa lông và gửi thú cưng tại
              gia nhưng chuyên nghiệp nhất mà mình biết. Dịch vụ
              tốt, có chuyên môn nên không phải lo nhé. Cứ tin
              Tùng lo cho bạn hết rồi hihi.
            </div>
            <div className="review-card__photo">
              <a href="#" target="_blank" rel="noopener noreferrer" className="facebook-photo-link">
                <img src="" alt="Facebook user image" />
              </a>
            </div>
          </div>

          <button className="btn-all-reviews" id="btn-all-reviews">Hiện cả 301 đánh giá</button>
        </div>

        
        <div className="detail-location" id="detail-location">
          <h2>Vị trí</h2>
          <div className="location-map">
            <img src="/images/danang_map.png" alt="Bản đồ vị trí Cat Moon Hotel" />
          </div>
          <div className="location-address">134 Yên Bái, Hải Châu, Đà Nẵng</div>
          <button className="location-show-more">Hiện thêm →</button>
        </div>

        
        <div className="detail-suggestions" id="detail-suggestions">
          <h2>Những lựa chọn khác ở cùng khu vực</h2>
          <div className="suggestions-grid">
            <Link href="/detail-dotung" className="suggestion-card" id="suggestion-1">
              <div className="suggestion-card__image">
                <img src="/Pet sister/portrait-young-asian-woman-holding-600nw-2344067699.webp" alt="Căn hộ Đỗ Tùng" />
              </div>
              <div className="suggestion-card__name">Căn hộ Đỗ Tùng</div>
              <div className="suggestion-card__price">150.000 VND / ngày</div>
            </Link>
            <Link href="/detail-baotram" className="suggestion-card" id="suggestion-2">
              <div className="suggestion-card__image">
                <img src="/Pet sister/istockphoto-1171217979-612x612.jpg" alt="Căn hộ Bảo Trâm" />
              </div>
              <div className="suggestion-card__name">Căn hộ Bảo Trâm</div>
              <div className="suggestion-card__price">180.000 VND / ngày</div>
            </Link>
            <Link href="/detail-minhnhat" className="suggestion-card" id="suggestion-3">
              <div className="suggestion-card__image">
                <img src="/Pet sister/portrait-beautiful-chinese-young-girl-hug-her-cute-british-shorthair-cat-who-has-amazing-orange-eyes-home-sunny-afternoon-141149436.webp" alt="Nhà riêng Minh Nhật" />
              </div>
              <div className="suggestion-card__name">Nhà riêng Minh Nhật</div>
              <div className="suggestion-card__price">200.000 VND / ngày</div>
            </Link>
            <Link href="/detail-baotram-house" className="suggestion-card" id="suggestion-4">
              <div className="suggestion-card__image">
                <img src="/Pet sister/smiling-young-asian-woman-in-yellow-shirt-is-holding-a-white-ragdoll-cat-over-isolated-white-background-photo.jpg" alt="Nhà riêng Bảo Trâm" />
              </div>
              <div className="suggestion-card__name">Nhà riêng Bảo Trâm</div>
              <div className="suggestion-card__price">160.000 VND / ngày</div>
            </Link>
          </div>
        </div>

      </div>

      
      <aside className="booking-widget" id="booking-widget">
        <div className="booking-widget__header">
          <div className="booking-widget__price">175.000 VND <span>/ ngày</span></div>
          <div className="booking-widget__rating">
            <div className="booking-widget__rating-top">
              <span className="star">★</span> 5.0
            </div>
            <div className="booking-widget__rating-count">301 đánh giá</div>
          </div>
        </div>

        
        <div className="confidence-score-section" id="confidence-score-section">
          
        </div>

        
        <div className="booking-contact-info" id="booking-contact-info" style={{"display": "none"}}>
          <div className="contact-info-header">
            <h3>📞 Thông tin liên hệ</h3>
            <button className="contact-edit-btn" id="contact-edit-btn">Chỉnh sửa</button>
          </div>
          <div className="contact-info-body">
            <p><strong>Tên:</strong> <span id="contact-name">-</span></p>
            <p><strong>SĐT:</strong> <span id="contact-phone">-</span></p>
            <p><strong>Địa chỉ:</strong> <span id="contact-address">-</span></p>
          </div>
        </div>

        <div className="booking-widget__dates">
          <div className="booking-widget__date-field">
            <label>Check-in</label>
            <input type="date" id="booking-checkin" />
          </div>
          <div className="booking-widget__date-field">
            <label>Check-out</label>
            <input type="date" id="booking-checkout" />
          </div>
        </div>

        <div className="quick-select-dates">
          <button className="quick-date-btn" data-days="1">1 ngày</button>
          <button className="quick-date-btn" data-days="2">2 ngày</button>
          <button className="quick-date-btn" data-days="3">3 ngày</button>
        </div>

        <div className="booking-widget__pet-field">
          <label>Thú cưng</label>
          <span>2 bé mèo</span>
        </div>

        
        <div className="care-instructions">
          <label>Ghi chú cho người chăm</label>
          <textarea placeholder="Ví dụ: Min cần 30 phút làm quen trước khi được chạm vào. Không cho ăn cá." rows={3}></textarea>
          <div className="care-instructions-note">Sẽ được gửi tự động khi đặt lịch</div>
        </div>

        
        <div className="escrow-payment-box">
          <div className="escrow-icon"><i className="fa-solid fa-lock"></i></div>
          <div className="escrow-content">
            <div className="escrow-title">Thanh toán an toàn qua PETTEL</div>
            <div className="escrow-details">
              Tiền giữ tạm · Hoàn trả 100% nếu hủy<br />
              Chỉ chuyển cho sitter sau khi bạn xác nhận hoàn thành
            </div>
          </div>
        </div>

        <button className="booking-widget__cta" id="booking-cta">ĐẶT LỊCH NGAY</button>
        <button className="booking-widget__chat-btn" id="chat-btn"><i className="fa-solid fa-comment"></i> Nhắn tin với sitter</button>

        <div className="booking-widget__breakdown" id="booking-breakdown">
          <div className="booking-widget__line">
            <span id="price-per-day-text">175.000 VND x 2 ngày</span>
            <span id="price-subtotal">350.000 VND</span>
          </div>
        </div>

        <div className="booking-widget__total">
          <span>Tổng</span>
          <span id="price-total">350.000 VND</span>
        </div>

        <div className="booking-widget__report">
          <svg viewBox="0 0 24 24"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"></path></svg>
          Báo cáo đơn hàng này
        </div>
      </aside>

    </div>

  </div>

  
  <div className="booking-modal" id="booking-modal" style={{"display": "none"}}>
    <div className="booking-modal-content booking-confirmation">
      <h2>Xác nhận đặt lịch</h2>
      
      <div className="confirmation-section">
        <h3>📞 Thông tin liên hệ</h3>
        <p><strong>Tên:</strong> <span id="confirm-name">-</span></p>
        <p><strong>SĐT:</strong> <span id="confirm-phone">-</span></p>
        <p><strong>Địa chỉ:</strong> <span id="confirm-address">-</span></p>
      </div>

      <div className="confirmation-section">
        <h3>📅 Thời gian</h3>
        <p><strong>Check-in:</strong> <span id="confirm-checkin">-</span></p>
        <p><strong>Check-out:</strong> <span id="confirm-checkout">-</span></p>
        <p><strong>Số ngày:</strong> <span id="confirm-days">-</span> ngày</p>
      </div>

      <div className="confirmation-section">
        <h3>💰 Chi phí</h3>
        <p><span id="confirm-price-breakdown">-</span></p>
        <p style={{"fontSize": "18px", "fontWeight": "700", "color": "var(--color-primary)", "marginTop": "8px"}}>
          Tổng: <span id="confirm-total">-</span>
        </p>
      </div>

      <div className="confirmation-note">
        <i className="fa-solid fa-lock"></i> Tiền sẽ được giữ an toàn bởi PETTEL
      </div>

      <div className="booking-modal-actions">
        <button className="booking-modal-back" id="booking-modal-back">Quay lại</button>
        <button className="booking-modal-confirm" id="booking-modal-confirm">Xác nhận đặt lịch</button>
      </div>
    </div>
  </div>

  
  <div className="booking-modal" id="booking-success-modal" style={{"display": "none"}}>
    <div className="booking-modal-content">
      <div className="booking-modal-icon"><i className="fa-solid fa-check-circle"></i></div>
      <h2>Đặt lịch thành công!</h2>
      <p>Tiền đang được giữ an toàn bởi PETTEL</p>
      <button className="booking-modal-close" id="booking-success-close">Đóng</button>
    </div>
  </div>

  
  <div className="booking-modal" id="profile-success-modal" style={{"display": "none"}}>
    <div className="booking-modal-content">
      <div className="booking-modal-icon"><i className="fa-solid fa-check-circle"></i></div>
      <h2>Cập nhật hồ sơ thành công!</h2>
      <p>Thông tin của bạn đã được lưu</p>
      <button className="booking-modal-close" id="profile-success-close">Đóng</button>
    </div>
  </div>

  
  <div className="user-profile-modal" id="user-profile-modal" style={{"display": "none"}}>
    <div className="user-profile-content">
      <div className="user-profile-header">
        <h2>Thông tin cá nhân</h2>
        <button className="user-profile-close" id="user-profile-close">&times;</button>
      </div>
      <form className="user-profile-form" id="user-profile-form">
        <div className="form-group">
          <label>Họ và tên</label>
          <input type="text" id="profile-name" required />
        </div>
        <div className="form-group">
          <label>Số điện thoại</label>
          <input type="tel" id="profile-phone" required />
        </div>
        <div className="form-group">
          <label>Email (tùy chọn)</label>
          <input type="email" id="profile-email" />
        </div>
        <div className="form-group">
          <label>Địa chỉ</label>
          <input type="text" id="profile-address" required />
        </div>
        <div className="form-group">
          <label>Giới tính</label>
          <select id="profile-gender">
            <option value="Nam">Nam</option>
            <option value="Nữ">Nữ</option>
          </select>
        </div>
        <button type="submit" className="user-profile-save-btn">Lưu thông tin</button>
      </form>
    </div>
  </div>

  
  <nav className="bottom-nav" id="bottom-nav">
    <Link href="/" className="bottom-nav__item bottom-nav__item--active" id="nav-home">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"></path>
      </svg>
      Trang chủ
    </Link>
    <a href="#" className="bottom-nav__item" id="nav-search">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>
      </svg>
      Tìm kiếm
    </a>
    <button className="bottom-nav__item" id="nav-profile">
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
      </svg>
      Profile
    </button>
  </nav>
    </>
  );
}
