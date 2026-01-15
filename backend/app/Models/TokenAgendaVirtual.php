<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TokenAgendaVirtual extends Model
{
    use HasFactory;

    protected $table = 'tokens_agenda_virtual';
    protected $primaryKey = 'id_token';

    protected $fillable = [
        'incriptacion',
        'paciente_id',
        'tipo_documento',
        'fecha_registro',
        'estado',
    ];
}
