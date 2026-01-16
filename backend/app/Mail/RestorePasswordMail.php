<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RestorePasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre;
    public $link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($nombre, $link)
    {
        $this->nombre = $nombre;
        $this->link = $link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Restablecimiento de Contraseña - Agenda Virtual')
                    ->view('emails.restore_password');
    }
}