<?php

namespace App\Http\Requests;

class UpdateOrderRequest extends BaseRequest
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
            'status' => ['required', 'in:packaging,delivery-in-progress,completed,cancelled'],
            'delivery' => ['required_if:status,==,delivery-in-progress', 'numeric', 'min:0'],
            'delivery_date' => ['required_if:status,==,completed', 'date', 'date_format:d-m-Y'],
            'canceled_date' => ['required_if:status,==,cancelled', 'date', 'date_format:d-m-Y'],
            'canceled_reason' => ['required_if:status,==,cancelled', 'string', 'min:1', 'max:2500'],
        ];
    }
}
