<?php


    namespace App\Services;


    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Str;

    class SMSService
    {
        public static $last_error = null;

        public function send($phone, $code, $message = null): bool
        {
            try {
                if ($message and Str::contains($message, '()')) {
                    $content = Str::replace('()', $code, $message);
                } else {
                    $content = $code;
                }

                //send
                $sms_api = "https://mazinhost.com/smsv1/sms/api?action=send-sms&api_key=Y2FyZGhlcm8yNDlAZ21haWwuY29tOko4WklzTyNRMm4=&to=" . $phone . "&from=PinkStore&sms=" . $content ."&unicode=1";

                $response = Http::withOptions(['verify' => false])->get($sms_api);

                if ($response->successful()) {
                    $endpoint = json_decode($response->body());
                    $endpoint_code = $endpoint->code;
                    $endpoint_message = $endpoint->message;
                    if ($endpoint_code == "ok") {
                        return true;
                    } else {
                        return false;
                    }
                } else if ($response->serverError()) {
                    return false;
                }
            } catch (\Exception $exception) {
                self::$last_error = $exception->getMessage();
            }

            return false;
        }


    }