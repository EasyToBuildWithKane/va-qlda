# KNOWLEDGE BASE — Module Tri Thức

> Wiki nội bộ kiểu Viblo / Notion Wiki / Confluence (phiên bản đơn giản), tích hợp trong VA-QLDA (Laravel 10 + Inertia + Vue 3).
> **Trạng thái:** ✅ Triển khai v1 (2026-06-14) — migrations, CRUD bài viết, tìm kiếm, file. Chi tiết route: `routes/web.php` prefix `knowledge-base.`.

---

## 1. Mục tiêu & phạm vi

| Mục tiêu | Mô tả |
|---|---|
| Lưu trữ tri thức tổ chức | Quy trình, HOWTO, onboarding, kinh nghiệm thực tế, tài liệu nội bộ |
| Khám phá nội dung | Sidebar danh mục, danh sách bài, tìm kiếm, lọc tag/danh mục |
| Đọc & tương tác | TOC tự động, breadcrumb, bài liên quan, yêu thích, đánh dấu đã đọc, lượt xem |
| Cộng tác nội bộ | Bình luận (tái sử dụng polymorphic `Comment`) |
| Xuất bản có kiểm soát | Draft → Published → Archived, phân quyền xem theo vai trò |

**Ngoài phạm vi v1 (có thể follow-up):** versioning đầy đủ như Confluence, public blog, đa ngôn ngữ, Scout/Elasticsearch (có thể bật sau).

---

## 2. Kiến trúc

Pattern **MVC** giống Blocker / Feedback — không tách Clean Architecture trừ khi sau này cần workflow phức tạp.

```
routes/web.php
    → KbArticleController, KbCategoryController (optional thin)
    → FormRequest (Store/Update KbArticle)
    → KbArticlePolicy
    → KbArticleResource, KbCategoryResource
    → Models: KbArticle, KbCategory, KbTag, …

resources/js/
    → Pages/KnowledgeBase/Index.vue      (sidebar + list)
    → Pages/KnowledgeBase/Show.vue       (chi tiết + TOC)
    → Pages/KnowledgeBase/Edit.vue       (TipTap + upload)
    → modules/knowledge-base/          (sidebar, ArticleCard, TocPanel, …)
    → composables/useKbArticle.js, useKbSearch.js
```

**Lưu file:** disk `public`, path `knowledge-base/{article_id}/images|attachments` — URL qua `PublicMediaUrl` + route tải (pattern Project attachment).

**Rich text:** TipTap (đã có trong dự án) — chèn ảnh inline qua upload → chèn URL vào editor.

**Tìm kiếm v1:** `WHERE title LIKE` + `FULLTEXT` trên `title, excerpt, content` (MySQL) hoặc Laravel Scout (tùy chọn phase 2).

---

## 3. Phân quyền

Ánh xạ trên guard `system` và role hiện có (`admin` | `lead` | `member` | `viewer`). Coaching dùng role riêng — **Knowledge Base không** dùng Super Admin / Coach / Student.

| Hành động | admin | lead | member | viewer |
|---|---|---|---|---|
| Xem bài `published` | ✅ | ✅ | ✅ | ✅ (có thể giới hạn danh mục — xem §3.1) |
| Xem bài `draft` / `archived` | ✅ | ✅ (tác giả hoặc toàn bộ — cấu hình) | Tác giả + lead | ❌ |
| Tạo / sửa bài | ✅ | ✅ | ✅ (draft của mình; publish theo policy) | ❌ |
| Xóa / archive | ✅ | ✅ | Chỉ draft của mình (tùy policy) | ❌ |
| Yêu thích / đánh dấu đã đọc | ✅ | ✅ | ✅ | ✅ |
| Bình luận | ✅ | ✅ | ✅ | ✅ (nếu được xem bài) |
| Quản lý danh mục / tag | ✅ | lead (tùy cấu hình) | ❌ | ❌ |

**Policy gợi ý:** `KbArticlePolicy` — `view`, `create`, `update`, `delete`, `publish`.

### 3.1 Phân quyền người xem (nâng cao)

