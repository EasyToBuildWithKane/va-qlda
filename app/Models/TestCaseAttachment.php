<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $test_case_id
 * @property int|null $uploaded_by_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property bool $is_image
 */
class TestCaseAttachment extends Model
{
    protected $fillable = [
        'test_case_id',
        'uploaded_by_id',
        'original_name',
        'path',
        'mime_type',
        'size',
        'is_image',
    ];

    protected $casts = [
        'is_image' => 'boolean',
        'size' => 'integer',
    ];

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by_id');
    }

    public function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->path);
    }

    public function url(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        return route('test-cases.attachments.file', [
            'testCase' => $this->test_case_id,
            'attachment' => $this->id,
        ]);
    }
}
