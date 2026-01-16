<?php

namespace App\Models;

// 1. Cambiar Model por Authenticatable
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens; // 2. Importar Sanctum

// Heredar de Authenticatable en lugar de Model
class SystemUsuarioVirtual extends Authenticatable
{
    use HasFactory, HasApiTokens; // 3. Usar el trait HasApiTokens

    protected $table = 'system_usuarios_virtual';
    protected $primaryKey = 'usuario_id_virtual';
    public $timestamps = false;

    protected $fillable = [
        'paciente_id',
        'tipo_documento',
        'passwd',
    ];

    protected $hidden = [
        'passwd',
    ];
    
    // Necesario para Auth::attempt si la contraseña no se llama 'password'
    public function getAuthPassword()
    {
        return $this->passwd;
    }

    /**
     * Relación con el paciente
     */
    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'paciente_id')
                    ->where('tipo_id_paciente', $this->tipo_documento);
    }
}