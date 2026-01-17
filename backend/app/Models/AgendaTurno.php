<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgendaTurno extends Model
{
    protected $table = 'agenda_turnos';
    protected $primaryKey = 'agenda_turno_id';
    public $timestamps = false;
    
    // Relaciones si se usaran con Eloquent
    public function profesional()
    {
        return $this->belongsTo(Profesional::class, 'profesional_id', 'tercero_id');
    }
}