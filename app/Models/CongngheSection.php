<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Override nội dung của một section trên trang /congnghe.
 *
 * Bảng chỉ lưu phần admin đã ghi đè — default sống ở config/congnghe.php và
 * được merge bởi {@see \App\Support\Congnghe\CongngheContentRepository}, nên
 * bảng trống vẫn hợp lệ.
 *
 * @property string $key
 * @property array<string, mixed>|null $content
 * @property bool $is_visible
 * @property int $position
 * @property int|null $updated_by
 */
class CongngheSection extends Model
{
    protected $table = 'congnghe_sections';

    protected $fillable = [
        'key',
        'content',
        'is_visible',
        'position',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
        'is_visible' => 'boolean',
        'position' => 'integer',
    ];
}
