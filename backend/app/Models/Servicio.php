<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    // Nombre de tabla inferido, ajustar según BD real (ej. 'cups' o 'servicios')
    protected $table = 'servicios'; 
    protected $primaryKey = 'servicio_id';
    public $timestamps = false;
}
