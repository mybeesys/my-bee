<?php

namespace App\Http\Resources;

use App\Models\Coupon;
use App\Services\CouponService;
use Carbon\Carbon;

class CouponResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->resource;
        $status = CouponService::instance()->resolveStatus($coupon);
        $usagesCount = (int) ($coupon->usages_count ?? $coupon->usages?->count() ?? 0);

        return $this->filterFields([
            'id' => $coupon->id,
            'code' => $coupon->code,
            'span' => $coupon->span,
            'spanLabel' => match ($coupon->span) {
                'one-time' => __('fields.coupon_span_one_time'),
                'specified-time' => __('fields.coupon_span_specified_time'),
                'unlimited-time' => __('fields.coupon_span_unlimited_time'),
                default => $coupon->span,
            },
            'type' => $coupon->type,
            'typeLabel' => $coupon->type === Coupon::$TYPE_FIXED
                ? __('fields.coupon_type_fixed')
                : __('fields.coupon_type_percent'),
            'value' => $coupon->value,
            'amount' => $coupon->type === Coupon::$TYPE_FIXED ? $coupon->value : null,
            'percent' => $coupon->type === Coupon::$TYPE_PERCENT ? $coupon->value : null,
            'valueFormatted' => $coupon->type === Coupon::$TYPE_FIXED
                ? number_format($coupon->value, currency_decimals(), '.', '').' '.main_currency_iso_code()
                : $coupon->value.'%',
            'active' => (bool) $coupon->active,
            'status' => $status,
            'validUntil' => $coupon->valid_until?->format('Y-m-d'),
            'validUntilFormatted' => $coupon->valid_until?->format('d-m-Y'),
            'validUntilDisplay' => $coupon->valid_until?->format('M j, Y'),
            'description' => $coupon->description,
            'usagesCount' => $usagesCount,
            'createdAt' => $coupon->created_at->format('F j, Y, g:i a'),
            'createdAtFormatted' => $coupon->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $coupon->updated_at ? $coupon->updated_at->format('F j, Y, g:i a') : null,
            'actions' => [
                'canEdit' => true,
                'canDelete' => false,
            ],
        ]);
    }
}
