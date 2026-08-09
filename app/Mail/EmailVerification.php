<?php

namespace App\Mail;

use App\Helpers\CacheManager;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $to, $expires, $link, $logo;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($to)
    {
        $this->logo = system_logo_url();
        $token = \Str::random(16);
        CacheManager::put($to, $token, CacheManager::ttl_day);
        $expires = now()->addHours(24)->timestamp;
        $this->expires = $expires;
        $domain = config('app.url');
        $this->link = $domain."auth/email/verify?email=$to&expires=$expires&token=$token";
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.email-verification');
    }
}
