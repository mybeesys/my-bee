<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $secret, $logo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
        $this->logo = system_logo_url();
    }

    public function build()
    {
        return $this->markdown('emails.email-password');
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
