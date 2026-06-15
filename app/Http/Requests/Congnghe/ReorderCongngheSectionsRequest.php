<?php

namespace App\Http\Requests\Congnghe;

use App\Models\CongngheSection;
use App\Support\Congnghe\CongngheContentSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate thứ tự mới của các section orderable trên /congnghe.
 */
class ReorderCongngheSectionsRequest extends FormRequest
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
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['string', Rule::in(CongngheContentSchema::orderableKeys())],
        ];
    }
}
