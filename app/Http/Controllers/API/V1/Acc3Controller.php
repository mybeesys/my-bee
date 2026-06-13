<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\Acc3Resource;
use App\Models\Acc3;

class Acc3Controller extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Acc3::with(['acc2.acc1'])->get();
        return $this->responder(__('messages.api.retrieved'), 200, Acc3Resource::collection($data))->respond();
    }
}
