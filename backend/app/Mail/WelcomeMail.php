<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre;
    public $documento;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($nombre, $documento)
    {
        $this->nombre = $nombre;
        $this->documento = $documento;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('¡Bienvenido a SanDi•Med! - Registro Exitoso')
                    ->view('emails.welcome');
    }
}