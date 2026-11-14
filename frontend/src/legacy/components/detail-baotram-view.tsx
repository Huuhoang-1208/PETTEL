/* eslint-disable @next/next/no-img-element -- Preserve the supplied image sizing during migration. */
import { PageBehavior } from "@/legacy/components/page-behavior";
import Link from "next/link";

export function DetailBaotramView() {
  return (
    <>
      <PageBehavior kind="detail-baotram" />
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
        </div>
      </header>

      <div className="detail-page" id="detail-page">
        <div className="detail-hero" id="detail-hero">
          <img
            src="/Pet sister/istockphoto-1171217979-612x612.jpg"
            alt="Căn hộ Bảo Trâm - Trông giữ thú cưng"
          />
        </div>

        <div className="detail-body">
          <div className="detail-content">
            <div className="detail-title" id="detail-title">
              <h1>Căn hộ Bảo Trâm</h1>
              <div className="detail-title__meta">
                <span className="star">★</span>
                <span style={{ fontWeight: "600" }}>4.8</span>
                <span className="dot">·</span>
                <a href="#reviews">
                  <strong>278 đánh giá</strong>
                </a>
                <span className="dot">·</span>
                <span>🏅 &quot;Chăm sóc tận tâm&quot;</span>
              </div>
            </div>

            <div className="detail-description" id="detail-description">
              <p>
                Xin chào! Mình là Bảo Trâm, chuyên chăm sóc chó mèo tại căn hộ rộng rãi ở khu vực
                trung tâm Đà Nẵng. Căn hộ có ban công thoáng mát, phòng riêng cho thú cưng và đầy đủ
                tiện nghi. Mình có kinh nghiệm 5 năm làm việc tại phòng khám thú y nên rất hiểu tâm
                lý và nhu cầu của các bé. Các bé sẽ được chăm sóc như ở nhà, với chế độ ăn uống khoa
                học và hoạt động vui chơi phù hợp.
              </p>
            </div>

            <div className="detail-amenities" id="detail-amenities">
              <h2>Tiện ích nổi bật</h2>
              <div className="amenities-grid">
                <div className="amenity-item">
                  <div className="icon">🐕</div>
                  <span>Nhận cả chó và mèo</span>
                </div>
                <div className="amenity-item">
                  <div className="icon">🏥</div>
                  <span>Kinh nghiệm y tế</span>
                </div>
                <div className="amenity-item">
                  <div className="icon">🌿</div>
                  <span>Ban công thoáng mát</span>
                </div>
                <div className="amenity-item">
                  <div className="icon">📱</div>
                  <span>Live cam 24/7</span>
                </div>
                <div className="amenity-item">
                  <div className="icon">🚿</div>
                  <span>Dịch vụ tắm gội</span>
                </div>
                <div className="amenity-item">
                  <div className="icon">🎾</div>
                  <span>Khu vui chơi riêng</span>
                </div>
              </div>
            </div>

            <div className="detail-reviews" id="reviews">
              <h2>Đánh giá</h2>
              <div className="reviews-summary">
                <span className="star">★</span>
                <span className="score">4.8</span>
                <span className="dot" style={{ color: "#999" }}>
                  ·
                </span>
                <span>278 đánh giá</span>
              </div>

              <div className="review-card" id="review-1">
                <div className="review-card__header">
                  <div className="review-card__avatar">
                    <div
                      style={{
                        width: "100%",
                        height: "100%",
                        background: "linear-gradient(135deg,#667eea,#764ba2)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        color: "white",
                        fontWeight: "700",
                        fontSize: "18px",
                      }}
                    >
                      L
                    </div>
                  </div>
                  <div className="review-card__info">
                    <div className="name">Lan Anh</div>
                    <div className="date">2 tuần trước</div>
                  </div>
                </div>
                <div className="review-card__content">
                  Bảo Trâm chăm sóc chó mình rất tốt! Có kinh nghiệm y tế nên mình rất yên tâm. Bé
                  được tắm gội sạch sẽ và vui chơi đầy đủ. Live cam giúp mình theo dõi bé mọi lúc.
                  Rất hài lòng!
                </div>
              </div>

              <div className="review-card" id="review-2">
                <div className="review-card__header">
                  <div className="review-card__avatar">
                    <div
                      style={{
                        width: "100%",
                        height: "100%",
                        background: "linear-gradient(135deg,#f093fb,#f5576c)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        color: "white",
                        fontWeight: "700",
                        fontSize: "18px",
                      }}
                    >
                      T
                    </div>
                  </div>
                  <div className="review-card__info">
                    <div className="name">Tuấn Anh</div>
                    <div className="date">1 tháng trước</div>
                  </div>
                </div>
                <div className="review-card__content">
                  Căn hộ rộng rãi, sạch sẽ. Bảo Trâm rất nhiệt tình và chu đáo. Bé mèo của mình được
                  chăm sóc như ở nhà, về vẫn khỏe mạnh và vui vẻ. Giá cả hợp lý cho chất lượng dịch
                  vụ tuyệt vời này.
                </div>
              </div>

              <div className="review-card" id="review-3">
                <div className="review-card__header">
                  <div className="review-card__avatar">
                    <div
                      style={{
                        width: "100%",
                        height: "100%",
                        background: "linear-gradient(135deg,#43e97b,#38f9d7)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        color: "white",
                        fontWeight: "700",
                        fontSize: "18px",
                      }}
                    >
                      N
                    </div>
                  </div>
                  <div className="review-card__info">
                    <div className="name">Ngọc Hân</div>
                    <div className="date">2 tháng trước</div>
                  </div>
                </div>
                <div className="review-card__content">
                  Lần đầu gửi chó nên hơi lo, nhưng Bảo Trâm rất chuyên nghiệp. Có camera để xem bé
                  mọi lúc, được cập nhật tình hình hàng ngày. Bé về nhà sạch sẽ và vui vẻ hơn. Chắc
                  chắn sẽ quay lại!
                </div>
              </div>

              <button className="btn-all-reviews" id="btn-all-reviews">
                Hiện cả 278 đánh giá
              </button>
            </div>

            <div className="detail-location" id="detail-location">
              <h2>Vị trí</h2>
              <div className="location-map">
                <img src="/images/danang_map.png" alt="Bản đồ vị trí Căn hộ Bảo Trâm" />
              </div>
              <div className="location-address">88 Lê Duẩn, Hải Châu, Đà Nẵng</div>
              <button className="location-show-more">Hiện thêm →</button>
            </div>

            <div className="detail-suggestions" id="detail-suggestions">
              <h2>Những lựa chọn khác ở cùng khu vực</h2>
              <div className="suggestions-grid">
                <Link href="/detail-dotung" className="suggestion-card" id="suggestion-1">
                  <div className="suggestion-card__image">
                    <img
                      src="/Pet sister/portrait-young-asian-woman-holding-600nw-2344067699.webp"
                      alt="Căn hộ Đỗ Tùng"
                    />
                  </div>
                  <div className="suggestion-card__name">Căn hộ Đỗ Tùng</div>
                  <div className="suggestion-card__price">150.000 VND / ngày</div>
                </Link>
                <Link href="/detail" className="suggestion-card" id="suggestion-2">
                  <div className="suggestion-card__image">
                    <img src="/Pet sister/Brunos-Play-Center-1000x750.jpg" alt="Cat Moon Hotel" />
                  </div>
                  <div className="suggestion-card__name">Cat Moon Hotel</div>
                  <div className="suggestion-card__price">175.000 VND / ngày</div>
                </Link>
                <Link href="/detail-minhnhat" className="suggestion-card" id="suggestion-3">
                  <div className="suggestion-card__image">
                    <img
                      src="/Pet sister/portrait-beautiful-chinese-young-girl-hug-her-cute-british-shorthair-cat-who-has-amazing-orange-eyes-home-sunny-afternoon-141149436.webp"
                      alt="Nhà riêng Minh Nhật"
                    />
                  </div>
                  <div className="suggestion-card__name">Nhà riêng Minh Nhật</div>
                  <div className="suggestion-card__price">200.000 VND / ngày</div>
                </Link>
                <Link href="/detail-baotram-house" className="suggestion-card" id="suggestion-4">
                  <div className="suggestion-card__image">
                    <img
                      src="/Pet sister/smiling-young-asian-woman-in-yellow-shirt-is-holding-a-white-ragdoll-cat-over-isolated-white-background-photo.jpg"
                      alt="Nhà riêng Bảo Trâm"
                    />
                  </div>
                  <div className="suggestion-card__name">Nhà riêng Bảo Trâm</div>
                  <div className="suggestion-card__price">160.000 VND / ngày</div>
                </Link>
              </div>
            </div>
          </div>

          <aside className="booking-widget" id="booking-widget">
            <div className="booking-widget__header">
              <div className="booking-widget__price">
                180.000 VND <span>/ ngày</span>
              </div>
              <div className="booking-widget__rating">
                <div className="booking-widget__rating-top">
                  <span className="star">★</span> 4.8
                </div>
                <div className="booking-widget__rating-count">278 đánh giá</div>
              </div>
            </div>

            <div className="booking-widget__dates">
              <div className="booking-widget__date-field">
                <label>Check-in</label>
                <span>4/8/2024</span>
              </div>
              <div className="booking-widget__date-field">
                <label>Checkout</label>
                <span>10/8/2024</span>
              </div>
            </div>

            <div className="booking-widget__pet-field">
              <label>Thú cưng</label>
              <span>2 bé mèo</span>
            </div>

            <button className="booking-widget__cta" id="booking-cta">
              ĐẶT NGAY!
            </button>
            <button className="booking-widget__chat-btn" id="chat-btn">
              <i className="fa-solid fa-comment"></i> Nhắn tin với sitter
            </button>
            <div className="booking-widget__note">Phương thức thanh toán tùy ý</div>

            <div className="booking-widget__breakdown">
              <div className="booking-widget__line">
                <span>180.000 VND x 6 ngày</span>
                <span>1.080.000 VND</span>
              </div>
              <div className="booking-widget__line">
                <span>Dịch vụ tắm gội x 2 lần</span>
                <span>200.000 VND</span>
              </div>
            </div>

            <div className="booking-widget__total">
              <span>Tổng</span>
              <span>1.280.000 VND</span>
            </div>

            <div className="booking-widget__report">
              <svg viewBox="0 0 24 24">
                <path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6h-5.6z"></path>
              </svg>
              Báo cáo đơn hàng này
            </div>
          </aside>
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
        <a href="#" className="bottom-nav__item" id="nav-profile">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
          </svg>
          Profile
        </a>
      </nav>
    </>
  );
}
