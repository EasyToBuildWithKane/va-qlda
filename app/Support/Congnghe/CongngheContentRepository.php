<?php

namespace App\Support\Congnghe;

use App\Models\CongngheSection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Đọc/ghi nội dung editable của /congnghe. DB chỉ lưu OVERRIDE; default đến từ
 * config/congnghe.php và được merge chồng lúc đọc (deepMerge). Bảng trống ⇒ trang
 * hiển thị đúng default.
 *
 * Quy tắc merge: object/assoc → merge theo key; mảng list (links, items, …) →
 * thay nguyên cụm. Đăng ký singleton trong AppServiceProvider.
 */
class CongngheContentRepository
{
    private const CACHE_KEY = 'congnghe.sections';

    /**
     * Override theo từng key: key => {content, is_visible, position}.
     *
     * @return array<string, array{content: array<string, mixed>|null, is_visible: bool, position: int}>
     */
    public function overrides(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! $this->tableReady()) {
                return [];
            }

            return CongngheSection::query()
                ->get()
                ->mapWithKeys(fn (CongngheSection $row) => [$row->key => [
                    'content' => is_array($row->content) ? $row->content : null,
                    'is_visible' => (bool) $row->is_visible,
                    'position' => (int) $row->position,
                ]])
                ->all();
        });
    }

    /**
     * Payload cho trang công khai: chrome + danh sách section đã lọc hiển thị và
     * sắp theo thứ tự.
     *
     * @return array<string, mixed>
     */
    public function forPublic(): array
    {
        $overrides = $this->overrides();

        $ordered = [];
        foreach (CongngheContentSchema::orderableKeys() as $key) {
            $row = $overrides[$key] ?? null;
            if ($row !== null && $row['is_visible'] === false) {
                continue;
            }
            $ordered[] = [
                'key' => $key,
                'data' => $this->resolved($key, $overrides),
                'pos' => $row['position'] ?? CongngheContentSchema::defaultPosition($key),
                'def' => CongngheContentSchema::defaultPosition($key),
            ];
        }

        usort($ordered, fn ($a, $b) => [$a['pos'], $a['def']] <=> [$b['pos'], $b['def']]);

        return [
            'meta' => $this->resolved('meta', $overrides),
            'nav' => $this->resolved('nav', $overrides),
            'hero' => $this->resolved('hero', $overrides),
            'footer' => $this->resolved('footer', $overrides),
            'sections' => array_map(
                fn (array $s) => ['key' => $s['key'], 'data' => $s['data']],
                $ordered,
            ),
        ];
    }

    /**
     * Payload đầy đủ cho trang quản trị (gồm cả section đang ẩn).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAdmin(): array
    {
        $overrides = $this->overrides();

        return array_map(function (string $key) use ($overrides): array {
            $row = $overrides[$key] ?? null;

            return [
                'key' => $key,
                'label' => CongngheContentSchema::label($key),
                'icon' => CongngheContentSchema::icon($key),
                'orderable' => CongngheContentSchema::isOrderable($key),
                'position' => $row['position'] ?? CongngheContentSchema::defaultPosition($key),
                'is_visible' => $row['is_visible'] ?? true,
                'is_overridden' => $row !== null && ! empty($row['content']),
                'editor' => CongngheContentSchema::editor($key),
                'data' => $this->resolved($key, $overrides),
                'default' => CongngheContentSchema::default($key),
            ];
        }, CongngheContentSchema::keys());
    }

    /**
     * Lưu override cho một section.
     *
     * @param  array<string, mixed>  $content
     */
    public function save(string $key, array $content, bool $isVisible, ?int $userId = null): void
    {
        CongngheSection::query()->updateOrCreate(
            ['key' => $key],
            [
                'content' => $content,
                'is_visible' => $isVisible,
                'position' => $this->overrides()[$key]['position'] ?? CongngheContentSchema::defaultPosition($key),
                'updated_by' => $userId,
            ],
        );

        $this->forget();
    }

    /**
     * Sắp xếp lại các section orderable (mảng key theo thứ tự mới).
     *
     * @param  array<int, string>  $keys
     */
    public function reorder(array $keys, ?int $userId = null): void
    {
        $overrides = $this->overrides();
        $position = 1;

        foreach ($keys as $key) {
            if (! CongngheContentSchema::isOrderable($key)) {
                continue;
            }
            CongngheSection::query()->updateOrCreate(
                ['key' => $key],
                [
                    'position' => $position++,
                    'content' => $overrides[$key]['content'] ?? null,
                    'is_visible' => $overrides[$key]['is_visible'] ?? true,
                    'updated_by' => $userId,
                ],
            );
        }

        $this->forget();
    }

    /** Khôi phục một section về mặc định (xoá override). */
    public function reset(string $key): void
    {
        CongngheSection::query()->where('key', $key)->delete();
        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Default merge với override (nếu có).
     *
     * @param  array<string, array{content: array<string, mixed>|null, is_visible: bool, position: int}>  $overrides
     * @return array<string, mixed>
     */
    private function resolved(string $key, array $overrides): array
    {
        $default = CongngheContentSchema::default($key);
        $override = $overrides[$key]['content'] ?? null;

        if (! is_array($override) || $override === []) {
            return $default;
        }

        return $this->deepMerge($default, $override);
    }

    /**
     * @param  array<string, mixed>  $default
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function deepMerge(array $default, array $override): array
    {
        $out = $default;

        foreach ($override as $k => $v) {
            $hasDefault = array_key_exists($k, $default) && is_array($default[$k]);
            if (
                is_array($v) && $hasDefault
                && ! array_is_list($v) && ! array_is_list($default[$k])
            ) {
                $out[$k] = $this->deepMerge($default[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('congnghe_sections');
        } catch (\Throwable) {
            return false;
        }
    }
}
