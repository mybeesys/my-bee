<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Subscription extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function isSubscribedTo($plan_id, Client $client): bool
    {
        $client->loadMissing(['user', 'subscription.plan']);

        if (!$client->user->hasRole(User::ROLE_CLIENT)) {
            throw new \Exception("Client is not valid for subscription.");
        }

        return $client->subscriptions->where('plan_id', $plan_id)->first() !== null;
    }

    public static function subscribe(Plan $plan, Client $client): Subscription
    {
        $client->loadMissing(['user', 'subscription.plan']);

        if (!$client->user->hasRole(User::ROLE_CLIENT)) {
            throw new \Exception("Client is not valid for subscription.");
        }

//        if ($client->subscription) {
//            if ($client->subscription->plan_id === $plan->id)
//                throw new \Exception("Tenant already subscribed to this plan.");
//
//            $client->subscription->update(['subscribed' => 0]);
//        }

        Subscription::create([
            'plan_id' => $plan->id,
            'client_id' => $client->id,
            'start_date' => now(),
            'price' => $plan->price,
        ]);

        $client->refresh();

        return $client->subscription;
    }
}
