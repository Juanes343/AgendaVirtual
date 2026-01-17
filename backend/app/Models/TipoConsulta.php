<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoConsulta extends Model
{
    protected $table = 'tipos_consulta';
    protected $primaryKey = 'tipo_consulta_id';
    public $timestamps = false;
}
