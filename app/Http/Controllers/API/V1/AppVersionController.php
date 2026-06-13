<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\AppVersionResource;
use App\Models\AppVersion;

class AppVersionController extends BaseController
{
    /**
     * List all versions
     *
     * @unauthenticated
     * @group App info
     */

    public function versions()
    {
        $items = AppVersion::all();
        return $this->responder(__("messages.api.retrieved"), 200, AppVersionResource::collection($items))->respond();
    }

    /**
     * Get Latest version
     *
     * @unauthenticated
     * @group App info
     */

    public function latestVersion()
    {
        $item = AppVersion::orderByDesc('version_code')->first();
        return $this->responder(__("messages.api.retrieved"), 200, new AppVersionResource($item))->respond();
    }
}
