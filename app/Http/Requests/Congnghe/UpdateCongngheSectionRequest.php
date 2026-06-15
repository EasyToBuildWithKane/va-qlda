<?php

namespace App\Http\Requests\Congnghe;

use App\Models\CongngheSection;
use App\Support\Congnghe\CongngheContentSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate payload chỉnh nội dung một section /congnghe. Rule sinh từ
 * CongngheContentSchema::rules() theo section trên route — mirror đúng hình dạng
 * field mà trình soạn thảo gửi lên.
 */
class UpdateCongngheSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', CongngheSection::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $section = (string) $this->route('section');

        return CongngheContentSchema::rules($section);
    }
}
