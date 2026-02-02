<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfigReportePermiso;

class ConfigReportePermisoController extends Controller
{
    /**
     * Obtener la configuración actual de permisos de impresión/correo.
     */
    public function index()
    {
        // Devolvemos todos los módulos configurados
        $permisos = ConfigReportePermiso::all();
        
        // Transformar a un objeto mapa para facilitar uso en frontend: 
        // { 'CONSULTA_EXTERNA': { sw_imprime: true, sw_correo: false }, ... }
        $map = [];
        foreach ($permisos as $p) {
            $map[$p->modulo] = [
                'id' => $p->id,
                'modulo' => $p->modulo,
                'etiqueta' => $p->etiqueta,
                'sw_imprime' => $p->sw_imprime,
                'sw_correo' => $p->sw_correo,
                'estado' => $p->estado,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $map,
            'list' => $permisos // También enviamos lista por si se necesita iterar en panel admin
        ]);
    }

    /**
     * Actualizar permisos (para panel administrativo)
     */
    public function update(Request $request)
    {
        // Se espera un array de configuraciones o actualización individual
        // Caso simple: actualizar uno por ID
        $data = $request->validate([
            'id' => 'required|integer',
            'sw_imprime' => 'required|boolean',
            'sw_correo' => 'required|boolean',
            'estado' => 'nullable|string|max:1'
        ]);

        $permiso = ConfigReportePermiso::findOrFail($data['id']);
        $permiso->sw_imprime = $data['sw_imprime'];
        $permiso->sw_correo = $data['sw_correo'];
        if (isset($data['estado'])) {
            $permiso->estado = $data['estado'];
        }
        $permiso->save();

        return response()->json([
            'success' => true,
            'message' => 'Permiso actualizado correctamente',
            'data' => $permiso
        ]);
    }
}