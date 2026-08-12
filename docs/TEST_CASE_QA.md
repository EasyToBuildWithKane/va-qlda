# QA / Test Case — Tài liệu module

> **Phiên bản:** LT-QA-01 · Trạng thái: **Live**
>
> Xem thêm: `docs/PROJECT_MANAGEMENT.md §12.2` · `docs/IMPORT_EXPORT_RECONCILE.md` · `routes/web/test-cases.php`

## 1. Tổng quan

Module QA / Test case cho phép nhóm dự án xây dựng và thực thi bộ kiểm thử phần mềm. Mỗi test case có thể thuộc một bộ test (TestSuite), được giao cho một người phụ trách, có danh sách các bước kiểm thử, và theo dõi kết quả qua `TestCaseRun`.

**Luồng chính:**

```
Tạo TestCase (draft) → Chuyển Ready → Thực thi (pass/fail/blocked/skipped)
                                            └─ fail + create_blocker → Blocker mới
```

---

## 2. Models

| Model | Bảng | Mô tả |
|---|---|---|
| `TestCase` | `va_prd_test_cases` | Trường hợp kiểm thử — có `code`, `steps` (JSON), `last_result`, `last_run_at` |
| `TestSuite` | `va_prd_test_suites` | Bộ test (nhóm nhiều TestCase theo tính năng/module) |
| `TestCaseRun` | `va_prd_test_case_runs` | Lịch sử thực thi: kết quả, actual_result, note, người thực thi |

---

## 3. Routes (`routes/web/test-cases.php`)

| Method | URI | Route name | Mô tả |
|---|---|---|---|
| `GET` | `/test-cases` | `test-cases.index` | Index toàn hệ thống (phân trang, lọc server) |
| `POST` | `/test-cases` | `test-cases.store` | Tạo mới |
| `PUT` | `/test-cases/{testCase}` | `test-cases.update` | Cập nhật |
| `DELETE` | `/test-cases/{testCase}` | `test-cases.destroy` | Xóa |
| `POST` | `/test-cases/import` | `test-cases.import` | Nhập hàng loạt từ Excel (bulk, max 200) |
| `POST` | `/test-cases/{testCase}/execute` | `test-cases.execute` | Ghi nhận kết quả thực thi |
| `POST` | `/test-cases/suites` | `test-cases.suites.store` | Tạo bộ test |
| `PUT` | `/test-cases/suites/{suite}` | `test-cases.suites.update` | Cập nhật bộ test |
| `DELETE` | `/test-cases/suites/{suite}` | `test-cases.suites.destroy` | Xóa bộ test |

---

## 4. Enums

| Enum | Values |
|---|---|
| `TestCaseStatus` | `draft` · `ready` · `deprecated` |
| `TestCasePriority` | `low` · `medium` · `high` · `critical` |
| `TestCaseRunResult` | `pass` · `fail` · `blocked` · `skipped` |

---

## 5. Frontend — cấu trúc file

```
resources/js/
├── Pages/TestCase/
│   └── Index.vue                   — Trang /test-cases (server filter, phân trang, KPI strip)
│
├── modules/testcase/components/
│   ├── ProjectTestCasePanel.vue    — Panel tab QA trên Project Show
│   ├── TestCaseSummaryBar.vue      — KPI strip (5 thẻ: total, ready, pass, fail, not_run)
│   ├── TestCaseFormModal.vue       — Tạo/sửa test case (steps editor)
│   ├── TestCaseExecuteModal.vue    — Ghi nhận kết quả thực thi
│   └── TestCaseDataModal.vue       — 3 tab: Nhập / Xuất / Đối soát
│
└── composables/
    ├── useTestCaseExport.js        — Xuất Excel styled (xlsx-js-style)
    ├── useTestCaseImport.js        — Template, parse, validate, preview (marker VA_TESTCASE_IMPORT_V1)
    └── useTestCaseReconcile.js     — Đối soát: ready_without_steps, fail_without_blocker, draft_stale, …
```

---

## 6. Props Inertia — Index page (`TestCase/Index`)

```js
testCases: paginated(TestCaseResource)
filters: { q, status, priority, last_result, project_id, suite_id, owner_id, per_page }
summary: { total, ready, pass, fail, not_run }
options: {
  projects: [],
  employees: [],
  status: TestCaseStatus::options(),
  priority: TestCasePriority::options(),
  runResult: TestCaseRunResult::options(),
}
can: { create: bool }
```

---

## 7. Props Inertia — Project Show (tab QA)

```js
testCases: TestCaseResource[]
testSuites: TestSuiteResource[]
testCaseSummary: { total, ready, pass, fail, not_run }
options.enums.testCaseStatus: []
options.enums.testCasePriority: []
options.enums.testCaseRunResult: []
```

---

## 8. Phân quyền (Policy `TestCasePolicy`)

| Hành động | Quyền yêu cầu |
|---|---|
| `viewAny` | Mọi thành viên đăng nhập |
| `create` | `admin`, `lead` |
| `update` | `admin`, `lead`, hoặc thành viên dự án |
| `delete` | `admin`, `lead` |
| `execute` | Mọi thành viên dự án |
| `import` | `admin`, `lead` |

---

## 9. Import Excel — mẫu VA_TESTCASE_IMPORT_V1

- Sheet **Huong dan** — 10 bước hướng dẫn chi tiết.
- Sheet **Nhap lieu** — marker `VA_TESTCASE_IMPORT_V1` dòng 1, header dòng 5, dữ liệu từ dòng 8.
- Cột bắt buộc: **Tiêu đề**, **Mức độ ưu tiên**.
- Tối đa 200 dòng mỗi lần nhập.
- Server validate mirror client (`ImportTestCaseRequest`).

---

## 10. Đối soát (Reconcile)

Composable `useTestCaseReconcile.js` trả issues theo mã code ổn định:

| Code | Level | Điều kiện |
|---|---|---|
| `ready_without_steps` | warning | Trạng thái Ready nhưng không có bước |
| `fail_without_blocker` | error | Kết quả Fail nhưng không có blocker liên kết |
| `draft_stale` | warning | Trạng thái Draft > 30 ngày không cập nhật |
| `no_owner` | info | Chưa gán người phụ trách |
| `no_expected_result` | warning | Trạng thái Ready nhưng không có kết quả mong đợi |
| `deprecated_with_runs` | info | Deprecated nhưng có kết quả thực thi gần đây |
