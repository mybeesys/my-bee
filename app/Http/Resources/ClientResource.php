<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
//            'id' => $this->id,
//            'name' => $this->user->full_name,
//            'phone' => $this->user->phone,
//            'email' => $this->user->email,
//            'address' => $this->user->address,
//            'gender' => $this->user->gender,
        ]);
    }
}
