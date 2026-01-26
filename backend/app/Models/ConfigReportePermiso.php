<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigReportePermiso extends Model
{
    protected $table = 'config_reporte_permisos';

    protected $fillable = [
        'modulo',
        'etiqueta',
        'sw_imprime',
        'sw_correo'
    ];

    protected $casts = [
        'sw_imprime' => 'boolean',
        'sw_correo' => 'boolean',
    ];
}