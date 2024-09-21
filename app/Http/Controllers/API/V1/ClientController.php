<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterClientRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\InternationalPhoneRule;
use App\Services\RoleService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientController extends BaseController
{

    public function register(RegisterClientRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $user_pass = $data['password'];

        try {
            DB::beginTransaction();

            $names = explode(' ', $data['name']);

            $user_data = [
                'first_name' => $names[0] ?? 'first name',
                'second_name' => $names[1] ?? 'last name',
                'third_name' => $names[2] ?? null,
                'fourth_name' => $names[3] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'],
                'gender' => $data['gender'] ?? null,
                'address' => $data['address'] ?? null,
                'password' => Hash::make($user_pass),
                'active' => 1,
            ];

            //create user
            $user = User::create($user_data);

            //assign role client to user
            RoleService::instance()->assignRole($user, User::ROLE_CLIENT);

            $data['user_id'] = $user->id;

            //create client
            $client = Client::create(Arr::except($data, ['password', 'password_confirmation', 'gender']));

            Subscription::subscribe(Plan::firstWhere('price', 0), $client);

            $token = $user->createToken("API token of " . $user->full_name)->plainTextToken;

            DB::commit();

            return $this->responder(__('fields.messages.client_registration_completed'), 201,
                [
                    "otpSent" => false,
                    'otpInterval' => 5 * 60,
                    'user' => new UserResource($user),
                    'token' => $token,
                ]
            )->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->responder(__('fields.messages.client_registration_failed'), 500,
                [
                    'message' => $exception->getMessage()
                ])->respond();

        }
    }

    public function login(LoginRequest $request)
    {
        $identifier = $request->input('email_or_phone');

        $token = null;

        if (is_numeric($identifier)) {
            $method = "phone";
            $passes = (new InternationalPhoneRule(false))->passes("phone", $identifier);
            if (!$passes)
                return $this->responder(__('messages.phone_invalid'), 422, [], [
                    'email_or_phone' => __('messages.phone_invalid'),
                ])->respond();

        } elseif (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $method = "email";
        } else {
            return $this->responder(__('messages.invalid_login_identifier'), 422, [], [
                'email_or_phone' => __('messages.invalid_login_identifier'),
            ])->respond();
        }

        $user = User::with(['roles', 'tenants.client.user'])->where($method, $identifier)->first();

        if ($user and $user->hasAnyRole([User::ROLE_SUPER_ADMIN, User::ROLE_SUPER_VISOR]))
            return $this->responder(__("auth.failed"), 401)->respond();

        if ($user && Hash::check($request->input('password'), $user->password)) {
            if ($user->hasRole(User::ROLE_CLIENT)) {
                $token = $user->createToken("API token of " . $user->full_name)->plainTextToken;
                return $this->responder(__('fields.messages.login_success'), 200,
                    [
                        'user' => new UserResource($user),
                        'token' => $token,
                    ]
                )->respond();
            }

            //regular tenant user
            if ($user->tenants->isNotEmpty()) {
                $token = $user->createToken("API token of " . $user->full_name)->plainTextToken;
                return $this->responder(__('fields.messages.login_success'), 200,
                    [
                        'user' => new UserResource($user),
                        'token' => $token,
                    ]
                )->respond();
            } else {
                return $this->responder(__("messages.no_tenant_access"), 403)->respond();
            }

        } else {
            return $this->responder(__("auth.failed"), 401)->respond();
        }
    }

    public function createTenant(StoreTenantRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $user = auth('sanctum')->user();

            $data['client_id'] = $user->client->id;

            $data['slug'] = custom_slug($data['name']);

            $t = Tenant::create($data);

            (new TenantService())->seedData($t->id);

            $t->members()->attach($user);

            DB::commit();

            return $this->responder(__('messages.created'), 201, new TenantResource($t))->respond();
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function updateTenant(UpdateTenantRequest $request)
    {
        $data = $request->validated();

        $tenant = $this->getTenant();
        $tenant_social_media = $tenant->store_social_media_links ?? [];

        if ($request->filled('store_social_media_links_facebook'))
            $tenant_social_media['facebook'] = $request->store_social_media_links_facebook;

        if ($request->filled('store_social_media_links_instagram'))
            $tenant_social_media['instagram'] = $request->store_social_media_links_instagram;

        if ($request->filled('store_social_media_links_twitter'))
            $tenant_social_media['twitter'] = $request->store_social_media_links_twitter;

        if ($request->filled('store_social_media_links_youtube'))
            $tenant_social_media['youtube'] = $request->store_social_media_links_youtube;

        if ($request->filled('store_social_media_links_snapchat'))
            $tenant_social_media['snapchat'] = $request->store_social_media_links_snapchat;

        if ($request->filled('store_social_media_links_whatsapp'))
            $tenant_social_media['whatsapp'] = $request->store_social_media_links_whatsapp;

        $data['store_social_media_links'] = $tenant_social_media;

        //update slug
        if (array_key_exists('name', $data)) {
            if ($data['name'] != $tenant->name)
                $data['slug'] = custom_slug($data['name']);

        }

        $tenant->update(Arr::except($data,
            [
                'cover',
                'store_social_media_links_facebook',
                'store_social_media_links_instagram',
                'store_social_media_links_twitter',
                'store_social_media_links_youtube',
                'store_social_media_links_snapchat',
                'store_social_media_links_whatsapp'
            ]
        ));

        if ($request->hasFile("cover")) {
            $tenant->clearMediaCollection("covers");
            $tenant->addMedia("cover")->toMediaCollection('covers');
        }

        if ($request->hasFile("logo")) {
            $tenant->clearMediaCollection("logos");
            $tenant->addMedia("logo")->toMediaCollection('logos');
        }

        return $this->responder(__('messages.updated'), 200, new TenantResource($tenant))->respond();
    }

    public function listTenants(Request $request)
    {
        $user = auth('sanctum')->user();

        return $this->responder(__('messages.api.retrieved'), 200, TenantResource::collection($user->tenants))->respond();
    }

    public function me(Request $request)
    {
        $user = auth('sanctum')->user();

        return $this->responder(__('messages.updated'), 200, new UserResource($user))->respond();
    }

}
