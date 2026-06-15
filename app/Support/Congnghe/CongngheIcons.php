<?php

namespace App\Support\Congnghe;

/**
 * Catalog biểu tượng (SVG path "d") dùng cho các item nội dung trên /congnghe.
 *
 * Admin chọn icon theo KEY từ một dropdown cố định (xem IconPicker.vue) thay vì
 * gõ path thô — vừa an toàn (không nhúng SVG tuỳ ý), vừa nhất quán thị giác.
 * Các path này khớp với bộ icon Lucide đang dùng trong About/AILab/Culture.
 */
final class CongngheIcons
{
    /**
     * key => array{label: string, path: string}
     *
     * @return array<string, array{label: string, path: string}>
     */
    public static function all(): array
    {
        return [
            'layers' => ['label' => 'Lớp / nền tảng', 'path' => 'M12 2 2 7l10 5 10-5-10-5ZM2 17l10 5 10-5M2 12l10 5 10-5'],
            'eye' => ['label' => 'Tầm nhìn', 'path' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
            'lens' => ['label' => 'Minh bạch / thấu suốt', 'path' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z'],
            'heart' => ['label' => 'Giá trị / tận tâm', 'path' => 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 1 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z'],
            'brain' => ['label' => 'AI / trí tuệ', 'path' => 'M12 2a5 5 0 0 1 5 5v1a5 5 0 0 1-1 9H8a5 5 0 0 1-1-9V7a5 5 0 0 1 5-5Z M9 21h6'],
            'chart-up' => ['label' => 'Phân tích / tăng trưởng', 'path' => 'M3 3v18h18 M7 14l4-4 3 3 5-6'],
            'check' => ['label' => 'Hoàn tất / kiểm soát', 'path' => 'M20 7 9 18l-5-5'],
            'arrow-right' => ['label' => 'Tiến tới / ship', 'path' => 'M5 12h14M13 6l6 6-6 6'],
            'book' => ['label' => 'Học hỏi', 'path' => 'M12 2 2 7l10 5 10-5-10-5ZM2 17l10 5 10-5'],
            'user' => ['label' => 'Người dùng', 'path' => 'M16 11a4 4 0 1 0-8 0 M3 21a7 7 0 0 1 18 0'],
            'sun' => ['label' => 'Tự động hoá / năng lượng', 'path' => 'M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4'],
            'users' => ['label' => 'Đồng đội', 'path' => 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 .01'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Catalog dạng list cho UI: [{key, label, path}].
     *
     * @return array<int, array{key: string, label: string, path: string}>
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::all() as $key => $meta) {
            $out[] = ['key' => $key, 'label' => $meta['label'], 'path' => $meta['path']];
        }

        return $out;
    }

    /** Map key => path cho frontend render. */
    public static function pathMap(): array
    {
        return array_map(fn (array $m) => $m['path'], self::all());
    }
}
