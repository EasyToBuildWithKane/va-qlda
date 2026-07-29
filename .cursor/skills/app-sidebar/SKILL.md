---
name: app-sidebar
description: >-
  VA-QLDA admin sidebar (Vue): AppChrome shell, rail/expand, mobile drawer, group
  accordion, rail flyout/tooltip, nav from backend Navigation, useAppSidebar.
  Gold: AppSidebar.vue + Layout/AppSidebar*.vue + useAppSidebar.js. Use when
  building, editing, or extending sidebar / shell navigation / layout chrome.
---

# App sidebar (VA-QLDA)

Rule: `.cursor/rules/app-sidebar.mdc` (`alwaysApply: true`)

Chi tiết composable / CSS / status: [reference.md](reference.md)

## Mẫu vàng (bắt buộc đọc trước khi sửa)

| Layer | File |
|-------|------|
| Shell persistent | `resources/js/Layouts/AppChrome.vue` |
| Layout fallback | `resources/js/Layouts/AppLayout.vue` |
| State / ACL active | `resources/js/composables/useAppSidebar.js` |
| Desktop aside | `resources/js/Components/Layout/AppSidebar.vue` |
| Brand + collapse | `AppSidebarBrand.vue` |
| Expanded groups | `AppSidebarExpandedNav.vue` |
| Rail icons | `AppSidebarRailNav.vue` |
| Rail flyout | `AppSidebarRailFlyout.vue` |
| Rail tooltip | `AppSidebarRailTooltip.vue` |
| Mobile drawer | `AppSidebarMobileDrawer.vue` |
| Nav config (BE) | `app/Support/Navigation.php` (+ `NavigationBadges.php`) |
| Inertia share | `HandleInertiaRequests` → `nav` |
| CSS nav | `resources/css/app.css` (`.sidebar-nav-*`) |

**Không remount sidebar theo page** — instance gắn `AppChrome` + `provide(APP_SIDEBAR_KEY)`.

Port / đối chiếu HRM React: cùng ý (rail + drawer + accordion + flyout + persist); stack khác — xem skill `app-sidebar` bên VA-HRM nếu cần parity.

---

## Cấu trúc

```
AppChrome (provide useAppSidebar)
├── AppSidebar (lg+, rail | expanded)
│   ├── AppSidebarBrand
│   ├── AppSidebarRailNav | AppSidebarExpandedNav
│   └── Teleport: RailFlyout + RailTooltip
└── AppSidebarMobileDrawer (< lg)
```

Nav data: `page.props.nav` = groups `{ key?, heading, section?, items[], defaultCollapsed?, variant? }` từ `Navigation::for($account)`.

---

## 1. Responsive

| Breakpoint | UI |
|------------|-----|
| `< lg` (1024) | Desktop aside ẩn; **mobile drawer** (`mobileOpen`) |
| `≥ lg` | `AppSidebar`: `rail ? w-[5.75rem] : w-72` |

- `rail` persist: `localStorage` `va-qlda.sidebar.rail` (`'1'|'0'`).
- Group collapse persist: `va-qlda.sidebar.collapsed` (JSON array keys).
- Đổi route Inertia → đóng drawer + flyout.
- Click outside đóng flyout khi đang rail (`AppChrome` document listener).

---

## 2. Body (thứ tự)

1. **Brand** (`AppSidebarBrand`) — collapse/expand controls.
2. **Nav** — rail icons **hoặc** expanded accordion; scroll hints gradient top/bottom.

User account: topbar `UserMenu` (không footer sidebar).

### Logo collapse (rail) — bắt buộc

| Mode | Asset | Markup |
|------|-------|--------|
| **Expanded / drawer** | `vas-white.png` (wordmark) | full width, drop-shadow |
| **Rail (collapsed)** | `vas-white-mark.png` + `@2x` srcset | ô `h-10 w-10` `rounded-lg bg-white/[0.08] ring-1 ring-white/15` + img `h-7 w-7` class `sidebar-brand-logo` |

**Cấm** thu nhỏ wordmark dài trong rail — phải đổi sang mark. Assets: `public/images/congnghe/brand/`.

Classes: `bg-brand text-brand-100`; active `sidebar-nav-item--active`; scroll `sidebar-nav-scroll`.

---

## 3. Groups & items

**Expanded:** group header toggle (`isOpen` / `toggleGroup`); items Link Inertia; badge status nếu không `live`.

**Rail:** icon group/item → tooltip; mở **flyout** menu group (`AppSidebarRailFlyout`).

**Active:** longest-prefix match trên mọi `href` (`useAppSidebar` `activeHref`) — không dùng `startsWith` mù cho hub nếu sau này thêm sibling leaf (cân nhắc map như HRM).

**Status:** `live | dev | maintenance | planned` (`SIDEBAR_STATUS`); `href === '#'` hoặc `planned` = upcoming UI.

---

## 4. Anti-patterns

- Tự viết sidebar mới trong page thay vì `AppSidebar*` + `useAppSidebar`.
- Remount shell mỗi Inertia visit (phá `sidebarShellInstance`).
- Hard-code menu FE khi đã có `Navigation.php` / Inertia `nav`.
- Rail không flyout/tooltip; width desktop lệch layout content.
- Bỏ `data-tour="sidebar"` nếu tour đang phụ thuộc.
- Rail dùng wordmark dài thay `vas-white-mark` trong ô mark.

---

## 5. Checklist

- [ ] Sửa qua `AppChrome` / `useAppSidebar` / `AppSidebar*` — không fork
- [ ] Nav mới → `Navigation.php` (+ badge nếu cần)
- [ ] Rail + expanded + mobile drawer vẫn đồng bộ props
- [ ] Logo: mark khi rail, wordmark khi expanded/drawer
- [ ] Persist rail / collapsed groups
- [ ] Active path + status badge đúng
- [ ] CSS class `sidebar-nav-*` giữ motion / reduced-motion
