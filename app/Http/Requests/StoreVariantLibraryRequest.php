<?php

namespace App\Http\Requests;

use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Rules\ApiUniqueTenantItemRule;

class StoreVariantLibraryRequest extends BaseRequest
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
            'name_en' => ['required', 'max:255', new ApiUniqueTenantItemRule(VariantLibrary::class, 'name_en')],
            'name_ar' => ['required', 'max:255', new ApiUniqueTenantItemRule(VariantLibrary::class, 'name_ar')],
            'options' => ['required', 'array', 'min:1'],
            'options.*.name_en' => ['required', 'string', 'min:1', 'max:255'],
            'options.*.name_ar' => ['required', 'min:1', 'max:255'],
        ];
    }
}
