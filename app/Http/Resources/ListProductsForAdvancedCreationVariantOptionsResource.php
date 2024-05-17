<?php

namespace App\Http\Resources;

use App\Models\VariantLibraryOption;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

class ListProductsForAdvancedCreationVariantOptionsResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $options = [];

        foreach ($this->values ?? [] as $id){
            $options[] = [
                'id' => $id,
                'name' => self::getVariantLibraryOptionName($id),
            ];
        }
        return $this->filterFields([
            'variantLibraryName' => $this->library->name,
            'variantLibraryId' => $this->variant_library_id,
            'options' => $options,
        ]);
    }

    protected function getVariantLibraryOptionName($id){
        $data = CacheService::instance()
            ->tenant(\request()->header('Tenant-Id') ?? null)
            ->remember('VariantLibraryOption', 60, function (){
               return VariantLibraryOption::all();
            });

        return $data->filter(function ($item) use ($id){
            return $item->id == $id;
        })->first()?->name ?? "N/A";
    }
}
