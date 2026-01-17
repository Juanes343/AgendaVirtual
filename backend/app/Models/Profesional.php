<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profesional extends Model
{
    protected $table = 'profesionales';
    protected $primaryKey = 'tercero_id'; // Clave compuesta usualmente, cuidado con Eloquent
    public $timestamps = false;
}