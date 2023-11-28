<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'max:255'],
            'name_ar' => ['required', 'max:255'],
            'description' => ['sometimes', 'max:255'],
            'parent_id' => ['sometimes', 'integer'],
            'sort' => ['sometimes', 'integer', 'max:999'],
        ];
    }
}
