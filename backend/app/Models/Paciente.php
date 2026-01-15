<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    use HasFactory;

    protected $table = 'pacientes';
    
    // Laravel no soporta PK compuestas por defecto en $primaryKey
    // Se recomienda no usar incremento automático si es compuesta.
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;        

    protected $fillable = [
        'paciente_id',
        'tipo_id_paciente',
        'primer_apellido',
        'segundo_apellido',
        'primer_nombre',
        'segundo_nombre',
        'fecha_nacimiento',
        'fecha_nacimiento_es_calculada',
        'residencia_direccion',
        'residencia_telefono',
        'zona_residencia',
        'ocupacion_id',
        'fecha_registro',
        'sexo_id',
        'tipo_estado_civil_id',
        'foto',
        'tipo_pais_id',
        'tipo_dpto_id',
        'tipo_mpio_id',
        'paciente_fallecido',
        'usuario_id',
        'nombre_madre',
        'observaciones',
        'tipo_comuna_id',
        'tipo_barrio_id',
        'tipo_estrato_id',
        'lugar_expedicion_documento',
        'sw_ficha',
        'celular_telefono',
        'email',
        'foto_paciente',
        'ln_tipo_pais_id',
        'ln_tipo_dpto_id',
        'ln_tipo_mpio_id',
        'telefono_smart',
    ];
}
