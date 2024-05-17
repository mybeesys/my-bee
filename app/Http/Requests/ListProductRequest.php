<?php

namespace App\Http\Requests;

class ListProductRequest extends BaseRequest
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
            'name' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', 'in:basic,variants'],
            'barcode' => ['sometimes', 'string'],
            'sku' => ['sometimes', 'string'],
            'calories_min' => ['sometimes', 'integer'],
            'calories_max' => ['sometimes', 'integer'],
            'category_id' => ['sometimes', 'integer'],
            'tax_profile_id' => ['sometimes', 'integer'],
            'published' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'in:default,latest,oldest'],
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
        ];
    }
}
