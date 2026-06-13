<?php


    namespace App\Services;


    use App\Http\Requests\FCMRequest;
    use App\Jobs\UpdateLastSeenJob;
    use App\Models\User;
    use App\Notifications\GenericNotification;

    class FirBaseService
    {
        public function send(FCMRequest $request):bool
        {
            try{
                $to = $request->input('to');
                $ids = $request->input('users');

                $fcmTokens = $to == "all" ?
                    User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray() :
                    User::find($ids)->pluck('fcm_token')->toArray();

                $fcmTokens = User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();

                //Notification::send(null,new SendPushNotification($request->title,$request->message,$fcmTokens));

                /* or */

                auth()->user()->notify(new GenericNotification($request->title, $request->message, $fcmTokens));

                /* or */

                // Larafirebase::withTitle($request->title)
                //     ->withBody($request->message)
                //     ->sendMessage($fcmTokens);

                return true;
                return $this->responder("Notification sent", 200, [])->respond();

            }catch(\Exception $e){
            }

            return false;

        }
    }