# CONGNGHE CONTENT — Quản trị nội dung trang /congnghe

> Module quản trị (admin-only) cho phép chỉnh **toàn bộ nội dung tĩnh** của landing
> Phòng Công Nghệ mà không cần sửa code / deploy lại.
> Đường dẫn: **`/congnghe/quan-tri`** · Nav: **Quản trị → Trang Công Nghệ**.

---

## 1. Mục tiêu

Trang `/congnghe` trộn 2 loại nội dung:

- **Tĩnh** (trước đây hard-code trong Vue): hero, giới thiệu, công nghệ, AI Lab, văn hoá, lộ trình, navbar, footer, tiêu đề mục, nhãn số liệu.
- **Dữ liệu thật** từ DB: số liệu thống kê, sản phẩm (dự án hoàn thành), vòng đời dự án, sơ đồ tổ chức.

Module này đưa phần **tĩnh** + **nhãn/tiêu đề/hiển thị/thứ tự** của phần dữ liệu thật về một nơi chỉnh được. **Số liệu thật vẫn tính tự động từ DB** — admin chỉ sửa nhãn/heading, chọn chỉ số nào hiển thị, không ghi đè con số.

Nguyên tắc (giống `docs/SYSTEM_CONFIG.md`):

- **Default là single source of truth** trong `config/congnghe.php`.
- **DB chỉ lưu override.** Bảng `va_prd_congnghe_sections` trống ⇒ trang hiển thị y hệt default.
- **Admin-only.** Policy chặn backend; nav chỉ hiện cho admin.

---

## 2. Kiến trúc & luồng

```
Giao diện (Vue)                  Tính năng (Laravel)               Phân quyền
─────────────                    ──────────────────               ──────────
Pages/CongngheAdmin/Index.vue    CongngheAdminController            CongngheContentPolicy
  ├─ partials/SectionEditor       ├─ index()  → forAdmin()           viewAny / manage = admin
  ├─ partials/RepeatableList      ├─ update($section)                (map ở AuthServiceProvider,
  └─ partials/IconPicker          ├─ reset($section)                 model CongngheSection)
                                  └─ reorder()
                                     ↓
                                  CongngheContentRepository (singleton)
                                     ├─ forPublic()  (merge + lọc + sắp xếp)
                                     ├─ forAdmin()   (tất cả + default + cờ)
                                     └─ save / reorder / reset → cache forget
                                       ↓
                                  congnghe_sections (DB, chỉ override)
                                       ↑ default
                                  config/congnghe.php  +  CongngheContentSchema (rules/editor)
                                       ↓
              CongngheController@__invoke → prop "content" + "icons" + dữ liệu thật
                                       ↓
              Pages/Congnghe/Index.vue → <component :is> theo content.sections (đã sắp + lọc)
```

**Merge:** object/assoc → merge theo key; **mảng list** (links, items, pillars…) → **thay nguyên cụm**. Nếu nội dung lưu trùng default thì `save()` lưu `content = null` (chỉ giữ cờ hiển thị/vị trí) ⇒ section không bị đánh dấu "đã ghi đè" và vẫn nhận default mới về sau.

**Cache:** `CongngheContentRepository` cache override (`Cache::rememberForever('congnghe.sections')`); mọi `save/reorder/reset` tự `forget`. Bảng chưa tồn tại (lúc migrate) ⇒ trả default.

---

## 3. Các section

| key | Loại | orderable | Nội dung chỉnh được |
|-----|------|-----------|---------------------|
| `meta` | chrome | ✗ | Tiêu đề trang (`<Head>`) |
| `nav` | chrome | ✗ | Tên/khẩu hiệu thương hiệu, 6 link điều hướng |
| `hero` | chrome (ghim đầu) | ✗ | Tiêu đề (3 phần), mô tả, 2 CTA, 3 chỉ số nổi bật |
| `about` | main | ✓ | Heading, ghi chú, 3 trụ cột (tag/title/body/icon) |
| `impact` | main | ✓ | Heading, nhãn LIVE, 6 thẻ số liệu (chỉ số/nhãn/phụ đề/màu/hậu tố) |
| `products` | main | ✓ | Heading (danh sách = dự án hoàn thành từ DB) |
| `tech` | main | ✓ | Heading, 4 nhóm công nghệ (tên/slug/danh sách) |
| `org` | main | ✓ | Heading, nhãn số liệu (sơ đồ từ DB) |
| `lifecycle` | main | ✓ | Heading, mô tả 3 giai đoạn (dự án từ DB) |
| `ai_lab` | main | ✓ | Heading, 3 sáng kiến (title/body/icon) |
| `culture` | main | ✓ | Heading, 6 giá trị (title/body/icon) |
| `roadmap` | main | ✓ | Heading, nhãn linh vật, các cột mốc (period/title/body/icon/state/progress %/live) |
| `footer` | chrome | ✗ | Thương hiệu, link "Khám phá"/"Liên lạc", bản quyền |

