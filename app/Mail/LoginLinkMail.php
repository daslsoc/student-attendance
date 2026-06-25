<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public $loginLink;

    public function __construct($loginLink)
    {
        $this->loginLink = $loginLink;
    }

    public function build()
    {
        return $this->subject('Your Login Link')
                    ->markdown('emails.loginlink')
                    ->with('loginLink', $this->loginLink);
    }
}
