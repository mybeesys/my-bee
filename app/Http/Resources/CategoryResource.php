<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends BaseResource
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
            'nameEn' => $this->getTranslation("name", "en"),
            'nameAr' => $this->getTranslation("name", "ar"),
            'description' => $this->description,
            'sort' => $this->sort,
            'productsCount' => $this->products->count(),
            'canBecomeParent' => $this->products->isEmpty(),
            'canDelete' => true,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at->format('F j, Y, g:i a'),
//            'parent' => new CategoryResource($this->parent),
        ]);
    }
}
