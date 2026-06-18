# Đề xuất nâng cấp UI/UX & responsive — Portal Công Nghệ (`/congnghe`)

> Phạm vi: `resources/js/Pages/Congnghe/` (Index + partials). Trang landing dark
> portal cho toàn bộ người dùng đã đăng nhập. Xem thêm `docs/CONGNGHE_CONTENT.md`.

## 1. Đánh giá hiện trạng

Trang đã ở mức kỹ thuật cao và phần lớn **đã responsive tốt**:

- Navbar grid 3 cột desktop + mobile drawer (Teleport, khoá scroll, Esc) —
  `CongngheNavbar.vue`.
- Hero `min-h-[100dvh]`, mascot ẩn dưới `lg`, KPI grid 3 cột co giãn —
  `HeroSection.vue`.
- **Sơ đồ tổ chức là luồng dọc** (card `w-full` wrap, children
  `grid-cols-1 → sm:grid-cols-2 → lg:grid-cols-3`), bleed giới hạn
  `calc(100vw - 1.5rem)` ⇒ **không cuộn ngang trên mobile** —
  `CongngheOrgChartBranch.vue`. (Khác với lo ngại ban đầu: phần này đã ổn.)
- Hiệu năng có nền tảng tốt: 1 rAF / 1 mousemove / 1 scroll **dùng chung**
  (`motion.js`), `IntersectionObserver` gate particle off-screen, fallback SVG
  tĩnh khi `prefers-reduced-motion`, parallax/tilt tự tắt khi không có con trỏ tinh.

### Vấn đề thực tế phát hiện

| # | Vấn đề | Bằng chứng | Mức |
|---|--------|-----------|-----|
| 1 | Padding dọc không đồng nhất + **thiếu `scroll-mt`** ⇒ bấm nav nhảy tới section, tiêu đề bị navbar cố định (88px) che | `AILabSection`, `ProductEcosystem`, `ProjectTimeline`, `TechStack` dùng `py-20` cứng, không `scroll-mt`; `RoadmapSection` `py-20` cứng | P0 |
| 2 | Particle chỉ tắt theo OS `prefers-reduced-motion`, **không hạ tải theo lớp thiết bị** ⇒ mobile tầm trung chạy đủ canvas (vẽ link O(n²)) ở mọi section | `SectionParticleNetwork.vue` cũ: `useStatic = prefersReducedMotionNow()` | P1 |
| 3 | **Không có công tắc giảm hiệu ứng** trong UI cho người không bật ở OS | — | P1 |
| 4 | Modal thành viên & mobile drawer chưa **giữ focus** (focus trap) trong dialog | `OrgChartSection.vue`, `CongngheNavbar.vue` (có Esc + overlay nhưng focus thoát ra ngoài) | P2 |

## 2. Đã triển khai (đợt này)

### P0 — Responsive

- Chuẩn hoá nhịp dọc về `py-16 sm:py-20 md:py-24` và thêm
  `scroll-mt-24 sm:scroll-mt-28` cho 5 section: `AILabSection`,
  `ProductEcosystem`, `ProjectTimeline`, `TechStack`, `RoadmapSection`.
  Khớp với chuẩn sẵn có ở `AboutSection` / `ImpactMetrics` / `CultureSection`.

### P1 — Hiệu năng + công tắc

- **`useCongngheMotion.js`** (mới): thiết lập chuyển động dùng chung, gộp
  3 nguồn → 1 computed `congngheMotionReduced`:
  1. OS `prefers-reduced-motion`.
  2. **Thiết bị yếu** (coarse pointer + `hardwareConcurrency ≤ 4` hoặc
     `deviceMemory ≤ 4`) ⇒ tự hạ về nền tĩnh.
  3. **Override người dùng** (`localStorage: congnghe:motion-pref` =
     `auto | reduced | full`).
- **`SectionParticleNetwork.vue`**: đọc `congngheMotionReduced` thay vì chỉ OS;
  refactor sang `initCanvas()` / `destroyCanvas()` + `watch` ⇒ bật/tắt particle
  **tức thời** khi đổi công tắc (không cần tải lại trang).
- **`CongngheFooter.vue`**: nút "Hiệu ứng: Đầy đủ / Giảm" ở thanh dưới
  (`aria-pressed`, lưu lựa chọn).

## 3. P2 — Đã triển khai (đợt 2)

- **`useCongngheFocusTrap.js`** (mới): composable bẫy focus dùng chung — lưu
  trigger, đưa focus vào dialog, vòng Tab/Shift+Tab, trả focus khi đóng. Áp vào
  **modal thành viên** (`OrgChartSection`), **modal chi tiết dự án**
  (`CongngheProjectDetailModal`) và **mobile drawer** (`CongngheNavbar`).
- **`CongngheSectionRail.vue`** (mới): dot-rail dọc ở mép phải, chỉ hiện
  `< lg`, dùng `useCongngheSectionSpy` để sáng dot theo section đang xem; gắn
  trong `Index.vue` từ `content.nav.links`.

## 4. Còn lại — ưu tiên thấp

- **Mở rộng phạm vi công tắc**: parallax/tilt/magnetic hiện chỉ theo OS
  reduced-motion. Có thể cho chúng cùng đọc `congngheMotionReduced` để công tắc
  "Giảm" tắt luôn hiệu ứng con trỏ (đã tự tắt trên cảm ứng nên ưu tiên thấp).

## 5. Kiểm thử nhanh

- DevTools → throttling + giả lập cảm ứng/ít core ⇒ particle chuyển nền tĩnh.
- Bấm các mục nav (Sản phẩm, Công nghệ, AI Lab, Lộ trình) ⇒ tiêu đề không bị
  navbar che.
- Nút "Hiệu ứng" ở footer ⇒ particle dừng/chạy ngay, trạng thái giữ sau khi reload.
