<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenAgendaVirtual extends Model
{
    use HasFactory;

    protected $table = 'tokens_agenda_virtual';
    protected $primaryKey = 'id_token';
    public $timestamps = false; // Desactivamos timestamps automáticos porque usamos fecha_registro propia

    protected $fillable = [
        'incriptacion',   // Aquí guardaremos el token o hash
        'paciente_id',
        'tipo_documento',
        'fecha_registro',
        'estado'          // '1' activo, '0' usado/inactivo
    ];
}