<?php

namespace App\Http\Requests\KnowledgeBase;

use App\Support\Enums\KbArticleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('article'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $article = $this->route('article');
        $articleId = $article instanceof \App\Models\KbArticle ? $article->id : null;

        return [
            'category_id' => ['sometimes', 'integer', 'exists:kb_categories,id'],
            'title' => ['sometimes', 'string', 'max:500'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('kb_articles', 'slug')->ignore($articleId)],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(KbArticleStatus::values())],
            'tag_names' => ['nullable', 'array', 'max:20'],
            'tag_names.*' => ['string', 'max:100'],
        ];
    }
}
