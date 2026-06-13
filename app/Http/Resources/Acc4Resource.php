<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Acc4Resource extends BaseResource
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
            'acc3' => new Acc3Resource($this->whenLoaded('acc3')),
        ]);
    }
}
