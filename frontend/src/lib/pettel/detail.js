import { sittersData } from "./demo-data";
/** @param {import("./page-scope").PageScope} scope */
export default function initialize(scope) {
    // ===== User Profile Management =====
    function initUserProfile() {
        const defaultUser = {
            name: "Tấn Anh",
            phone: "0909123456",
            email: "tananh@gmail.com",
            address: "Đà Nẵng",
            gender: "Nam"
        };
        let userData = localStorage.getItem('pettel_user');
        if (!userData) {
            localStorage.setItem('pettel_user', JSON.stringify(defaultUser));
            userData = JSON.stringify(defaultUser);
        }
        return JSON.parse(userData);
    }
    function saveUserProfile(userData) {
        localStorage.setItem('pettel_user', JSON.stringify(userData));
    }
    function loadUserProfileToForm() {
        const userData = initUserProfile();
        document.getElementById('profile-name').value = userData.name;
        document.getElementById('profile-phone').value = userData.phone;
        document.getElementById('profile-email').value = userData.email || '';
        document.getElementById('profile-address').value = userData.address;
        document.getElementById('profile-gender').value = userData.gender;
    }
    function showUserProfile() {
        loadUserProfileToForm();
        document.getElementById('user-profile-modal').style.display = 'flex';
    }
    function hideUserProfile() {
        document.getElementById('user-profile-modal').style.display = 'none';
    }
    function updateContactInfo() {
        const userData = initUserProfile();
        document.getElementById('contact-name').textContent = userData.name;
        document.getElementById('contact-phone').textContent = userData.phone.replace(/(\d{4})(\d{3})(\d{3})/, '$1xxx$3');
        document.getElementById('contact-address').textContent = userData.address;
        document.getElementById('booking-contact-info').style.display = 'block';
    }
    // ===== Date Picker Logic =====
    function getTomorrow() {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        return tomorrow;
    }
    function addDays(date, days) {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    }
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    function calculateDays(checkin, checkout) {
        const diff = checkout - checkin;
        return Math.ceil(diff / (1000 * 60 * 60 * 24));
    }
    function initDatePicker() {
        const checkinInput = document.getElementById('booking-checkin');
        const checkoutInput = document.getElementById('booking-checkout');
        const tomorrow = getTomorrow();
        const checkoutDefault = addDays(tomorrow, 2);
        checkinInput.value = formatDate(tomorrow);
        checkoutInput.value = formatDate(checkoutDefault);
        checkinInput.min = formatDate(new Date());
        checkoutInput.min = formatDate(tomorrow);
        scope.listen(checkinInput, 'change', () => {
            const checkinDate = new Date(checkinInput.value);
            const newCheckout = addDays(checkinDate, 2);
            checkoutInput.value = formatDate(newCheckout);
            checkoutInput.min = formatDate(addDays(checkinDate, 1));
            updatePriceBreakdown();
        });
        scope.listen(checkoutInput, 'change', () => {
            updatePriceBreakdown();
        });
        // Quick select buttons
        document.querySelectorAll('.quick-date-btn').forEach(btn => {
            scope.listen(btn, 'click', () => {
                const days = parseInt(btn.dataset.days);
                const checkinDate = new Date(checkinInput.value);
                const newCheckout = addDays(checkinDate, days);
                checkoutInput.value = formatDate(newCheckout);
                updatePriceBreakdown();
            });
        });
    }
    // ===== Price Calculation =====
    let currentSitterPrice = 175000;
    function updatePriceBreakdown() {
        const checkinInput = document.getElementById('booking-checkin');
        const checkoutInput = document.getElementById('booking-checkout');
        if (!checkinInput.value || !checkoutInput.value)
            return;
        const checkinDate = new Date(checkinInput.value);
        const checkoutDate = new Date(checkoutInput.value);
        const days = calculateDays(checkinDate, checkoutDate);
        if (days <= 0) {
            alert('Check-out phải sau Check-in');
            return;
        }
        const subtotal = currentSitterPrice * days;
        document.getElementById('price-per-day-text').textContent =
            `${currentSitterPrice.toLocaleString('vi-VN')} VND x ${days} ngày`;
        document.getElementById('price-subtotal').textContent =
            `${subtotal.toLocaleString('vi-VN')} VND`;
        document.getElementById('price-total').textContent =
            `${subtotal.toLocaleString('vi-VN')} VND`;
    }
    // ===== Load Dynamic Data from URL =====
    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }
    function getTrustBadgeHTML(trustLevel) {
        if (trustLevel === 'pro') {
            return '<div class="trust-badge trust-badge--pro"><span class="badge-icon"><i class="fa-solid fa-medal"></i></span> PETTEL Pro</div>';
        }
        else if (trustLevel === 'verified') {
            return '<div class="trust-badge trust-badge--verified"><span class="badge-icon"><i class="fa-solid fa-shield-check"></i></span> PETTEL Verified</div>';
        }
        else if (trustLevel === 'new') {
            return '<div class="trust-badge trust-badge--new"><span class="badge-icon"><i class="fa-solid fa-user"></i></span> New Member</div>';
        }
        return '';
    }
    function loadSitterDetails() {
        const sitterId = parseInt(getQueryParam('id')) || 3;
        if (!sitterId || typeof sittersData === 'undefined') {
            scope.navigate('/detail?id=3');
            return;
        }
        const sitter = sittersData.find(s => s.id === sitterId);
        if (!sitter) {
            alert('Không tìm thấy sitter với ID này');
            scope.navigate('/');
            return;
        }
        // Store current sitter price for calculation
        currentSitterPrice = sitter.price;
        // Update page title
        document.title = `${sitter.name} | PETTEL`;
        // Update hero image
        const heroImg = document.querySelector('.detail-hero img');
        if (heroImg) {
            heroImg.src = sitter.image;
            heroImg.alt = sitter.name;
        }
        // Update title section with trust badge
        const titleSection = document.getElementById('detail-title');
        if (titleSection) {
            const petTypeLabels = sitter.petType.map(pet => pet === 'cat' ? '<i class="fa-solid fa-cat"></i> Mèo' : '<i class="fa-solid fa-dog"></i> Chó').join(' & ');
            const trustBadge = getTrustBadgeHTML(sitter.trustLevel);
            titleSection.innerHTML = `
          <h1>${sitter.name} ${trustBadge}</h1>
          <div class="detail-title__meta">
            <span class="star">★</span>
            <span style="font-weight: 600;">${sitter.rating}</span>
            <span class="dot">·</span>
            <a href="#reviews"><strong>${sitter.reviewCount.toLocaleString('vi-VN')} đánh giá</strong></a>
            <span class="dot">·</span>
            <span>${petTypeLabels}</span>
            <span class="dot">·</span>
            <span>${sitter.location}</span>
          </div>
        `;
        }
        // Update description
        const descSection = document.getElementById('detail-description');
        if (descSection) {
            const experienceLabels = {
                'basic': 'Chăm sóc cơ bản',
                'experienced-with-shy': 'Có kinh nghiệm với thú cưng nhút nhát',
                'medical-experience': 'Có kinh nghiệm y tế',
                'professional': 'Chuyên nghiệp'
            };
            const environmentLabel = sitter.environment === 'indoor-only' ? 'Chỉ ở trong nhà' : 'Có thể ra ngoài';
            descSection.innerHTML = `
          <p>
            Chào mừng bạn đến với ${sitter.type.toLowerCase()} của ${sitter.name}, 
            tọa lạc tại ${sitter.location}. ${sitter.status}. 
          </p>
          <p style="margin-top: 12px;">
            <strong>Kinh nghiệm:</strong> ${experienceLabels[sitter.experienceLevel]}<br>
            <strong>Môi trường:</strong> ${environmentLabel}<br>
            <strong>Thời gian rảnh:</strong> ${sitter.availability.map(a => {
                if (a === 'morning')
                    return 'Buổi sáng';
                if (a === 'afternoon')
                    return 'Buổi chiều';
                if (a === 'all-day')
                    return 'Cả ngày';
            }).join(', ')}
          </p>
        `;
        }
        // Update amenities
        const amenitiesSection = document.querySelector('.detail-amenities .amenities-grid');
        if (amenitiesSection) {
            amenitiesSection.innerHTML = sitter.amenities.map(amenity => `
          <div class="amenity-item">
            <div class="icon"><i class="fa-solid fa-star"></i></div>
            <span>${amenity}</span>
          </div>
        `).join('');
        }
        // Update Trust & Verification section
        const trustGrid = document.getElementById('trust-verification-grid');
        if (trustGrid && sitter.verifiedDetails) {
            trustGrid.innerHTML = `
          <div class="trust-item ${sitter.verifiedDetails.idCheck ? 'verified' : ''}">
            <span class="trust-icon"><i class="fa-solid fa-${sitter.verifiedDetails.idCheck ? 'check-circle' : 'times-circle'}"></i></span>
            <span>ID Check</span>
          </div>
          <div class="trust-item ${sitter.verifiedDetails.backgroundCheck ? 'verified' : ''}">
            <span class="trust-icon"><i class="fa-solid fa-${sitter.verifiedDetails.backgroundCheck ? 'check-circle' : 'times-circle'}"></i></span>
            <span>Background Check</span>
          </div>
          <div class="trust-item ${sitter.verifiedDetails.hasCertificate ? 'verified' : ''}">
            <span class="trust-icon"><i class="fa-solid fa-${sitter.verifiedDetails.hasCertificate ? 'check-circle' : 'times-circle'}"></i></span>
            <span>Certificate</span>
          </div>
          <div class="trust-item ${sitter.verifiedDetails.liveUpdates ? 'verified' : ''}">
            <span class="trust-icon"><i class="fa-solid fa-${sitter.verifiedDetails.liveUpdates ? 'check-circle' : 'times-circle'}"></i></span>
            <span>Live Updates</span>
          </div>
        `;
            document.getElementById('verified-reviews-count').innerHTML = `
          <strong>${sitter.verifiedDetails.verifiedReviewCount}</strong> đánh giá từ booking đã hoàn thành
        `;
        }
        // Update confidence score
        const confidenceSection = document.getElementById('confidence-score-section');
        if (confidenceSection) {
            const matchPercent = 95;
            confidenceSection.innerHTML = `
          <div class="confidence-score-title">Confidence Score</div>
          <div class="confidence-score-value">${sitter.confidenceScore} <span>/ 100</span></div>
          <div class="confidence-breakdown">
            <div class="breakdown-item"><i class="fa-solid fa-star"></i> Reviews: ${sitter.rating} (${sitter.reviewCount})</div>
            <div class="breakdown-item"><i class="fa-solid fa-check-circle"></i> Trust Level: ${sitter.trustLevel === 'pro' ? 'PETTEL Pro' : sitter.trustLevel === 'verified' ? 'PETTEL Verified' : 'New Member'}</div>
            <div class="breakdown-item"><i class="fa-solid fa-bullseye"></i> Match with your pet: ${matchPercent}%</div>
          </div>
        `;
        }
        // Update review summary
        const reviewSummary = document.querySelector('.reviews-summary');
        if (reviewSummary) {
            reviewSummary.innerHTML = `
          <span class="star">★</span>
          <span class="score">${sitter.rating}</span>
          <span class="dot" style="color: #999;">·</span>
          <span>${sitter.reviewCount.toLocaleString('vi-VN')} đánh giá</span>
        `;
        }
        // Update booking price
        const priceElement = document.querySelector('.booking-widget__price');
        if (priceElement) {
            priceElement.innerHTML = `${sitter.price.toLocaleString('vi-VN')} VND <span>/ ngày</span>`;
        }
        // Chat button handler
        const chatBtn = document.getElementById('chat-btn');
        if (chatBtn) {
            scope.listen(chatBtn, 'click', () => {
                scope.navigate(`/chat?id=${sitter.id}`);
            });
        }
        // Initialize booking features
        initDatePicker();
        updateContactInfo();
        updatePriceBreakdown();
        // Booking button handler - Show confirmation modal
        const bookingBtn = document.getElementById('booking-cta');
        const bookingModal = document.getElementById('booking-modal');
        const bookingSuccessModal = document.getElementById('booking-success-modal');
        if (bookingBtn && bookingModal) {
            scope.listen(bookingBtn, 'click', () => {
                // Load current booking info to confirmation modal
                const userData = initUserProfile();
                const checkinInput = document.getElementById('booking-checkin');
                const checkoutInput = document.getElementById('booking-checkout');
                // Update confirmation modal
                document.getElementById('confirm-name').textContent = userData.name;
                document.getElementById('confirm-phone').textContent = userData.phone;
                document.getElementById('confirm-address').textContent = userData.address;
                const checkinDate = new Date(checkinInput.value);
                const checkoutDate = new Date(checkoutInput.value);
                document.getElementById('confirm-checkin').textContent = checkinDate.toLocaleDateString('vi-VN');
                document.getElementById('confirm-checkout').textContent = checkoutDate.toLocaleDateString('vi-VN');
                const days = calculateDays(checkinDate, checkoutDate);
                document.getElementById('confirm-days').textContent = days;
                const subtotal = currentSitterPrice * days;
                document.getElementById('confirm-price-breakdown').textContent =
                    `${currentSitterPrice.toLocaleString('vi-VN')} VND × ${days} ngày`;
                document.getElementById('confirm-total').textContent =
                    `${subtotal.toLocaleString('vi-VN')} VND`;
                // Show confirmation modal
                bookingModal.style.display = 'flex';
            });
        }
        // Confirmation modal buttons
        const modalBack = document.getElementById('booking-modal-back');
        const modalConfirm = document.getElementById('booking-modal-confirm');
        const successClose = document.getElementById('booking-success-close');
        if (modalBack && bookingModal) {
            scope.listen(modalBack, 'click', () => {
                bookingModal.style.display = 'none';
            });
        }
        if (modalConfirm && bookingModal && bookingSuccessModal) {
            scope.listen(modalConfirm, 'click', () => {
                bookingModal.style.display = 'none';
                bookingSuccessModal.style.display = 'flex';
            });
        }
        if (successClose && bookingSuccessModal) {
            scope.listen(successClose, 'click', () => {
                bookingSuccessModal.style.display = 'none';
            });
        }
    }
    const facebookAccounts = [
        { name: "Nguyễn Khánh Huy", image: "/facebook/ảnh 1.png", link: "https://www.facebook.com/share/1QUyu8Nai7/?mibextid=wwXIfr" },
        { name: "Linh Trần", image: "/facebook/ảnh 2.png", link: "https://www.facebook.com/share/1BXt5VEgjR/?mibextid=wwXIfr" },
        { name: "Bùi Tuấn", image: "/facebook/ảnh 3.png", link: "https://www.facebook.com/share/1BHw3skLWh/?mibextid=wwXIfr" },
        { name: "Minh Anh", image: "/facebook/ảnh 4.jpg", link: "https://www.facebook.com/share/1J99riKwe3/?mibextid=wwXIfr" },
        { name: "Hồng Nhung", image: "/facebook/ảnh 5.jpg", link: "https://www.facebook.com/share/1J8vrmpS5R/?mibextid=wwXIfr" },
        { name: "Hoàng Phúc", image: "/facebook/ảnh 6.jpg", link: "https://www.facebook.com/share/1DhyrsdHLX/?mibextid=wwXIfr" },
        { name: "Thuỳ Dương", image: "/facebook/ảnh 7.jpg", link: "https://www.facebook.com/share/1CppRaNv9o/?mibextid=wwXIfr" },
        { name: "Gia Hân", image: "/facebook/ảnh 8.jpg", link: "https://www.facebook.com/share/1BfHQV6hgo/?mibextid=wwXIfr" },
        { name: "Văn Đức", image: "/facebook/ảnh 9.jpg", link: "https://www.facebook.com/share/1BMuFWaKgP/?mibextid=wwXIfr" },
        { name: "Phương Thảo", image: "/facebook/ảnh 10.jpg", link: "https://www.facebook.com/share/18TZgEuUc7/?mibextid=wwXIfr" },
        { name: "Thanh Tùng", image: "/facebook/ảnh 11.png", link: "https://www.facebook.com/share/1DwjWnPjdk/?mibextid=wwXIfr" },
        { name: "Oanh Kiều", image: "/facebook/ảnh 12.jpg", link: "https://www.facebook.com/share/18jS71P5f5/?mibextid=wwXIfr" },
        { name: "Quang Vinh", image: "/facebook/ảnh 13.jpg", link: "https://www.facebook.com/share/1YCDnv97gx/?mibextid=wwXIfr" },
        { name: "Hải Yến", image: "/facebook/ảnh 14.jpg", link: "https://www.facebook.com/share/1CMAW3Evkm/?mibextid=wwXIfr" },
        { name: "Trúc Anh", image: "/facebook/ảnh 15.jpg", link: "https://www.facebook.com/share/1BZgZejnHb/?mibextid=wwXIfr" },
        { name: "Thị Ly", image: "/facebook/ảnh 16.jpg", link: "https://www.facebook.com/share/1UrXDcrp49/?mibextid=wwXIfr" },
        { name: "Bảo Ngọc", image: "/facebook/ảnh 17.jpg", link: "https://www.facebook.com/share/1F9DmohZK3/?mibextid=wwXIfr" },
        { name: "Anh Duy", image: "/facebook/ảnh 18.jpg", link: "https://www.facebook.com/share/18aEosTLX6/?mibextid=wwXIfr" },
        { name: "Hoài An", image: "/facebook/ảnh 19.jpg", link: "https://www.facebook.com/share/1Cozp269ZJ/?mibextid=wwXIfr" },
    ];
    function resolveFacebookImagePath(path) {
        return path.split('/').map(encodeURIComponent).join('/');
    }
    function assignFacebookAccountsToReviews() {
        const reviewIds = ['review-1', 'review-2', 'review-3'];
        const shuffled = facebookAccounts
            .map((account) => ({ account, sort: Math.random() }))
            .sort((a, b) => a.sort - b.sort)
            .map((item) => item.account);
        const selected = shuffled.slice(0, reviewIds.length);
        reviewIds.forEach((reviewId, index) => {
            const account = selected[index];
            const review = document.getElementById(reviewId);
            if (!review || !account)
                return;
            const linkAnchor = review.querySelector('.facebook-account-link');
            const photoAnchor = review.querySelector('.facebook-photo-link');
            const photoImg = review.querySelector('.review-card__photo img');
            const imageUrl = resolveFacebookImagePath(account.image);
            if (linkAnchor) {
                linkAnchor.href = account.link;
                linkAnchor.setAttribute('aria-label', `Facebook ${account.name}`);
            }
            if (photoAnchor) {
                photoAnchor.href = account.link;
                photoAnchor.setAttribute('aria-label', `Facebook ${account.name}`);
            }
            if (photoImg) {
                photoImg.src = imageUrl;
                photoImg.alt = account.name;
            }
        });
    }
    // ===== Event Listeners =====
    scope.listen(document, 'DOMContentLoaded', () => {
        // Initialize user profile
        initUserProfile();
        // User profile button (top right)
        scope.listen(document.getElementById('user-profile-btn'), 'click', showUserProfile);
        scope.listen(document.getElementById('user-profile-close'), 'click', hideUserProfile);
        scope.listen(document.getElementById('contact-edit-btn'), 'click', showUserProfile);
        // Bottom nav profile button
        const navProfile = document.getElementById('nav-profile');
        if (navProfile) {
            scope.listen(navProfile, 'click', (e) => {
                e.preventDefault();
                showUserProfile();
            });
        }
        // User profile form submit
        scope.listen(document.getElementById('user-profile-form'), 'submit', (e) => {
            e.preventDefault();
            const userData = {
                name: document.getElementById('profile-name').value,
                phone: document.getElementById('profile-phone').value,
                email: document.getElementById('profile-email').value,
                address: document.getElementById('profile-address').value,
                gender: document.getElementById('profile-gender').value
            };
            saveUserProfile(userData);
            updateContactInfo();
            hideUserProfile();
            // Show success modal
            document.getElementById('profile-success-modal').style.display = 'flex';
        });
        // Profile success modal close
        const profileSuccessClose = document.getElementById('profile-success-close');
        const profileSuccessModal = document.getElementById('profile-success-modal');
        if (profileSuccessClose && profileSuccessModal) {
            scope.listen(profileSuccessClose, 'click', () => {
                profileSuccessModal.style.display = 'none';
            });
        }
        // Load sitter details
        loadSitterDetails();
        assignFacebookAccountsToReviews();
    });
}
