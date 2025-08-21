<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mail_body;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($mail_body){
        $this->mail_body = $mail_body;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->mail_body->subject)->markdown('emails.demo')->with('mail_body', $this->mail_body);
    }
}