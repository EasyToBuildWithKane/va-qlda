<?php

namespace App\Support\Congnghe;

use Illuminate\Validation\Rule;

/**
 * Single source of truth cho nội dung editable của trang /congnghe.
 *
 * - Default + metadata (label/icon/orderable/position) lấy từ config/congnghe.php.
 * - editor() khai báo HÌNH DẠNG field từng section → dùng để (a) render trình
 *   soạn thảo ở admin và (b) sinh rule validation (rules()) — một nguồn duy nhất,
 *   tránh lệch giữa client và server.
 *
 * "key" trong hero/impact là một chỉ số trong $metrics do CongngheController
 * tính (METRIC_KEYS); "tone" là bảng màu trong ImpactMetrics (TONES); "icon" là
 * key trong {@see CongngheIcons}.
 */
final class CongngheContentSchema
{
    /** Chỉ số khả dụng cho hero/impact (key => nhãn gợi ý trong admin). */
    public const METRIC_KEYS = [
        'projects' => 'Tổng dự án',
        'activeProjects' => 'Dự án đang chạy',
        'completedProjects' => 'Dự án hoàn thành',
        'orgPeople' => 'Nhân sự trên sơ đồ',
        'members' => 'Nhân sự đang hoạt động',
        'departments' => 'Phòng ban',
        'orgTeams' => 'Nhóm tổ chức',
        'tasks' => 'Tổng công việc',
        'doneTasks' => 'Công việc hoàn thành',
        'aiAccounts' => 'Tài khoản AI',
    ];

    /** Bảng màu cho thẻ số liệu (khớp toneMap trong ImpactMetrics.vue). */
    public const TONES = ['brand', 'cyan', 'emerald', 'violet', 'amber', 'rose'];

    /** Regex cho anchor (#id) trong nav/footer/hero CTA. */
    private const ANCHOR_REGEX = '/^#?[A-Za-z0-9\-_]+$/';

    /**
     * Tất cả section theo thứ tự khai báo trong config.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(config('congnghe', []));
    }

    /**
     * Section nằm trong luồng <main> (đổi thứ tự / ẩn-hiện được).
     *
     * @return array<int, string>
     */
    public static function orderableKeys(): array
    {
        return array_values(array_filter(
            self::keys(),
            fn (string $key) => (bool) (config("congnghe.$key.orderable", false)),
        ));
    }

    public static function exists(string $key): bool
    {
        return config("congnghe.$key") !== null;
    }

    public static function isOrderable(string $key): bool
    {
        return (bool) config("congnghe.$key.orderable", false);
    }

    /** Metadata + default content của một section. */
    public static function section(string $key): array
    {
        return (array) config("congnghe.$key", []);
    }

    /**
     * Default content payload (mảng) của một section.
     *
     * @return array<string, mixed>
     */
    public static function default(string $key): array
    {
        return (array) config("congnghe.$key.content", []);
    }

    public static function defaultPosition(string $key): int
    {
        return (int) config("congnghe.$key.position", 0);
    }

    public static function label(string $key): string
    {
        return (string) config("congnghe.$key.label", $key);
    }

    public static function icon(string $key): string
    {
        return (string) config("congnghe.$key.icon", 'settings');
    }

