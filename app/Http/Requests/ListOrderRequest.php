<?php

namespace App\Http\Requests;

class ListOrderRequest extends BaseRequest
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
            'no' => ['sometimes', 'string'],
            'source' => ['sometimes', 'in:shop,dashboard'],
            'payment_method' => ['sometimes', 'in:cash_on_delivery'],
            'payment_status' => ['sometimes','string', "in:دفع بالآجل,مسدد جزئيا,تم السداد,Post paid,Partly paid,Paid"],
            'status' => ['sometimes', 'in:new,packaging,delivery-in-progress,completed,cancelled'],
            'delivery_type' => ['sometimes', 'in,delivery,pickup'],
            'state_id' => ['sometimes', 'integer'],
            'city_id' => ['sometimes', 'integer'],
            'area_id' => ['sometimes', 'integer'],
            'coupon' => ['sometimes', 'string'],
            'from_date' => ['sometimes', 'date', 'date_format:d-m-Y'],
            'to_date' => ['sometimes', 'date', 'date_format:d-m-Y', 'after:from_date'],
        ];
    }
}
