<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'trn' => $this->trn,
            'companyPerson' => $this->company_person,
            'active' => $this->active,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'store_title_en' => $this->store_title_en,
            'store_title_ar' => $this->store_title_ar,
            'store_bio_en' => $this->store_bio_en,
            'store_bio_ar' => $this->store_bio_ar,
            'store_address_en' => $this->store_address_en,
            'store_address_ar' => $this->store_address_ar,
            'store_working_hours_en' => $this->store_working_hours_en,
            'store_working_hours_ar' => $this->store_working_hours_ar,
            'store_hide_out_of_stock_products' => $this->store_hide_out_of_stock_products,
            'store_enable_orders_tracking' => $this->store_enable_orders_tracking,
            'store_enable_stock_tracking' => $this->store_enable_stock_tracking,
            'store_orders_tracking_mode' => $this->store_orders_tracking_mode,
            'store_orders_tracking_packaging_time_hours' => $this->store_orders_tracking_packaging_time_hours,
            'store_orders_tracking_delivery_time_hours' => $this->store_orders_tracking_delivery_time_hours,
            'store_social_media_links_facebook' => $this->store_social_media_links['facebook'] ?? "",
            'store_social_media_links_instagram' => $this->store_social_media_links['instagram'] ?? "",
            'store_social_media_links_twitter' => $this->store_social_media_links['twitter'] ?? "",
            'store_social_media_links_youtube' => $this->store_social_media_links['youtube'] ?? "",
            'store_social_media_links_snapchat' => $this->store_social_media_links['snapchat'] ?? "",
            'store_social_media_links_whatsapp' => $this->store_social_media_links['whatsapp'] ?? "",
            'canDelete' => false,
            'dashboardUrl' => env('CLIENT_URL') . $this->slug,
        ]);
    }
}
