<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;


class StorePlantRequest extends FormRequest
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
            'name' => 'required|min:3|max:64',
            'watering_frequency' => 'required',
            'fertilizing_frequency' => 'required'
        ];
    }


    public function messages()
    {
        return [
            'name.required' => 'A name is required!',
            'name.min' => 'Name must at least 3 characters long!',
            'watering_frequency.required' => 'A watering frequency is required!',
            'fertilizing_frequency.required' => 'A fertilizing frequency is required!',
        ];
    }

}
