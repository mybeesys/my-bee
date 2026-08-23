<?php

namespace App\Services;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public static function instance(): self
    {
        return new self();
    }

    public function isValid($coupon): bool
    {
        $coupon = Coupon::firstWhere('code', $coupon);

        if ($coupon && $coupon->valid_until === null) {
            return (bool) $coupon->active;
        }

        return $coupon && $coupon->active && $coupon->valid_until->isFuture();
    }

    public function amount($coupon, $subTotal)
    {
        $coupon = Coupon::firstWhere('code', $coupon);

        if ($coupon && $coupon->type === Coupon::$TYPE_FIXED) {
            return $coupon->value;
        }

        if ($coupon && $coupon->type === Coupon::$TYPE_PERCENT && $subTotal > 0) {
            return $subTotal * ($coupon->value / 100);
        }

        return 0.0;
    }

    public function isUsedByCustomer(Coupon $coupon, $customer_id): bool
    {
        $coupon->loadMissing('usages');

        return $coupon->usages->where('customer_id', $customer_id)->isNotEmpty();
    }

    public function usages(Coupon $coupon): Collection
    {
        $coupon->loadMissing('usages');

        return $coupon->usages;
    }

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return ['usages'];
    }

    /**
     * @return array<string, mixed>
     */
    public function prefill(): array
    {
        return [
            'type' => Coupon::$TYPE_PERCENT,
            'span' => 'specified-time',
            'active' => true,
            'percent' => 1,
            'amount' => null,
            'validUntil' => now()->addMonth()->format('Y-m-d'),
            'code' => null,
            'description' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'spans' => [
                ['value' => 'one-time', 'label' => __('fields.coupon_span_one_time')],
                ['value' => 'specified-time', 'label' => __('fields.coupon_span_specified_time')],
                ['value' => 'unlimited-time', 'label' => __('fields.coupon_span_unlimited_time')],
            ],
            'types' => [
                ['value' => Coupon::$TYPE_FIXED, 'label' => __('fields.coupon_type_fixed')],
                ['value' => Coupon::$TYPE_PERCENT, 'label' => __('fields.coupon_type_percent')],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $tenantId): Coupon
    {
        $payload = $this->preparePayload($data);
        $payload['tenant_id'] = $tenantId;

        return Coupon::create($payload)->fresh()->loadCount('usages')->load(self::eagerLoads());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $merged = array_merge([
            'code' => $coupon->code,
            'span' => $coupon->span,
            'type' => $coupon->type,
            'active' => $coupon->active,
            'description' => $coupon->description,
            'valid_until' => $coupon->valid_until?->format('Y-m-d'),
            'amount' => $coupon->type === Coupon::$TYPE_FIXED ? $coupon->value : null,
            'percent' => $coupon->type === Coupon::$TYPE_PERCENT ? $coupon->value : null,
        ], $data);

        $payload = $this->preparePayload($merged, $coupon);
        $coupon->update($payload);

        return $coupon->fresh()->loadCount('usages')->load(self::eagerLoads());
    }

    public function resolveStatus(Coupon $coupon): string
    {
        if (! $coupon->active) {
            return 'inactive';
        }

        if ($coupon->valid_until && $coupon->valid_until->isPast()) {
            return 'expired';
        }

        if ($coupon->span === 'one-time' && $coupon->usages()->exists()) {
            return 'used';
        }

        return 'active';
    }

    /**
     * @param  Collection<int, Coupon>  $coupons
     * @return array<string, mixed>
     */
    public function listSummaries(Collection $coupons): array
    {
        return [
            'count' => $coupons->count(),
            'activeCount' => $coupons->filter(fn (Coupon $coupon) => $this->resolveStatus($coupon) === 'active')->count(),
            'usagesCount' => (int) $coupons->sum(fn (Coupon $coupon) => $coupon->usages_count ?? $coupon->usages->count()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preparePayload(array $data, ?Coupon $coupon = null): array
    {
        $type = (string) ($data['type'] ?? $coupon?->type ?? Coupon::$TYPE_PERCENT);
        $span = (string) ($data['span'] ?? $coupon?->span ?? 'specified-time');

        if (! in_array($type, [Coupon::$TYPE_FIXED, Coupon::$TYPE_PERCENT], true)) {
            throw ValidationException::withMessages(['type' => __('validation.in', ['attribute' => 'type'])]);
        }

        if (! in_array($span, ['one-time', 'specified-time', 'unlimited-time'], true)) {
            throw ValidationException::withMessages(['span' => __('validation.in', ['attribute' => 'span'])]);
        }

        $payload = [
            'code' => trim((string) ($data['code'] ?? $coupon?->code)),
            'span' => $span,
            'type' => $type,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : ($coupon?->active ?? true),
            'description' => array_key_exists('description', $data) ? $data['description'] : ($coupon?->description ?? null),
        ];

        if ($type === Coupon::$TYPE_FIXED) {
            $payload['value'] = (int) round((float) ($data['amount'] ?? $coupon?->value ?? 0));
        } else {
            $payload['value'] = (int) ($data['percent'] ?? $coupon?->value ?? 0);
        }

        if ($span === 'unlimited-time') {
            $payload['valid_until'] = null;
        } else {
            if (empty($data['valid_until']) && ! $coupon?->valid_until) {
                throw ValidationException::withMessages([
                    'valid_until' => __('validation.required', ['attribute' => 'valid_until']),
                ]);
            }

            $payload['valid_until'] = Carbon::parse($data['valid_until'] ?? $coupon?->valid_until);
        }

        return $payload;
    }
}
