<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class SystemUsuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'system_usuarios';
    protected $primaryKey = 'usuario_id';
    public $timestamps = false; // Ajustar si tiene created_at/updated_at

    protected $fillable = [
        'usuario',
        'nombre',
        'descripcion',
        'passwd',
        'sw_admin',
        'activo'
    ];

    protected $hidden = [
        'passwd',
    ];

    protected $casts = [
        'sw_admin' => 'integer',
        'activo' => 'integer'
    ];
}