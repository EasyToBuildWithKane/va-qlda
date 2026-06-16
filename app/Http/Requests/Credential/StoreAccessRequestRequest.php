<?php

namespace App\Http\Requests\Credential;

use App\Support\Enums\CredentialPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('credential'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requested_permissions' => ['required', 'array', 'min:1'],
            'requested_permissions.*' => ['string', Rule::in(CredentialPermission::values())],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
