<?php

namespace App\Http\Resources;

use App\Traits\HideResourceFieldsFromRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    use HideResourceFieldsFromRequest;

    //https://medium.com/hackernoon/hiding-api-fields-dynamically-laravel-5-5-82744f1dd15a

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
