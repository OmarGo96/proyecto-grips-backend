<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivactionCode extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $token;
    public $date_reg;
    public function __construct($token, $date_reg)
    {
        $this->token = $token;
        $this->date_reg = $date_reg;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('app.MAIL_FROM_ADDRESS'), config('app.MAIL_FROM_NAME'))
        ->view('mails.usr-activation-code');
    }
}
