<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemUsuarioVirtual extends Model
{
    use HasFactory;

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

    /**
     * Relación con el paciente
     */
    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'paciente_id', 'paciente_id')
                    ->where('tipo_id_paciente', $this->tipo_documento);
    }
}
