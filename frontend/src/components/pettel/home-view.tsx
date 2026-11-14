/* eslint-disable @next/next/no-img-element -- Preserve the supplied image sizing during migration. */
import Link from "next/link";
import { PageBehavior } from "./page-behavior";

export function HomeView() {
  return (
    <>
      <PageBehavior kind="home" />
      <header className="header" id="header">
    <div className="header-top">
      
      <Link href="/" className="logo" id="logo">
        <span className="logo-icon">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M4.5 11.5C5.88 11.5 7 10.38 7 9S5.88 6.5 4.5 6.5 2 7.62 2 9s1.12 2.5 2.5 2.5zm15 0C20.88 11.5 22 10.38 22 9s-1.12-2.5-2.5-2.5S17 7.62 17 9s1.12 2.5 2.5 2.5zm-11-4C9.88 7.5 11 6.38 11 5S9.88 2.5 8.5 2.5 6 3.62 6 5s1.12 2.5 2.5 2.5zm7 0C16.88 7.5 18 6.38 18 5s-1.12-2.5-2.5-2.5S13 3.62 13 5s1.12 2.5 2.5 2.5zM12 10c-2.76 0-5 2.24-5 5 0 2.05 1.24 3.81 3 4.58V22h4v-2.42c1.76-.77 3-2.53 3-4.58 0-2.76-2.24-5-5-5z"></path>
          </svg>
        </span>
        PETTEL
      </Link>

      
      <div className="search-bar" id="search-bar">
        <input type="text" className="search-bar__input" id="search-input" placeholder="Tìm kiếm theo tên, địa điểm..." />
        <button className="search-bar__btn" id="search-btn" aria-label="Tìm kiếm">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"></path>
          </svg>
        </button>
      </div>

      
      <div className="header-right">
        <Link href="/pet-profile" className="header-link" id="pet-profile-link">Hồ sơ thú cưng</Link>
        <a href="#" className="become-host" id="become-host">Trở thành chủ nhà</a>
        <div className="user-menu" id="user-menu">
          <div className="user-menu__hamburger">
            <span></span><span></span><span></span>
          </div>
          <div className="user-menu__avatar">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="white"></path>
            </svg>
          </div>
        </div>
      </div>
    </div>

    
    <div className="filter-bar" id="filter-bar">
      
      <div className="filter-dropdown-wrapper">
        <div className="filter-dropdown" id="filter-price-btn">
          <span id="filter-price-text">Giá</span>
          <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"></path></svg>
        </div>
        <div className="filter-dropdown-menu" id="filter-price-menu" style={{"display": "none"}}>
          <div className="filter-dropdown-option" data-value="all">Tất cả mức giá</div>
          <div className="filter-dropdown-option" data-value="0-100">Dưới 100.000</div>
          <div className="filter-dropdown-option" data-value="100-200">100.000 – 200.000</div>
          <div className="filter-dropdown-option" data-value="200-300">200.000 – 300.000</div>
          <div className="filter-dropdown-option" data-value="300-400">300.000 – 400.000</div>
          <div className="filter-dropdown-option" data-value="400-500">400.000 – 500.000</div>
          <div className="filter-dropdown-option" data-value="500+">Trên 500.000</div>
        </div>
      </div>

      
      <div className="filter-dropdown-wrapper">
        <div className="filter-dropdown" id="filter-district-btn">
          <span id="filter-district-text">Quận</span>
          <svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"></path></svg>
        </div>
        <div className="filter-dropdown-menu" id="filter-district-menu" style={{"display": "none"}}>
          <div className="filter-dropdown-option" data-value="all">Tất cả quận</div>
          <div className="filter-dropdown-option" data-value="haichau">Hải Châu</div>
          <div className="filter-dropdown-option" data-value="camle">Cẩm Lệ</div>
          <div className="filter-dropdown-option" data-value="sontra">Sơn Trà</div>
          <div className="filter-dropdown-option" data-value="thanhkhe">Thanh Khê</div>
          <div className="filter-dropdown-option" data-value="lienchiều">Liên Chiểu</div>
          <div className="filter-dropdown-option" data-value="ngu hanh son">Ngũ Hành Sơn</div>
        </div>
      </div>

      <div className="filter-divider"></div>

      <button className="filter-pill filter-pill--inactive" id="filter-trongtaigia">Trông tại gia</button>
      <button className="filter-pill filter-pill--inactive" id="filter-guigam">Gửi gắm</button>
      <button className="filter-pill filter-pill--inactive" id="filter-datdidao">Dắt đi dạo</button>

      <div className="filter-divider"></div>

      <button className="filter-pill filter-pill--inactive" id="filter-pro-only">Chỉ PETTEL Pro</button>
      <button className="filter-pill filter-pill--inactive" id="filter-verified-plus">Verified trở lên</button>

      <div className="filter-divider"></div>

      
      <div className="smart-match-container">
        <button className="filter-pill filter-pill--smart-match" id="smart-match-toggle">
          <i className="fa-solid fa-wand-magic-sparkles" style={{"marginRight": "4px"}}></i> Smart Match
        </button>
        <div className="pet-selector-dropdown" id="pet-selector-dropdown" style={{"display": "none"}}>
          <div className="pet-selector-header">
            <span>Chọn thú cưng</span>
          </div>
          <div className="pet-selector-list" id="pet-selector-list">
            
          </div>
          <div className="pet-selector-footer">
            <button className="pet-footer-btn pet-reset-btn" id="pet-reset-btn">Khôi phục</button>
            <button className="pet-footer-btn pet-apply-btn" id="pet-apply-btn">Áp dụng</button>
          </div>
        </div>
      </div>

      <div className="filter-divider"></div>

      <button className="filter-icon-btn" id="toggle-filters-btn" aria-label="Bộ lọc">
        <svg viewBox="0 0 24 24"><path d="M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z"></path></svg>
      </button>
    </div>

    
    <div className="smart-match-banner" id="smart-match-banner" style={{"display": "none"}}>
      <span id="smart-match-text">Bộ lọc đang dựa trên hồ sơ của <strong id="selected-pet-name"></strong> <i className="fa-solid fa-cat"></i></span>
      <button className="smart-match-close" id="smart-match-close">×</button>
    </div>

    
    <div className="advanced-filters" id="advanced-filters">
      <div className="advanced-filters__content">
        
        
        <div className="filter-group">
          <h3 className="filter-group__title">Loại thú cưng</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="cat" data-filter="petType" />
              <span className="filter-checkbox__label">Mèo</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="dog" data-filter="petType" />
              <span className="filter-checkbox__label">Chó</span>
            </label>
          </div>
        </div>

        
        <div className="filter-group">
          <h3 className="filter-group__title">Tính cách</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="friendly" data-filter="personality" />
              <span className="filter-checkbox__label">Thân thiện</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="shy" data-filter="personality" />
              <span className="filter-checkbox__label">Nhút nhát</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="aggressive" data-filter="personality" />
              <span className="filter-checkbox__label">Hung dữ</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="active" data-filter="personality" />
              <span className="filter-checkbox__label">Năng động</span>
            </label>
          </div>
        </div>

        
        <div className="filter-group">
          <h3 className="filter-group__title">Nhu cầu đặc biệt</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="none" data-filter="specialNeeds" />
              <span className="filter-checkbox__label">Không có</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="medical-care" data-filter="specialNeeds" />
              <span className="filter-checkbox__label">Cần chăm sóc y tế</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="medication" data-filter="specialNeeds" />
              <span className="filter-checkbox__label">Cần uống thuốc</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="senior-pet" data-filter="specialNeeds" />
              <span className="filter-checkbox__label">Thú cưng lớn tuổi</span>
            </label>
          </div>
        </div>

        
        <div className="filter-group">
          <h3 className="filter-group__title">Yêu cầu kinh nghiệm</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="basic" data-filter="experienceLevel" />
              <span className="filter-checkbox__label">Chăm sóc cơ bản</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="experienced-with-shy" data-filter="experienceLevel" />
              <span className="filter-checkbox__label">Có kinh nghiệm với thú cưng nhút nhát</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="medical-experience" data-filter="experienceLevel" />
              <span className="filter-checkbox__label">Có kinh nghiệm y tế</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="professional" data-filter="experienceLevel" />
              <span className="filter-checkbox__label">Chuyên nghiệp</span>
            </label>
          </div>
        </div>

        
        <div className="filter-group">
          <h3 className="filter-group__title">Chế độ ăn</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="normal" data-filter="diet" />
              <span className="filter-checkbox__label">Bình thường</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="strict-diet" data-filter="diet" />
              <span className="filter-checkbox__label">Ăn kiêng nghiêm ngặt</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="restricted-food" data-filter="diet" />
              <span className="filter-checkbox__label">Không ăn một số loại</span>
            </label>
          </div>
        </div>

        
        <div className="filter-group">
          <h3 className="filter-group__title">Môi trường</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="indoor-only" data-filter="environment" />
              <span className="filter-checkbox__label">Chỉ ở trong nhà</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="can-go-outside" data-filter="environment" />
              <span className="filter-checkbox__label">Có thể ra ngoài</span>
            </label>
          </div>
        </div>

        
        <div className="filter-group">
          <h3 className="filter-group__title">Thời gian rảnh</h3>
          <div className="filter-group__options">
            <label className="filter-checkbox">
              <input type="checkbox" value="morning" data-filter="availability" />
              <span className="filter-checkbox__label">Buổi sáng</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="afternoon" data-filter="availability" />
              <span className="filter-checkbox__label">Buổi chiều</span>
            </label>
            <label className="filter-checkbox">
              <input type="checkbox" value="all-day" data-filter="availability" />
              <span className="filter-checkbox__label">Cả ngày</span>
            </label>
          </div>
        </div>

        
        <div className="filter-actions">
          <button className="filter-actions__clear" id="clear-filters-btn">Xóa tất cả</button>
          <button className="filter-actions__apply" id="apply-filters-btn">Áp dụng</button>
        </div>

      </div>
    </div>
  </header>

  
  <main className="main-layout" id="main">
    
    <div className="loading-overlay" id="loading-overlay" style={{"display": "none"}}>
      <div className="loading-spinner"></div>
      <p>Đang tải...</p>
    </div>

    
    <div className="listings-panel" id="listings-panel">
      <div className="listings-count" id="listings-count">Đang tải...</div>
      
      
      <div id="listings-container"></div>

      
      <div className="no-results" id="no-results" style={{"display": "none"}}>
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
        </svg>
        <h3>Không tìm thấy kết quả</h3>
        <p>Hãy thử thay đổi bộ lọc để xem thêm sitter</p>
      </div>
    </div>

    
    <div className="map-panel" id="map-panel">
      <img src="/images/danang_map.png" alt="Bản đồ Đà Nẵng" />
      
      <div className="map-marker" style={{"top": "30%", "left": "25%"}}>150.000đ</div>
      <div className="map-marker map-marker--active" style={{"top": "45%", "left": "40%"}}>175.000đ</div>
      <div className="map-marker" style={{"top": "55%", "left": "60%"}}>200.000đ</div>
      <div className="map-marker" style={{"top": "35%", "left": "70%"}}>160.000đ</div>
      <div className="map-marker" style={{"top": "65%", "left": "30%"}}>190.000đ</div>
    </div>

  </main>
    </>
  );
}
