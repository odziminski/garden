<?php

namespace App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|unique:users|min:2|max:64',
            'email' => 'required|unique:users|min:3|max:254',
            'password' => 'required|regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9])(?=.*[\d\x])(?=.*[!$#%]).*$/|confirmed|min:6',
        ];
    }


    public function messages()
    {
        return [
            'name.required' => 'A name is required!',
            'name.min' => 'Name must at least 2 characters long!',
            'email.required' => 'Email is required!',
            'password' => 'Password is required!',
        ];
    }
}