    /**
     * Mô tả field cho trình soạn thảo của một section.
     *
     * Mỗi mục: ['type' => 'text|textarea|heading|link|kv|list', 'path' => ..., ...].
     *
     * @return array<int, array<string, mixed>>
     */
    public static function editor(string $key): array
    {
        return match ($key) {
            'meta' => [
                ['type' => 'text', 'path' => 'title', 'label' => 'Tiêu đề trang', 'required' => true, 'max' => 120],
            ],
            'nav' => [
                ['type' => 'text', 'path' => 'brand_name', 'label' => 'Tên thương hiệu', 'required' => true, 'max' => 80],
                ['type' => 'text', 'path' => 'brand_tagline', 'label' => 'Khẩu hiệu nhỏ', 'max' => 80],
                ['type' => 'list', 'path' => 'links', 'label' => 'Liên kết điều hướng', 'item_label' => 'Liên kết', 'min' => 1, 'max' => 8, 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Nhãn', 'required' => true, 'max' => 60],
                    ['key' => 'anchor', 'type' => 'anchor', 'label' => 'Neo (#id)'],
                ]],
            ],
            'hero' => [
                ['type' => 'text', 'path' => 'title_prefix', 'label' => 'Tiêu đề — đoạn đầu', 'max' => 60],
                ['type' => 'text', 'path' => 'title_highlight', 'label' => 'Tiêu đề — cụm nổi bật', 'max' => 60],
                ['type' => 'text', 'path' => 'title_suffix', 'label' => 'Tiêu đề — đoạn cuối', 'max' => 80],
                ['type' => 'textarea', 'path' => 'description', 'label' => 'Mô tả', 'max' => 600],
                ['type' => 'link', 'path' => 'cta_primary', 'label' => 'Nút chính'],
                ['type' => 'link', 'path' => 'cta_secondary', 'label' => 'Nút phụ'],
                ['type' => 'list', 'path' => 'highlights', 'label' => 'Chỉ số nổi bật', 'item_label' => 'Chỉ số', 'min' => 0, 'max' => 4, 'fields' => [
                    ['key' => 'key', 'type' => 'metric', 'label' => 'Chỉ số', 'required' => true],
                    ['key' => 'label', 'type' => 'text', 'label' => 'Nhãn', 'required' => true, 'max' => 40],
                    ['key' => 'suffix', 'type' => 'text', 'label' => 'Hậu tố', 'max' => 4],
                ]],
            ],
            'about' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'textarea', 'path' => 'note', 'label' => 'Ghi chú trợ lý', 'max' => 500],
                ['type' => 'list', 'path' => 'pillars', 'label' => 'Trụ cột', 'item_label' => 'Trụ cột', 'min' => 1, 'max' => 6, 'fields' => [
                    ['key' => 'tag', 'type' => 'text', 'label' => 'Nhãn nhỏ', 'max' => 60],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Tiêu đề', 'required' => true, 'max' => 200],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Nội dung', 'max' => 600],
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Biểu tượng'],
                ]],
            ],
            'impact' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'text', 'path' => 'live_badge', 'label' => 'Nhãn LIVE', 'max' => 40],
                ['type' => 'list', 'path' => 'stats', 'label' => 'Thẻ số liệu', 'item_label' => 'Thẻ', 'min' => 1, 'max' => 8, 'fields' => [
                    ['key' => 'key', 'type' => 'metric', 'label' => 'Chỉ số', 'required' => true],
                    ['key' => 'label', 'type' => 'text', 'label' => 'Nhãn', 'required' => true, 'max' => 60],
                    ['key' => 'sub', 'type' => 'text', 'label' => 'Phụ đề', 'max' => 80],
                    ['key' => 'tone', 'type' => 'tone', 'label' => 'Màu'],
                    ['key' => 'suffix', 'type' => 'text', 'label' => 'Hậu tố', 'max' => 4],
                ]],
            ],
            'products' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
            ],
            'tech' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'list', 'path' => 'groups', 'label' => 'Nhóm công nghệ', 'item_label' => 'Nhóm', 'min' => 1, 'max' => 6, 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Tên nhóm', 'required' => true, 'max' => 60],
                    ['key' => 'tag', 'type' => 'text', 'label' => 'Thẻ (slug)', 'max' => 30],
                    ['key' => 'items', 'type' => 'stringlist', 'label' => 'Công nghệ', 'max' => 12, 'item_max' => 60],
                ]],
            ],
            'org' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'text', 'path' => 'stat_label', 'label' => 'Nhãn số liệu', 'max' => 80],
            ],
            'lifecycle' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'kv', 'path' => 'phase_hints', 'label' => 'Mô tả giai đoạn', 'rows' => [
                    ['key' => 'rnd', 'label' => 'R&D'],
                    ['key' => 'deployment', 'label' => 'Triển khai'],
                    ['key' => 'operation', 'label' => 'Vận hành'],
                ], 'max' => 400],
            ],
            'ai_lab' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'list', 'path' => 'initiatives', 'label' => 'Sáng kiến', 'item_label' => 'Sáng kiến', 'min' => 1, 'max' => 6, 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Tiêu đề', 'required' => true, 'max' => 120],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Nội dung', 'max' => 400],
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Biểu tượng'],
                ]],
            ],
            'culture' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'list', 'path' => 'values', 'label' => 'Giá trị', 'item_label' => 'Giá trị', 'min' => 1, 'max' => 9, 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Tiêu đề', 'required' => true, 'max' => 80],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Nội dung', 'max' => 300],
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Biểu tượng'],
                ]],
            ],
            'roadmap' => [
                ['type' => 'heading', 'path' => 'heading', 'label' => 'Tiêu đề mục'],
                ['type' => 'text', 'path' => 'guide_label', 'label' => 'Nhãn "Người dẫn đường"', 'max' => 60],
                ['type' => 'text', 'path' => 'companion_note', 'label' => 'Ghi chú linh vật', 'max' => 200],
                ['type' => 'list', 'path' => 'milestones', 'label' => 'Cột mốc', 'item_label' => 'Cột mốc', 'min' => 1, 'max' => 8, 'fields' => [
                    ['key' => 'period', 'type' => 'text', 'label' => 'Mốc thời gian', 'max' => 60],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Tiêu đề', 'required' => true, 'max' => 200],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Nội dung', 'max' => 400],
                    ['key' => 'state', 'type' => 'text', 'label' => 'Trạng thái', 'max' => 40],
                    ['key' => 'live', 'type' => 'bool', 'label' => 'Đang diễn ra'],
                ]],
            ],
            'footer' => [
                ['type' => 'text', 'path' => 'brand_title', 'label' => 'Tên thương hiệu', 'required' => true, 'max' => 80],
                ['type' => 'text', 'path' => 'brand_tagline', 'label' => 'Khẩu hiệu', 'max' => 160],
                ['type' => 'textarea', 'path' => 'brand_desc', 'label' => 'Mô tả', 'max' => 300],
                ['type' => 'list', 'path' => 'explore_links', 'label' => 'Liên kết "Khám phá"', 'item_label' => 'Liên kết', 'min' => 0, 'max' => 8, 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Nhãn', 'required' => true, 'max' => 80],
                    ['key' => 'anchor', 'type' => 'anchor', 'label' => 'Neo (#id)'],
                ]],
                ['type' => 'list', 'path' => 'contact_links', 'label' => 'Liên kết "Liên lạc"', 'item_label' => 'Liên kết', 'min' => 0, 'max' => 8, 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Nhãn', 'required' => true, 'max' => 120],
                    ['key' => 'href', 'type' => 'text', 'label' => 'Đường dẫn (mailto:/tel:/URL)', 'max' => 200],
                ]],
                ['type' => 'text', 'path' => 'copyright', 'label' => 'Dòng bản quyền', 'max' => 300],
                ['type' => 'text', 'path' => 'portal_label', 'label' => 'Nhãn cổng', 'max' => 60],
            ],
            default => [],
        };
    }

    /**
     * Rule validation cho payload của một section (sinh từ editor()).
     * Áp cho input dạng { content: {...}, is_visible: bool }.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $key): array
    {
        $rules = ['is_visible' => ['sometimes', 'boolean']];

        foreach (self::editor($key) as $field) {
            $rules = array_merge($rules, self::fieldRules($field));
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function fieldRules(array $field): array
    {
        $path = $field['path'] ?? null;
        $base = $path !== null ? "content.$path" : 'content';

        return match ($field['type']) {
            'text', 'textarea' => [
                $base => self::scalarRule($field),
            ],
            'heading' => [
                "$base.eyebrow" => ['nullable', 'string', 'max:120'],
                "$base.title" => ['required', 'string', 'max:200'],
                "$base.subtitle" => ['nullable', 'string', 'max:500'],
            ],
            'link' => [
                "$base.label" => ['nullable', 'string', 'max:120'],
                "$base.anchor" => ['nullable', 'string', 'max:120', 'regex:'.self::ANCHOR_REGEX],
            ],
            'kv' => self::kvRules($base, $field),
            'list' => self::listRules($base, $field),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private static function scalarRule(array $field): array
    {
        $max = (int) ($field['max'] ?? ($field['type'] === 'textarea' ? 800 : 200));

        return [
            ! empty($field['required']) ? 'required' : 'nullable',
            'string',
            "max:$max",
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function kvRules(string $base, array $field): array
    {
        $max = (int) ($field['max'] ?? 600);
        $out = [$base => ['sometimes', 'array']];
        foreach ($field['rows'] ?? [] as $row) {
            $out["$base.".$row['key']] = ['nullable', 'string', "max:$max"];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function listRules(string $base, array $field): array
    {
        $listRule = ['array'];
        if (isset($field['min'])) {
            $listRule[] = 'min:'.(int) $field['min'];
        }
        if (isset($field['max'])) {
            $listRule[] = 'max:'.(int) $field['max'];
        }

        $out = [$base => $listRule];

        foreach ($field['fields'] ?? [] as $sub) {
            $subBase = "$base.*.".$sub['key'];
            $out = array_merge($out, self::subFieldRules($subBase, $sub));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sub
     * @return array<string, mixed>
     */
    private static function subFieldRules(string $subBase, array $sub): array
    {
        return match ($sub['type']) {
            'text', 'textarea' => [$subBase => self::scalarRule($sub)],
            'icon' => [$subBase => ['nullable', Rule::in(CongngheIcons::keys())]],
            'metric' => [$subBase => [
                ! empty($sub['required']) ? 'required' : 'nullable',
                Rule::in(array_keys(self::METRIC_KEYS)),
            ]],
            'tone' => [$subBase => ['nullable', Rule::in(self::TONES)]],
            'bool' => [$subBase => ['sometimes', 'boolean']],
            'anchor' => [$subBase => ['nullable', 'string', 'max:120', 'regex:'.self::ANCHOR_REGEX]],
            'stringlist' => [
                $subBase => ['sometimes', 'array', 'max:'.(int) ($sub['max'] ?? 12)],
                "$subBase.*" => ['nullable', 'string', 'max:'.(int) ($sub['item_max'] ?? 60)],
            ],
            default => [],
        };
    }
}
