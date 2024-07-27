<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ApiTenantAttributeRule;
use App\Rules\InternationalPhoneRule;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->user()->hasRole(User::ROLE_CLIENT);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant_id = request()->header('Tenant-Id');
//        dd('sometimes', 'string', 'max:255', 'unique:tenants,name,'.request()->header('Tenant-Id').',id');
        //'unique:tenants,name,'.request()->header('Tenant-Id').',id'
        return [
            'type' => ['sometimes', 'string', 'in:company,individual'],
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('tenants', 'name')->ignore($tenant_id)],
            'phone' => ['sometimes', new ApiTenantAttributeRule('phone', $tenant_id), new InternationalPhoneRule(false)],
            'mobile' => ['nullable', 'string', new InternationalPhoneRule(false)],
            'email' => ['sometimes', 'email', new ApiTenantAttributeRule('email', $tenant_id)],
            'address' => ['sometimes', 'string', 'max:255'],
            'trn' => ['required_if:type,==,company', 'string', 'max:255'],
            'company_person' => ['required_if:type,==,company', 'string', 'max:255'],

            'store_title_en' => ['sometimes', 'max:255'],
            'store_title_ar' => ['sometimes', 'max:255'],
            'store_bio_en' => ['sometimes', 'max:255'],
            'store_bio_ar' => ['sometimes', 'max:255'],
            'store_address_en' => ['sometimes', 'max:255'],
            'store_address_ar' => ['sometimes', 'max:255'],
            'store_working_hours_en' => ['sometimes', 'max:255'],
            'store_working_hours_ar' => ['sometimes', 'max:255'],
            'store_hide_out_of_stock_products' => ['sometimes', 'boolean', 'max:255'],
            'store_enable_orders_tracking' => ['sometimes', 'boolean', 'max:255'],
            'store_enable_stock_tracking' => ['sometimes', 'boolean', 'max:255'],
            'store_orders_tracking_mode' => ['sometimes', 'in:automatic,manually'],
            'store_orders_tracking_packaging_time_hours' => ['required_if:store_orders_tracking_mode,==,automatic', 'numeric'],
            'store_orders_tracking_delivery_time_hours' => ['required_if:store_orders_tracking_mode,==,automatic', 'numeric'],
            'store_social_media_links_facebook' => ['sometimes', 'max:255'],
            'store_social_media_links_instagram' => ['sometimes', 'max:255'],
            'store_social_media_links_twitter' => ['sometimes', 'max:255'],
            'store_social_media_links_youtube' => ['sometimes', 'max:255'],
            'store_social_media_links_snapchat' => ['sometimes', 'max:255'],
            'store_social_media_links_whatsapp' => ['sometimes', 'max:255'],
            'store_terms_and_conditions' => ['sometimes'],
        ];
    }
}
