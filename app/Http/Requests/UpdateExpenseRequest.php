<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Validation\Rules\File;

class UpdateExpenseRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('date')) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $this->input('date'))) {
            $this->merge([
                'date' => Carbon::createFromFormat('d-m-Y', $this->input('date'))->format('Y-m-d'),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:65535'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'expense_category_id' => ['sometimes', 'exists:expense_categories,id'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['required', 'file', File::types(['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'JPEG', 'webp', 'pdf', 'PDF'])->max(2048)],
        ];
    }
}