- Cột `visibility` trên bài (đề xuất v2): `internal_all` | `restricted_roles` | `restricted_departments`.
- Hoặc ma trận trong `system_settings` (`permissions.kb_*`) đồng bộ `docs/SYSTEM_CONFIG.md`.
- v1: mọi `published` visible cho `member`+; `viewer` chỉ đọc; danh mục «Tài liệu nội bộ» có thể `lead+` only qua policy theo `category.slug`.

---

## 4. Chuyên mục kiến thức (danh mục)

Danh mục **seed cố định** (enum slug + bản ghi `kb_categories`), có thể mở rộng cây con qua `parent_id`:

| Slug | Tên hiển thị |
|---|---|
| `general` | Kiến thức chung |
| `development` | Kiến thức Development |
| `business-analyst` | Kiến thức Business Analyst (BA) |
| `ai-automation` | AI & Automation |
| `project-management` | Quản lý dự án |
| `field-experience` | Kinh nghiệm thực tế |
| `internal-docs` | Tài liệu nội bộ |
| `other` | Khác |

---

## 5. Quản lý bài viết

### 5.1 Trường dữ liệu

| Trường | Bắt buộc | Ghi chú |
|---|---|---|
| Tiêu đề | ✅ | max 500 |
| Slug SEO | ✅ | unique, auto từ title, cho phép sửa |
| Mô tả ngắn (`excerpt`) | Khuyến nghị | meta / card list |
| Nội dung | ✅ khi publish | HTML TipTap |
| Hình ảnh (gallery) | ❌ | nhiều file, metadata alt |
| Ảnh trong nội dung | ❌ | upload → URL trong HTML |
| File đính kèm | ❌ | PDF, DOCX, … |
| Tags | ❌ | many-to-many, autocomplete |
| Danh mục | ✅ | FK `category_id` |
| Người tạo | auto | `author_id` → employees |
| Ngày tạo / cập nhật | auto | timestamps |
| Trạng thái | ✅ | `draft` \| `published` \| `archived` |

### 5.2 Luồng trạng thái

```
draft ──publish──→ published ──archive──→ archived
  ↑                    │
  └──── unpublish ─────┘ (về draft, clear published_at tùy rule)
```

- `published_at` set khi lần đầu publish.
- `view_count` tăng khi `Show` (debounce theo session/account tùy chọn).

---

## 6. Chức năng nâng cao

| Tính năng | Cách triển khai |
|---|---|
| Full-text search | MySQL FULLTEXT (`KbArticleSearch`) + LIKE fallback; Scout tùy chọn phase 2 |
| Lọc tag / danh mục | Query params + datagrid toolbar (label **Tìm kiếm**, filter dòng 2) |
| Gallery ảnh | `kb_article_images.usage=gallery`, alt, CRUD trên Edit, grid trên Show |
| Xuất danh sách | `GET export-data` + `useKbExport.js` (CSV/Excel, tối đa 200) |
| Bài liên quan | Cùng category + overlap tags, limit 5, exclude current |
| Breadcrumb | Home → Tri thức → {Category} → {Title} |
| Yêu thích | Pivot `kb_article_favorites` |
| Đã đọc | Pivot `kb_article_reads` + `read_at` |
| Lượt xem | `view_count` trên article |
| Bình luận | `Comment` morph `KbArticle` |
| TOC | Client: parse `h2`/`h3` từ HTML content, sticky sidebar desktop |

---

## 7. Giao diện

Tham chiếu UX: Viblo (list + tag), Notion Wiki (sidebar cây), Confluence (breadcrumb + TOC).

### 7.1 Layout

```
┌─────────────────────────────────────────────────────────────┐
│ AppLayout + PageHeader «Tri thức»                            │
├──────────────┬──────────────────────────────────────────────┤
│ Sidebar      │ Main                                          │
│ - Danh mục   │ [Mobile: drawer]                              │
│ - Yêu thích  │ Index: toolbar Tìm kiếm + Lọc tag/danh mục   │
│ - Gần đây    │       → Article list (card/table)             │
│              │ Show: breadcrumb + title + meta + TOC + body  │
│              │       + attachments + related + comments      │
└──────────────┴──────────────────────────────────────────────┘
```

- **Responsive:** sidebar collapse → drawer; TOC dưới title trên mobile.
- **Brand:** `#9A0036`, copy tiếng Việt.

### 7.2 Trang chính

