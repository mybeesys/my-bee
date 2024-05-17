<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\UniqueTenantItemRule;
use Illuminate\Validation\Rules\File;

class StoreProductRequest extends BaseRequest
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
            'type' => ['required', 'in:basic,variants'],
            'name' => ['required', 'string', 'max:255', new UniqueTenantItemRule(Product::class, 'name')],

            'images' => ['sometimes', 'array'],
            'images.*' => ['sometimes', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG'])->max(1024)],

            'extras' => ['sometimes', 'array'],
            'extras.*.id' => ['required', 'integer', 'exists:item_extras,id', 'distinct:extras.*.id'],
            'extras.*.price' => ['required', 'numeric', 'max:'.PHP_INT_MAX],
            'extras.*.discount_price' => ['required', 'numeric', 'lt:extras.*.price', 'max:'.PHP_INT_MAX],

            'barcode' => ['required', 'min:4', 'max:255', new UniqueTenantItemRule(Product::class, 'barcode')],
            'sku' => ['required', 'digits:9', new UniqueTenantItemRule(Product::class, 'sku')],
            'calories' => ['sometimes', 'integer'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'security_stock' => ['required', 'integer'],
            'description' => ['sometimes', 'min:3', 'max:500'],
            'tax_profile_id' => ['sometimes', 'integer', 'exists:tax_profiles,id'],
            'published' => ['required', 'boolean'],
            'sort' => ['nullable', 'integer'],
            'price' => ['required_if:type,basic', 'numeric', 'max:'.PHP_INT_MAX],
            'discount_price' => ['nullable', 'numeric', 'lt:price', 'max:'.PHP_INT_MAX],

            'variants' => ['required_if:type,variants', 'array', 'min:1'],
            'variants.*.new_item' => ['required', 'bool'],
            'variants.*.name_ar' => ['required', 'string', 'max:50'],
            'variants.*.name_en' => ['required', 'string', 'max:50'],
            'variants.*.name' => ['required', 'string', 'max:50'],
            'variants.*.variant_library_options_ids' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', new UniqueTenantItemRule(ProductVariant::class, 'sku')],
            'variants.*.price' => ['required', 'numeric', 'max:'.PHP_INT_MAX],
            'variants.*.discount_price' => ['nullable', 'numeric', 'lt:variants.*.price', 'max:'.PHP_INT_MAX],
            'variants.*.image' => ['nullable', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG'])->max(1024)],

        ];
    }
}
