<?php

namespace App\Http\Controllers\API;

use App\Models\Tenant;
use App\Traits\HttpResponses;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use function Filament\authorize;

class BaseController extends \Illuminate\Routing\Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests, HttpResponses;


    public function authorizeRole(string $role, string $message = null)
    {
        $user = auth('api')->user();
        if (!$user or !$user->hasRole($role))
            abort(403, $message ?? "Forbidden");
    }

    public function authorizeOwnership($record_owner_id, $message = null)
    {
        if (auth('api')->id() != $record_owner_id)
            abort(403, $message ?? "Forbidden");
    }

    public function getTenantId()
    {
        return request()->header('Tenant-Id');
    }

    public function getTenant($abortIfNotFound = true, $code = 400): Tenant
    {
        $tenant = Tenant::find($this->getTenantId());

        if (!$tenant and $abortIfNotFound)
            abort(400, "Tenant not found.");

        return $tenant;
    }

    public function checkAbility($action, ?Model $model): bool
    {
        try {
            return authorize($action, $model)->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function canDelete(Model $model): bool
    {
        return $this->checkAbility('delete', $model);
    }
}
