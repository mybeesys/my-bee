<?php

namespace App\Http\Requests;

class UpdateOrderRequest extends BaseRequest
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
            'status' => ['required', 'in:packaging,delivery-in-progress,completed,cancelled'],
            'delivery' => ['required_if:status,delivery-in-progress,completed', 'numeric', 'min:0'],
            'delivery_date' => ['required_if:status,completed', 'date', 'date_format:Y-m-d,d-m-Y'],
            'canceled_date' => ['required_if:status,cancelled', 'date', 'date_format:Y-m-d,d-m-Y'],
            'canceled_reason' => ['required_if:status,cancelled', 'string', 'min:1', 'max:2500'],
        ];
    }
}
