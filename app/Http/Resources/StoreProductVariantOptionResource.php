<?php

namespace App\Http\Resources;

use App\Models\VariantLibraryOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductVariantOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $options = VariantLibraryOption::findMany($this->values);

        return [
            'libraryId' => $this->library->id,
            'libraryName' => $this->library->name,
            'options' => StoreVariantLibraryOptionResource::collection($options),
        ];
    }
}
