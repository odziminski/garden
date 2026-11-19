<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users')->ignore($this->user())],
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'bail',
                'nullable',
                'string',
                'min:8',
                'confirmed',
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) > 72) {
                        $fail('The password must be at most 72 bytes.');
                    }
                },
            ],
        ];
    }
}
