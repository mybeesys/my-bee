<?php

namespace App\Http\Requests;

class ListOrderRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'no' => ['sometimes', 'string'],
            'source' => ['sometimes', 'in:shop,dashboard'],
            'payment_method' => ['sometimes', 'in:cash_on_delivery,cash,mbok,fawry,other'],
            'payment_status' => ['sometimes', 'string', 'in:دفع بالآجل,مسدد جزئيا,تم السداد,Post paid,Partly paid,Paid'],
            'status' => ['sometimes', 'in:new,packaging,delivery-in-progress,completed,cancelled'],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['in:new,packaging,delivery-in-progress,completed,cancelled'],
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'delivery_type' => ['sometimes', 'in:none,delivery,pickup'],
            'state_id' => ['sometimes', 'integer'],
            'city_id' => ['sometimes', 'integer'],
            'area_id' => ['sometimes', 'integer'],
            'coupon' => ['sometimes', 'string'],
            'from_date' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:Y-m-d,d-m-Y', 'after_or_equal:from_date'],
            'paginate' => ['sometimes'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'in:latest,oldest'],
        ];
    }
}
