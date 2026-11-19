<?php

namespace App\Http\Requests;

use App\Rules\BcryptPassword;
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
                new BcryptPassword(),
            ],
        ];
    }
}
