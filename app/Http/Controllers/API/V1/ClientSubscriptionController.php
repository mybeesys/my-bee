<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Services\SubscriptionApiService;
use App\Services\SubscriptionCouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientSubscriptionController extends BaseController
{
    public function show()
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            SubscriptionApiService::instance()->summary()
        )->respond();
    }

    public function plans()
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            ['plans' => SubscriptionApiService::instance()->plans()]
        )->respond();
    }

    public function quote(Request $request)
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        $data = $request->validate([
            'planId' => ['required', 'integer', 'exists:plans,id'],
            'billingPeriod' => ['nullable', 'string', 'in:monthly,yearly'],
            'couponCode' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $quote = SubscriptionApiService::instance()->quote(
                (int) $data['planId'],
                $data['billingPeriod'] ?? null,
                $data['couponCode'] ?? null,
            );

            return $this->responder(__('messages.api.retrieved'), 200, $quote)->respond();
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['couponCode' => $exception->getMessage()]);
        }
    }

    public function validateCoupon(Request $request)
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        $data = $request->validate([
            'couponCode' => ['required', 'string', 'max:50'],
            'planId' => ['required', 'integer', 'exists:plans,id'],
            'billingPeriod' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        try {
            $client = SubscriptionApiService::instance()->resolveClient();
            $coupon = SubscriptionCouponService::instance()->findUsable($data['couponCode'], $client);
            $quote = SubscriptionApiService::instance()->quote(
                (int) $data['planId'],
                $data['billingPeriod'] ?? null,
                $coupon->code,
                $client,
            );

            return $this->responder(__('messages.api.retrieved'), 200, [
                'valid' => true,
                'couponCode' => $coupon->code,
                'couponId' => $coupon->id,
                'quote' => $quote,
            ])->respond();
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['couponCode' => $exception->getMessage()]);
        }
    }

    public function subscribe(Request $request)
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        $data = $request->validate([
            'planId' => ['required', 'integer', 'exists:plans,id'],
            'billingPeriod' => ['nullable', 'string', 'in:monthly,yearly'],
            'couponCode' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $subscription = SubscriptionApiService::instance()->subscribe(
                (int) $data['planId'],
                $data['billingPeriod'] ?? null,
                $data['couponCode'] ?? null,
            );

            $subscription->load('plan');

            return $this->responder(
                __('fields.subscription_updated'),
                200,
                SubscriptionApiService::instance()->summary()
            )->respond();
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['couponCode' => $exception->getMessage()]);
        }
    }

    public function usage()
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            SubscriptionApiService::instance()->usage()
        )->respond();
    }

    public function couponsAvailable()
    {
        $user = auth('sanctum')->user();

        if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
            return $this->responder(__('messages.api.permission_denied'), 403)->respond();
        }

        return $this->responder(__('messages.api.retrieved'), 200, [
            'hasActiveCoupons' => SubscriptionCouponService::instance()->hasActiveCoupons(),
        ])->respond();
    }
}
