<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PreSolicitudMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $solicitud;
    public $type;
    public $pdf;
    public function __construct($type, $solicitud, $pdf)
    {
        $this->type = $type;
        $this->solicitud = (object) $solicitud;
        $this->pdf = $pdf;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->from(config('app.MAIL_FROM_ADDRESS'), config('app.MAIL_FROM_NAME'))
        ->view('mails.pre-solicitud');

        $this->attachData($this->pdf->output(), $this->solicitud->name.'.pdf');

        return $this;
    }
}
