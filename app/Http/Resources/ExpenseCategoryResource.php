<?php

namespace App\Http\Resources;

class ExpenseCategoryResource extends BaseResource
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
            'name' => $this->name,
            'expensesCount' => $this->expenses->Count(),
            'expensesTotal' => $this->expenses_total_formatted,
            'expenses' => ExpenseResource::collection($this->expenses),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'canDelete' => true,
        ]);
    }
}