- **icon** trong item là **key** trong `App\Support\Congnghe\CongngheIcons` (admin chọn từ dropdown — không nhập SVG thô).
- **key** trong hero/impact là một chỉ số của `$metrics` (xem `CongngheContentSchema::METRIC_KEYS`); số do controller tính, admin chỉ chọn chỉ số + đặt nhãn.
- **tone** trong impact là bảng màu (`CongngheContentSchema::TONES`).
- Thẻ `doneTasks` tự hiển thị `% tổng task` khi có dữ liệu (logic giữ trong `ImpactMetrics.vue`).

---

## 4. Bật/tắt & sắp xếp

- Mỗi section **orderable** có cờ `is_visible` và `position`. Trang công khai chỉ render section `is_visible` và theo `position`.
- Admin bật/tắt bằng `ToggleSwitch` ở cột trái (lưu ngay), đổi thứ tự bằng nút ▲▼ (gọi `reorder`).
- Section **chrome** (meta/nav/hero/footer) luôn hiển thị, không nằm trong luồng sắp xếp.

---

## 5. Bảng `va_prd_congnghe_sections`

| Column | Type | Null | Mô tả |
|---|---|---|---|
| id | bigint UNSIGNED | NO | PK |
| key | varchar(255) | NO | Unique, khớp `config/congnghe.php` |
| content | json | YES | Override; `null` ⇒ dùng default |
| is_visible | boolean | NO | Hiển thị (mặc định true) |
| position | smallint UNSIGNED | NO | Thứ tự trong `<main>` |
| updated_by | bigint UNSIGNED | YES | FK `system_accounts`, `nullOnDelete` |
| created_at / updated_at | timestamp | YES | |

Cần `php artisan migrate` trên DB thật (migration đã verify qua test sqlite).

---

## 6. Thêm / sửa một field

1. Sửa default trong `config/congnghe.php` (section.content).
2. Khai báo field cho trình soạn thảo trong `CongngheContentSchema::editor($key)` — type: `text|textarea|heading|link|kv|list`; list có `fields` con (`text|textarea|icon|metric|tone|number|bool|anchor|stringlist`). `number` nhận `min`/`max` (mặc định 0–100), validate `integer` + `nullable`.
3. `rules()` tự sinh từ `editor()` ⇒ không cần viết validation riêng (client + server khớp).
4. Trình soạn thảo (`SectionEditor.vue` + `RepeatableList.vue`) render generic theo type ⇒ thường không phải sửa Vue admin.
5. Partial công khai đọc field qua `props.content.*` — nếu thêm field mới, bind vào partial tương ứng.

Thêm icon mới: thêm vào `CongngheIcons::all()` (key → label + SVG path).

---

## 7. Files

| Lớp | File |
|-----|------|
| Default | `config/congnghe.php` |
| Domain | `App\Support\Congnghe\{CongngheContentSchema, CongngheContentRepository, CongngheIcons}` |
| DB | `database/migrations/*_create_congnghe_sections_table.php`, `App\Models\CongngheSection` |
| Quyền | `App\Policies\CongngheContentPolicy` (map ở `AuthServiceProvider`) |
| HTTP | `App\Http\Controllers\Congnghe\CongngheAdminController`, `App\Http\Requests\Congnghe\{UpdateCongngheSectionRequest, ReorderCongngheSectionsRequest}`, routes `congnghe.admin.*` |
| Public | `App\Http\Controllers\Congnghe\CongngheController` (prop `content` + `icons`) |
| Vue admin | `resources/js/Pages/CongngheAdmin/Index.vue` + `partials/{SectionEditor, RepeatableList, IconPicker}.vue` |
| Vue public | `resources/js/Pages/Congnghe/Index.vue` + `partials/*` (nhận `content` qua props) |
| Test | `tests/Feature/Congnghe/CongngheAdminTest.php`, `tests/Feature/CongngheTest.php` |
