<?php

namespace App\Http\Requests;

use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Rules\ApiUniqueTenantItemRule;

class UpdateVariantLibraryRequest extends BaseRequest
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
        $id = str(request()->getRequestUri())->afterLast('/')->value();

        return [
            'name_en' => ['sometimes', 'max:255', new ApiUniqueTenantItemRule(VariantLibrary::class, 'name_en', $id)],
            'name_ar' => ['sometimes', 'max:255', new ApiUniqueTenantItemRule(VariantLibrary::class, 'name_ar', $id)],
            'options' => ['sometimes', 'array', 'min:1'],
            'options.*.id' => ['sometimes', 'exists:variant_library_options,id'],
            'options.*.delete' => ['sometimes', 'boolean'],
            'options.*.new' => ['sometimes', 'boolean'],
            'options.*.name_en' => ['sometimes', 'min:1', 'max:255'],
            'options.*.name_ar' => ['sometimes', 'min:1', 'max:255'],
        ];
    }
}
