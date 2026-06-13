<?php

    namespace App\Http\Controllers\API\V1;

    use App\Helpers\CacheManager;
    use App\Http\Controllers\API\BaseController;
    use App\Http\Resources\AppVersionResource;
    use App\Http\Resources\InAppAlertResource;
    use App\Http\Resources\SettingResource;
    use App\Jobs\AppStatusJob;
    use App\Models\AppVersion;
    use App\Models\Setting;
    use App\Models\User;
    use App\Models\UserDeviceToken;
    use App\Services\CartService;
    use App\Services\InAppAlertService;
    use App\Services\SettingService;
    use App\Services\UserService;
    use App\Traits\Responder;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Str;

    class MobileAppStatusController extends BaseController
    {

        /**
         * App Status [started, closed]
         *
         * @unauthenticated
         * @group App info
         */
        public function status(\App\Http\Requests\MobileAppStatusRequest $request)
        {
            $status = $request->get('status');

            $latestVersion = Cache::rememberForever('app-latest-version', function () {
                return AppVersion::orderByDesc('version_code')->first();
            });

            $data = [
                'status' => $status,
                'latestVersion' => $latestVersion == null ? null : new AppVersionResource($latestVersion),
            ];

            return $this->responder('Status Updated', 200, $data)->respond();
        }

    }
