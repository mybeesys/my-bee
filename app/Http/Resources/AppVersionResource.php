<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'versionCode' => $this->version_code,
            'updateSummary' => collect($this->update_summary)->pluck('summary')->toArray(),
            'description' => $this->description,
            'mustUpdate' => $this->must_update,
        ];
    }
}
