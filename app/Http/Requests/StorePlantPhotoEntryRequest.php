<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlantPhotoEntryRequest extends FormRequest
{
    protected $errorBag = 'photoJournal';

    public function rules(): array
    {
        $today = now($this->user()->reminder_timezone ?? 'UTC')->toDateString();

        return [
            'taken_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:'.$today],
            'photo' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('plants', $this->route('plant')).'#photo-journal';
    }
}
