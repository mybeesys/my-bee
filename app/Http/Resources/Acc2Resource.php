<?php

namespace App\Http\Resources;

use App\Models\Acc1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Acc2Resource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'name' => $this->name,
            'code' => $this->code,
            'acc1' => new Acc1Resource($this->acc1),
        ]);
    }
}