| Route (đề xuất) | Page |
|---|---|
| `GET /knowledge-base` | `KnowledgeBase/Index.vue` |
| `GET /knowledge-base/articles/{article:slug}` | `KnowledgeBase/Show.vue` |
| `GET/POST .../create`, `edit`, `update` | `KnowledgeBase/Edit.vue` |

---

## 8. Database schema

Prefix bảng: `va_prd_`. Chi tiết cột: `docs/DATABASE_STRUCTURE.md` §7.

| Bảng | Mô tả |
|---|---|
| `kb_categories` | Danh mục, sort, parent_id |
| `kb_articles` | Bài viết chính |
| `kb_tags` | Tag |
| `kb_article_tags` | Pivot |
| `kb_article_images` | Gallery / upload riêng |
| `kb_article_attachments` | File đính kèm |
| `kb_article_favorites` | Yêu thích theo `system_account_id` |
| `kb_article_reads` | Đã đọc |

**Comments:** `va_prd_comments` — `commentable_type = App\Models\KbArticle`.

---

## 9. Route map (đề xuất)

| Method | URI | Name | Ghi chú |
|---|---|---|---|
| GET | `/knowledge-base` | `knowledge-base.index` | List + filters |
| GET | `/knowledge-base/articles/create` | `knowledge-base.articles.create` | lead+ |
| POST | `/knowledge-base/articles` | `knowledge-base.articles.store` | |
| GET | `/knowledge-base/articles/{article:slug}` | `knowledge-base.articles.show` | |
| GET | `/knowledge-base/articles/{article}/edit` | `knowledge-base.articles.edit` | |
| PUT/PATCH | `/knowledge-base/articles/{article}` | `knowledge-base.articles.update` | |
| DELETE | `/knowledge-base/articles/{article}` | `knowledge-base.articles.destroy` | |
| POST | `/knowledge-base/articles/{article}/favorite` | `knowledge-base.articles.favorite` | toggle |
| POST | `/knowledge-base/articles/{article}/read` | `knowledge-base.articles.read` | mark read |
| POST | `/knowledge-base/articles/{article}/images` | `knowledge-base.articles.images.store` | |
| POST | `/knowledge-base/articles/{article}/attachments` | `knowledge-base.articles.attachments.store` | |
| GET | `/knowledge-base/attachments/{attachment}/file` | `knowledge-base.attachments.file` | authorize + fileExists |

Nav: `App\Support\Navigation` — mục «Tri thức», roles `admin`, `lead`, `member`, `viewer` (đọc).

---

## 10. Frontend components map

| Component | Vai trò |
|---|---|
| `KbSidebar.vue` | Cây danh mục, yêu thích, mobile drawer |
| `KbArticleList.vue` | Card/list + empty state |
| `KbArticleMeta.vue` | Tác giả, ngày, tags, lượt xem |
| `KbTocPanel.vue` | TOC từ headings |
| `KbRelatedArticles.vue` | Bài liên quan |
| `KbEditor.vue` | TipTap + image upload hook |
| `KbAttachmentList.vue` | Tải file đính kèm |
| `useKbArticle.js` | Form state, slugify, publish |
| `useKbSearch.js` | Debounce search, filter sync URL |

Tests: Feature (policy, publish, slug unique); E2E (index → show, favorite).

---

## 11. Definition of Done (triển khai)

- [x] Migrations + seed 8 danh mục
- [x] CRUD bài + upload ảnh/attachment + TipTap inline image
- [x] Index: sidebar, search, filter tag/category, yêu thích
- [x] Show: breadcrumb, TOC (server anchors), related, view count, favorite/read
- [x] Comments morph hoạt động
- [x] Policy + Nav + messages tiếng Việt
- [x] Feature tests + E2E smoke (`tests/e2e/knowledge-coaching.spec.js`)
- [x] Route file ảnh/đính kèm — 404 khi file mất

---

## 12. Công nghệ (điều chỉnh theo VA-QLDA)

| Đề xuất ban đầu | Trong VA-QLDA |
|---|---|
| Laravel 12 | Laravel 10 |
| React / Nuxt | Vue 3 + Inertia |
| Shadcn | `shared/ui/` + Tailwind |
| CKEditor 5 | TipTap |
| S3 / MinIO | `public` disk (mở rộng S3 sau) |
| Scout | Tùy chọn; FULLTEXT MySQL cho v1 |
