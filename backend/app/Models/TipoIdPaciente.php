<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoIdPaciente extends Model
{
    protected $table = 'tipos_id_pacientes';
    protected $primaryKey = 'tipo_id_paciente';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; 

    protected $fillable = [
        'tipo_id_paciente',
        'descripcion',
        'indice_de_ordenasc'
    ];
}