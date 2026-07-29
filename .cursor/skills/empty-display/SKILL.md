---
name: empty-display
description: >-
  VA-Workspace empty UI: không hiển thị ký tự «—» trên giao diện user-facing; dùng
  emptyDisplay.js và nhãn tiếng Việt theo ngữ cảnh. Cột Kỳ audit = dropdown +
  nhóm collapse. Bắt buộc khi thêm bảng, export Excel user-facing, hoặc props nullable.
---

# Empty display & cột Kỳ audit — VA-Workspace

Rule: `.cursor/rules/empty-display.mdc` (`alwaysApply: true`)

## Cấm

- Hiển thị `—`, `-`, `N/A` hoặc ô trống im lặng cho dữ liệu thiếu trên UI / Excel user-facing.
- Backend trả `'—'` làm placeholder — dùng `null` / chuỗi rỗng; frontend format.

## Utility (frontend)

`resources/js/shared/utils/emptyDisplay.js`

| Hàm / hằng | Dùng khi |
|------------|----------|
| `displayOrEmpty(value, emptyLabel)` | Mọi ô bảng, subtitle, tooltip |
| `isEmptyDisplayValue(value)` | Kiểm tra placeholder cấm |
| `EMPTY_LABELS.*` | Nhãn chuẩn: team, period, grade, … |
| `auditGradeLabel(grade, hasCommitment)` | Cột xếp loại audit |

**Ví dụ nhãn:** «Chưa gán team», «Chưa chọn kỳ», «Chưa có cam kết», «Chưa cập nhật».

Tham chiếu UX: `Pages/Credential/Index.vue` (username, owner, status).

## Cột Kỳ — Performance Audit (`/performance/audit`)

- Trang: `Pages/Performance/Audit.vue` — nhóm **kỳ con** dạng dòng header thu gọn (pattern giống `Pages/Blocker/Index.vue` theo dự án), không dropdown từng ô.
- Prop hàng: `row.periodBuckets` (backend `EmployeeAuditListBuilder` + `PerformancePeriodBuckets`)
- Bật cột **Kỳ** → ẩn cột Kỳ, hiển thị nhóm line + toolbar «Mở kỳ / Thu kỳ»; tắt cột Kỳ → bảng phẳng theo tổng kỳ.
- Component legacy (không dùng list): `PerformanceAuditPeriodCell.vue` (dropdown) — giữ tham chiếu nếu tái sử dụng.

## Backend

- `App\Support\Performance\PerformancePeriodBuckets::forFilter()`
- List row: `periodBuckets[]` với `label`, `range`, `committed`, `done`, `commitmentRate`, `grade|null`

## Export Excel (performance)

`usePerformanceExport.js` — thay `?? '—'` bằng nhãn tiếng Việt cùng nghĩa với UI (import `EMPTY_LABELS` / helper).

## Checklist PR

- [ ] Không còn `—` user-facing trong diff (grep `—` trong Vue/JS export user-facing)
- [ ] Nullable props có nhãn tiếng Việt
- [ ] Audit list: cột Kỳ bật → nhóm line collapse trên `Audit.vue` (không dropdown ô)
