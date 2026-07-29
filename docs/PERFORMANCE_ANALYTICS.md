# Báo Cáo Hiệu Suất & Audit Nhân Sự (Performance Analytics & Work Audit)

Module đánh giá hiệu suất cấp quản lý: tổng hợp dữ liệu task/worklog/báo cáo ngày
thật thành Dashboard analytics + Timeline audit theo nhân viên. Phong cách SaaS
Enterprise (Linear / Jira Advanced Reports / Power BI). **Chỉ dùng dữ liệu thật —
không bịa số.**

> Pha 1 (lõi): Executive Dashboard + Audit theo nhân viên + bộ lọc datagrid toolbar.
> Pha sau: màn riêng cho Dự án / Team / Workload.

---

## Truy cập & phân quyền

| Route | Tên | Màn |
|-------|-----|-----|
| `GET /performance` | `performance.index` | Executive Dashboard |
| `GET /performance/audit` | `performance.audit` | Danh sách audit nhân sự (datagrid + KPI) |
| `GET /performance/audit/{employee}` | `performance.audit.show` | Chi tiết timeline audit một nhân sự |

Gate `performance.view` (trong `AuthServiceProvider`) — chỉ `admin | lead | viewer`
(quản lý / ban giám đốc). `member` không truy cập. Nav: nhóm **"Hiệu suất & Audit"**.

---

## Kiến trúc

```
Controller (mỏng)                     Service (thuần, app/Support/Performance/)
─────────────────                     ────────────────────────────────────────
PerformanceDashboardController  ──▶   PerformanceFilter   (giải mã bộ lọc → kỳ + scope)
                                      PerformanceMetrics  (engine KPI/distribution/trend + summary)
                                      PerformanceScorer   (điểm khách quan từ task)
PerformanceAuditController      ──▶   EmployeeAuditListBuilder (danh sách: cam kết / điểm theo kỳ)
                                      EmployeeAuditBuilder (timeline tuần: kế hoạch vs kết quả)
                                      PerformanceTaskScope (truy vấn task + gán nhân sự thống nhất)
```

Controller chỉ `authorize` + gọi service + `Inertia::render`. Service trả mảng đã
shape (giống `HubDashboardController`). Bộ lọc đổi → Inertia partial reload, mọi widget
đồng bộ vì cùng đọc một `PerformanceFilter`.

### Frontend (`resources/js/modules/performance/`)

- `components/` — `PerformanceFilterBar` (datagrid toolbar), `PerformanceDashboardSummaryBar`,
  `PerformanceAuditSummaryBar`, `KpiCard` (+ `Sparkline`, `ProgressRing`),
  `TrendChart`, `StatusDonut`, `WorkloadBars`, `ProjectContributionChart`, `LeaderboardTable`,
  `AuditTimeline` → `WeeklyAuditCard` → `KanbanSnapshot`.
- `composables/` — `useChartTheme` (palette brand + chart options), `usePerformanceExport`
  (Excel `xlsx-js-style`). Count-up tái dùng `@/shared/composables/useCountUp`.
- Pages: `Pages/Performance/Dashboard.vue`, `Pages/Performance/Audit.vue` (index danh sách),
  `Pages/Performance/AuditShow.vue` (chi tiết).

---

## Định nghĩa dữ liệu (nguồn thật)

Mọi chỉ số tính từ `Task` (root tasks: `whereNull('parent_id')`) của nhân sự trong
phạm vi + `Worklog`. Khoảng thời gian `[start, end]` do bộ lọc quyết định.

**Gán nhân sự:** task được tính nếu `assignee_id` thuộc phạm vi **hoặc** có bản ghi
trong `task_assignees`.

**Kỳ Sprint:** `period=sprint` + query `sprint={id}` → task trong kỳ = mọi task có
`sprint_id` khớp (không lọc chéo thêm theo ngày). Dropdown sprint hiện ngay khi chọn
kỳ Sprint trên `PerformanceFilterBar`.

