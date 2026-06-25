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
	$dateFormat = date('Y-m-d');
        return $this->subject('Attendance Login Link - '. $dateFormat)
                    ->markdown('emails.loginlink')
                    ->with('loginLink', $this->loginLink);
    }
}
