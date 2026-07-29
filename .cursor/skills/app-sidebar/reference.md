# App sidebar VA-QLDA — reference

Bổ sung cho [SKILL.md](SKILL.md).

## `useAppSidebar` API (tóm tắt)

Singleton `sidebarShellInstance` — lần gọi đầu tạo, sau đó reuse.

| Export | Vai trò |
|--------|---------|
| `nav`, `user`, `appShortName`, `appName`, `appVersion` | Từ Inertia props |
| `rail`, `isMobile`, `mobileOpen` | Layout modes |
| `openMobile` / `closeMobile` / `toggleMobile` | Drawer |
| `isActive`, `activeHref`, `groupContainsActive` | Highlight |
| `groupKey`, `isOpen`, `toggleGroup`, `collapsed` | Accordion |
| `isUpcomingGroup`, `isPlanned`, `showBadge`, `statusOf`, `showRailStatus`, `railTone` | Status UI |
| `tip` / `showTip` / `hideTip` | Rail tooltip |
| `flyout` / `openFlyout` / `scheduleFlyout` / `closeFlyout` / … | Rail flyout |
| `sidebarNavRef`, `sidebarScrollEdges`, `onSidebarNavScroll` | Overflow hints |

Keys storage:

- `va-qlda.sidebar.rail`
- `va-qlda.sidebar.collapsed`

`MOBILE_BREAKPOINT = 1024`.

## Active href

```js
// longest matching href where url === href || url.startsWith(href + '/')
```

Thêm route con cùng prefix với hub khác → kiểm tra lại thứ tự/href dài hơn.

## Nav backend

- Build: `app/Support/Navigation.php` → `Navigation::for($account)`
- Badges: `NavigationBadges::decorate(...)`
- Share: `HandleInertiaRequests` prop `nav`

Group shape điển hình: `key`, `heading`, `section`, `items[{ href, label, icon, status? }]`, `defaultCollapsed?`, `variant?: 'upcoming'`.

## CSS (`app.css`)

- `.sidebar-nav-scroll` — thin scrollbar
- `.sidebar-nav-icon` / `.sidebar-nav-icon-shell` — hover translate + active pop
- `.sidebar-nav-item--active` — glow / pop animation
- `prefers-reduced-motion` — tắt transform/animation icon

## Width

| Mode | Class |
|------|-------|
| Rail | `w-[5.75rem]` |
| Expanded | `w-72` |

Đổi width → cập nhật layout content offset / flyout `left` nếu hard-code (tooltip/flyout dùng `getBoundingClientRect`).

## Logo collapse (parity HRM)

```
Expanded / drawer → /images/congnghe/brand/vas-white.png
Rail              → vas-white-mark.png (+ @2x) trong shell:
  h-10 w-10 rounded-lg bg-white/[0.08] p-1.5
  ring-1 ring-inset ring-white/15
  img.sidebar-brand-logo h-7 w-7
```

CSS `.sidebar-brand-logo` — brightness/contrast + hover scale nhẹ.

## A11y

- Collapse/expand brand: `aria-label` tiếng Việt
- Flyout: `role="menu"` (xem `AppSidebarRailFlyout`)
- Focus-visible ring accent trên control rail
- Đóng flyout: click ngoài (AppChrome) + đổi route
- Rail mark button: `aria-label="Mở rộng thanh bên"` + `title` = appName