| Khái niệm | Định nghĩa |
|-----------|-----------|
| **Trong kỳ (committed)** | Theo ngày: `due_date` / `completed_at` / `work_started_at` chạm kỳ. Theo sprint: membership `sprint_id`. |
| **Hoàn thành (done)** | committed + `status = Done` + `completed_at` ∈ kỳ |
| **Đúng hạn (onTime)** | done + `completed_at <= due_date` (cuối ngày) |
| **Đang thực hiện** | snapshot hiện tại: `status ∈ {in_progress, in_review}` |
| **Quá hạn** | snapshot: chưa Done + `due_date < hôm nay` |
| **Story point** | tổng `story_points` của done |
| **Giờ ghi nhận** | tổng `Worklog.hours` ∈ kỳ |

Phạm vi nhân sự: `member` → `team` (OrgTeam + con) → `department` → mặc định
**Phòng Công nghệ** (`DashboardPersonnelScope`).

**Kỳ mặc định (tháng):** từ `00:00` ngày đầu tháng đến hết ngày hiện tại (không
kéo tới cuối tháng nếu chưa tới). Nhãn hiển thị: `dd/mm/yyyy 00:00` (PHP
`PerformanceDisplay`, JS `dateAtMidnight` trong `@/composables/useFormat`).

**Bộ lọc datagrid:** dòng filter ẩn lần đầu; bật từng control qua nút **Lọc** (localStorage
`va-workspace.performance.visible-filters.v3`, mặc định tất cả `false`).

---

## Công thức điểm (khách quan, 0–100)

`config/performance.php`:

```
completion = done / committed
onTime     = onTime / done           (chỉ tính trên task đã xong)
quality    = 100 − (blocked / committed)
performance = completion*0.45 + onTime*0.35 + quality*0.20   (trọng số chuẩn hoá)
```

Xếp loại: S ≥ 90, A ≥ 80, B ≥ 65, C ≥ 50, còn lại D. Điểm KPI = `completion`.
Không dùng điểm review chủ quan (DailyReportScore) ở pha này.

## Audit theo nhân viên

**Danh sách (`/performance/audit`):** `EmployeeAuditListBuilder` tổng hợp cam kết / hoàn thành /
điểm / xếp loại cho từng nhân sự trong phạm vi (một lượt query task). Toolbar datagrid: tìm kiếm,
Lọc (mốc thời gian, phòng ban, team), Cột, Xuất; segmented **Tuần | Tháng | Quý**. KPI strip
`PerformanceAuditSummaryBar` (`mode=list`). Bấm dòng → `performance.audit.show`.

**Chi tiết (`/performance/audit/{employee}`):** `EmployeeAuditBuilder` chia kỳ thành **tuần** (kỳ "năm" → tháng) thành các Weekly
Audit Card:

- **Kế hoạch / Cam kết** = task đến hạn hoặc bắt đầu làm trong tuần.
- **Kết quả** = task Done trong tuần (đánh dấu ✔/✘ từng task).
- Tỉ lệ hoàn thành cam kết + điểm (qua `PerformanceScorer`).
- **Định tính**: text `goals_today` / `plan_tomorrow` từ Báo cáo ngày của tuần (hiển
  thị cạnh, không auto-match).
- **Drill-down**: Kanban snapshot toàn bộ task thực tế của tuần.

## Xuất

`usePerformanceExport`: Excel styled (`xlsx-js-style`, brand `#9A0036`) — Dashboard
(Tổng quan + Nhân sự), Audit (theo tuần). Toolbar: **Lọc** + **Xuất** (không nút In/Đặt lại).

Audit: dải KPI `PerformanceAuditSummaryBar` trên index (`mode=list`) và show (`mode=detail`).

---

## Mở rộng (pha sau)

`PerformanceMetrics` đã trả sẵn `people[]` (per-employee) + `workloadDistribution`
+ `projectContribution` để dựng các màn riêng: Báo cáo theo giai đoạn / dự án / team,
Workload & Capacity. Thêm route + page, tái dùng engine — không cần viết lại tính toán.
