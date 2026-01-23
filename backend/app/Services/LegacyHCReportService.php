<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LegacyHCReportService
{
    private string $basePath = '/var/www/html/php74/PRUEBAS_SANDIEGO_RIPS';

    public function generarHistoriaCompleta(int $ingreso): string
    {
        // 1. Bootstrap SIIS
        $this->bootstrapLegacy();

        // 2. Evolución
        $evolucion = DB::table('hc_evoluciones')
            ->where('ingreso', $ingreso)
            ->orderBy('fecha', 'desc')
            ->first();

        if (!$evolucion) {
            throw new \Exception('No se encontró evolución para el ingreso');
        }

        // 3. Datos base SIIS
        $datosEvolucion = GetDatosEvolucion($evolucion->evolucion_id);
        $datosPaciente  = GetDatosPaciente($evolucion->ingreso);

        // 4. Submódulos EXACTOS de la evolución
        $submodulos = DB::table('hc_evoluciones_submodulos')
            ->where('evolucion_id', $evolucion->evolucion_id)
            ->orderBy('fecha_registro')
            ->pluck('submodulo');

        // 5. HTML final
        $html = '';

        foreach ($submodulos as $submodulo) {
            $html .= $this->ejecutarSubmodulo(
                $submodulo,
                $datosEvolucion,
                $datosPaciente
            );
        }

        return $html;
    }

    private function ejecutarSubmodulo(string $submodulo, array $datosEvolucion, array $datosPaciente): string
    {
        $ruta = "{$this->basePath}/hc_modules/{$submodulo}/hc_{$submodulo}.php";

        if (!file_exists($ruta)) {
            return "<!-- Submodulo {$submodulo} no encontrado -->";
        }

        require_once $ruta;

        if (!class_exists($submodulo)) {
            return "<!-- Clase {$submodulo} no existe -->";
        }

        $obj = new $submodulo();

        // MÉTODO SIIS REAL
        $obj->InitSubmodulo(
            $datosEvolucion,
            1,
            'frm_' . $submodulo,
            $datosPaciente
        );

        if (method_exists($obj, 'GetReporte_Html')) {
            $out = $obj->GetReporte_Html();
            if ($out !== true && $out !== 1) {
                return $out;
            }
        }

        return '';
    }

    private function bootstrapLegacy(): void
    {
        require_once $this->basePath . '/config.php';
        require_once $this->basePath . '/lib/adodb.inc.php';
        require_once $this->basePath . '/lib/funciones.php';
        require_once $this->basePath . '/lib/hc_functions.php';
    }
}