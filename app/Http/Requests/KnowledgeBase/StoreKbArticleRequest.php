<?php

namespace App\Http\Requests\KnowledgeBase;

use App\Models\KbArticle;
use App\Support\Enums\KbArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', KbArticle::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:kb_categories,id'],
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:kb_articles,slug'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(KbArticleStatus::values())],
            'tag_names' => ['nullable', 'array', 'max:20'],
            'tag_names.*' => ['string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'status.required' => 'Vui lòng chọn trạng thái.',
        ];
    }
}
